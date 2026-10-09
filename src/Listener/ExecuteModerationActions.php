<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Listener;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Discussion\Event\Saving as DiscussionSaving;
use Flarum\Extension\ExtensionManager;
use Flarum\Flags\Flag;
use Flarum\Post\Event\Saving as PostSaving;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Huoxin\FilterRuleManager\Model\FilterBlockLog;
use Huoxin\FilterRuleManager\Model\Ruleset;
use Huoxin\FilterRuleManager\Repository\RulesetRepository;
use Huoxin\FilterRuleManager\Service\RuleEvaluator;
use Huoxin\FilterRuleManager\Service\RulesetMatcher;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Contracts\Translation\TranslatorInterface;

class ExecuteModerationActions
{
    public function __construct(
        protected RuleEvaluator $evaluator,
        protected RulesetMatcher $matcher,
        protected ExtensionManager $extensions,
        protected TranslatorInterface $translator,
        protected SettingsRepositoryInterface $settings,
        protected RulesetRepository $rulesets
    ) {
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(PostSaving::class, [$this, 'moderatePost']);
        $events->listen(DiscussionSaving::class, [$this, 'moderateDiscussion']);
    }

    public function moderatePost(PostSaving $event): void
    {
        $hasApproval = $this->extensions->isEnabled('flarum-approval');
        $hasFlags = $this->extensions->isEnabled('flarum-flags');

        if (! $hasApproval && ! $hasFlags) {
            return;
        }

        $post = $event->post;

        // If the post is being explicitly approved by a moderator, forgive evasion for this user
        /** @phpstan-ignore-next-line */
        if ($post->exists && $post->isDirty('is_approved') && $post->is_approved && $post->user_id) {
            FilterBlockLog::where('user_id', $post->user_id)
                ->update(['is_cleared' => true]);
        }

        // Only evaluate if this is a new post or the content was edited.
        // This prevents re-evaluating during delete, recover, or approval actions.
        if ($post->exists && ! $post->isDirty('content')) {
            return;
        }

        $onlyField = $post->exists ? 'content' : null;
        $this->evaluateModeration($event, $post, $event->actor, $onlyField);
    }

    public function moderateDiscussion(DiscussionSaving $event): void
    {
        $discussion = $event->discussion;

        // Only evaluate if the discussion already exists and its title was modified.
        // New discussions are handled by moderatePost because their first post is also saved.
        if ($discussion->exists && $discussion->isDirty('title')) {
            $firstPost = $discussion->firstPost;
            if ($firstPost) {
                $firstPost->setRelation('discussion', $discussion);
                $this->evaluateModeration($event, $firstPost, $event->actor, 'title');
            }
        }
    }

    /**
     * @param PostSaving|DiscussionSaving $event
     * @param Post $post
     * @param User|null $actor
     * @param string|null $onlyField
     */
    private function evaluateModeration($event, $post, $actor, ?string $onlyField = null): void
    {
        $hasApproval = $this->extensions->isEnabled('flarum-approval');
        $hasFlags = $this->extensions->isEnabled('flarum-flags');

        if (! $hasApproval && ! $hasFlags) {
            return;
        }

        $globalAutoFlag = (bool) $this->settings->get('huoxin-filter-rule-manager.global_auto_flag', true);
        $globalRequireApproval = (bool) $this->settings->get('huoxin-filter-rule-manager.global_require_approval', true);
        $globalEvasionActive = (bool) $this->settings->get('huoxin-filter-rule-manager.global_evasion_active', false);
        $globalEvasionTimeout = (int) $this->settings->get('huoxin-filter-rule-manager.global_evasion_timeout', 5);
        $globalEvasionThreshold = (int) $this->settings->get('huoxin-filter-rule-manager.global_evasion_threshold', 2);

        // Load all active rulesets once from in-memory cache, filter per concern.
        $allActive = $this->rulesets->getActiveRulesets();

        $rulesets = $allActive->filter(function (Ruleset $ruleset) use ($globalAutoFlag, $globalRequireApproval, $hasFlags, $hasApproval) {
            $willFlag = $hasFlags && ($ruleset->auto_flag ?? $globalAutoFlag);
            $willApprove = $hasApproval && ($ruleset->require_approval ?? $globalRequireApproval);

            return $willFlag || $willApprove || $ruleset->block_cascade;
        });

        $providers = $this->evaluator->getProviders();

        [$defaultRulesets, $customMessages, $requiresApproval, $requiresFlag] =
            $this->collectModerationMatches($rulesets, $post, $actor, $providers, $globalAutoFlag, $globalRequireApproval, $hasFlags, $hasApproval, $onlyField);

        $evasionResult = $this->resolveEvasion($actor, $allActive, $globalEvasionActive, $globalEvasionTimeout, $globalEvasionThreshold);
        $isEvasion = $evasionResult !== null;

        if (empty($defaultRulesets) && empty($customMessages) && ! $isEvasion) {
            return;
        }

        if ($isEvasion) {
            $requiresApproval = true;
            $requiresFlag = true;
        }

        $shouldApprove = $hasApproval && $requiresApproval;
        $shouldFlag = $hasFlags && $requiresFlag;

        if (! $shouldApprove && ! $shouldFlag) {
            return;
        }

        $reasonDetail = $this->buildReasonDetail($defaultRulesets, $customMessages, $isEvasion, $evasionResult);

        $entityBeingSaved = $event instanceof PostSaving ? $event->post : $event->discussion;

        if ($shouldApprove) {
            $this->applyApproval($entityBeingSaved, $post);
        }

        if ($shouldFlag) {
            $this->createFlag($entityBeingSaved, $post, $reasonDetail, 'autoMod');
        }
    }

    /**
     * @param Collection $rulesets
     * @param Post $post
     * @param User|null $actor
     * @param array $providers
     * @param bool $globalAutoFlag
     * @param bool $globalRequireApproval
     * @param bool $hasFlags
     * @param bool $hasApproval
     * @param string|null $onlyField
     * @return array
     */
    private function collectModerationMatches(Collection $rulesets, $post, $actor, array $providers, bool $globalAutoFlag, bool $globalRequireApproval, bool $hasFlags, bool $hasApproval, ?string $onlyField = null): array
    {
        $defaultRulesets = [];
        $customMessages = [];
        $requiresApproval = false;
        $requiresFlag = false;

        foreach ($rulesets as $ruleset) {
            $tokens = $this->matcher->match($ruleset, $post, $actor, $providers, false, $onlyField);
            if ($tokens !== null) {
                $strictEdit = $ruleset->strict_edit ?? (bool) $this->settings->get('huoxin-filter-rule-manager.strict_edit_evaluation', false);

                if ($post->exists && ! $strictEdit) {
                    $oldTokens = $this->matcher->match($ruleset, $post, $actor, $providers, true, $onlyField);

                    if ($oldTokens !== null && $oldTokens === $tokens) {
                        continue; // Violation already existed prior to this edit.
                    }
                }
                $autoFlag = ($ruleset->auto_flag ?? $globalAutoFlag) && $hasFlags;
                $requireApproval = ($ruleset->require_approval ?? $globalRequireApproval) && $hasApproval;

                if ($autoFlag || $requireApproval) {
                    if (! empty($ruleset->flag_message)) {
                        $customMessages[] = $this->evaluator->interpolate($ruleset->flag_message, $tokens);
                    } else {
                        $defaultRulesets[] = $ruleset->name;
                    }
                }

                if ($requireApproval) {
                    $requiresApproval = true;
                }
                if ($autoFlag) {
                    $requiresFlag = true;
                }

                if ($ruleset->block_cascade) {
                    break;
                }
            }
        }

        return [$defaultRulesets, $customMessages, $requiresApproval, $requiresFlag];
    }

    private function buildReasonDetail(array $defaultRulesets, array $customMessages, bool $isEvasion, mixed $evasionResult = null): string
    {
        $messages = [];

        if (! empty($defaultRulesets)) {
            $rulesStr = implode(', ', $defaultRulesets);
            $messages[] = $this->translator->trans('huoxin-filter-rule-manager.forum.flag_message', ['{rulesets}' => $rulesStr]);
        }

        foreach ($customMessages as $customMsg) {
            $messages[] = $customMsg;
        }

        if ($isEvasion) {
            $evasionMessage = $this->buildEvasionMessage($evasionResult);
            if ($evasionMessage !== '') {
                $messages[] = $evasionMessage;
            }
        }

        $reasonDetail = implode("\n\n", $messages);

        return html_entity_decode($reasonDetail, ENT_QUOTES, 'UTF-8');
    }

    private function buildEvasionMessage(mixed $evasionResult): string
    {
        if (empty($evasionResult)) {
            return '';
        }

        if (is_string($evasionResult)) {
            return $this->translator->trans(
                'huoxin-filter-rule-manager.forum.evasion_flag_message',
                [
                    '{ruleset}' => $evasionResult,
                    '{count}' => '1',
                    '{count_formatted}' => '1 time',
                    '{details}' => '',
                ]
            );
        }

        if (! is_array($evasionResult)) {
            return '';
        }

        /** @var Ruleset|null $ruleset */
        $ruleset = $evasionResult['ruleset'] ?? null;
        $rulesetName = (string) ($evasionResult['ruleset_name'] ?? ($ruleset ? $ruleset->name : ''));
        /** @var Collection<int, FilterBlockLog> $logs */
        $logs = $evasionResult['logs'] ?? collect();
        $count = (int) ($evasionResult['count'] ?? $logs->count());
        $timeout = (int) ($evasionResult['timeout'] ?? 5);
        $threshold = (int) ($evasionResult['threshold'] ?? 1);
        $countFormatted = $count === 1 ? '1 time' : "{$count} times";

        // Collect all unique matched terms across all blocked logs
        $allTerms = [];
        foreach ($logs as $log) {
            foreach ($this->extractLogMatchedTerms($log) as $term) {
                if (str_contains($term, ', ')) {
                    foreach (explode(', ', $term) as $t) {
                        $t = trim($t);
                        if ($t !== '') {
                            $allTerms[] = $t;
                        }
                    }
                } else {
                    $t = trim($term);
                    if ($t !== '') {
                        $allTerms[] = $t;
                    }
                }
            }
        }
        $uniqueTerms = array_values(array_unique($allTerms));
        $matchesStr = implode(', ', $uniqueTerms);

        // Build chronological audit timeline of attempts (up to 5 recent attempts)
        $chronological = $logs->sortBy('created_at')->values();
        $totalAttempts = $chronological->count();
        $displayLogs = $chronological->slice(max(0, $totalAttempts - 5));

        $timelineLines = [];
        if ($totalAttempts > 5) {
            $earlierCount = $totalAttempts - 5;
            $timelineLines[] = $this->translator->trans(
                'huoxin-filter-rule-manager.forum.evasion_earlier_attempts',
                ['{count}' => (string) $earlierCount]
            );
        }

        foreach ($displayLogs as $log) {
            $timeStr = $log->created_at->format('H:i:s');
            $terms = $this->extractLogMatchedTerms($log);
            $matchDesc = ! empty($terms) ? implode(', ', $terms) : (string) ($log->message ?? '');
            $snippet = Str::limit(trim((string) preg_replace('/\s+/', ' ', (string) ($log->content ?? ''))), 75);

            $linePrefix = "• [{$timeStr}]";
            $line = $linePrefix;
            if ($matchDesc !== '') {
                $line .= ' '.$this->translator->trans('huoxin-filter-rule-manager.forum.evasion_item_matched', ['{match}' => $matchDesc]);
            }
            if ($snippet !== '') {
                $line .= " | \"{$snippet}\"";
            }
            if ($line !== $linePrefix) {
                $timelineLines[] = $line;
            }
        }

        $timelineStr = implode("\n", $timelineLines);

        // Assemble details block
        $detailsParts = [];
        if ($matchesStr !== '') {
            $detailsParts[] = $this->translator->trans(
                'huoxin-filter-rule-manager.forum.evasion_matched_terms',
                ['{matches}' => $matchesStr]
            );
        }
        if (! empty($timelineLines)) {
            $header = $this->translator->trans('huoxin-filter-rule-manager.forum.evasion_attempts_header');
            $detailsParts[] = $header."\n".$timelineStr;
        }
        $detailsBlock = ! empty($detailsParts) ? "\n\n".implode("\n\n", $detailsParts) : '';

        // Latest attempt info for individual placeholders
        /** @var FilterBlockLog|null $latestLog */
        $latestLog = $logs->first();
        $latestTerms = $latestLog ? $this->extractLogMatchedTerms($latestLog) : [];
        $lastMatchDesc = ! empty($latestTerms) ? implode(', ', $latestTerms) : (string) ($latestLog?->message ?? '');
        $lastContent = Str::limit(trim((string) preg_replace('/\s+/', ' ', (string) ($latestLog?->content ?? ''))), 100);

        // Check for custom configured template
        $customTemplate = (string) $this->settings->get('huoxin-filter-rule-manager.global_evasion_flag_message', '');

        $interpolationTokens = [
            'ruleset' => $rulesetName,
            'count' => (string) $count,
            'count_formatted' => $countFormatted,
            'timeout' => (string) $timeout,
            'threshold' => (string) $threshold,
            'matches' => $matchesStr,
            'last_match' => $lastMatchDesc,
            'last_message' => (string) ($latestLog?->message ?? ''),
            'last_content' => $lastContent,
            'timeline' => $timelineStr,
            'details' => $detailsBlock,
        ];

        if ($customTemplate !== '') {
            $customMessage = $this->evaluator->interpolate($customTemplate, $interpolationTokens);
            // Also replace single brace variables like {ruleset} if administrator entered single braces
            foreach ($interpolationTokens as $key => $val) {
                if (is_string($val)) {
                    $customMessage = str_replace('{'.$key.'}', $val, $customMessage);
                }
            }
            // If the custom template didn't include details or timeline or matches, append details block if present
            if ($detailsBlock !== '' && ! str_contains($customTemplate, 'details') && ! str_contains($customTemplate, 'timeline') && ! str_contains($customTemplate, 'matches')) {
                $customMessage .= $detailsBlock;
            }

            return $customMessage;
        }

        // Standard translated template
        $transParams = [
            '{ruleset}' => $rulesetName,
            '{count}' => (string) $count,
            '{count_formatted}' => $countFormatted,
            '{timeout}' => (string) $timeout,
            '{threshold}' => (string) $threshold,
            '{matches}' => $matchesStr,
            '{details}' => $detailsBlock,
        ];

        return $this->translator->trans('huoxin-filter-rule-manager.forum.evasion_flag_message', $transParams);
    }

    /**
     * Extracts human-readable matched terms/tokens from a single block log.
     *
     * @param FilterBlockLog $log
     * @return string[]
     */
    private function extractLogMatchedTerms(FilterBlockLog $log): array
    {
        $tokens = $log->tokens ?? [];
        if (empty($tokens)) {
            return [];
        }

        $terms = [];

        // Check universal and common text tokens first (RegexRule, ContainsWordRule)
        if (! empty($tokens['matched_text'])) {
            $terms[] = $this->stringifyTokenValue($tokens['matched_text']);
        } elseif (! empty($tokens['matched_string'])) {
            $terms[] = $this->stringifyTokenValue($tokens['matched_string']);
        } elseif (! empty($tokens['matched_word'])) {
            $terms[] = $this->stringifyTokenValue($tokens['matched_word']);
        } elseif (! empty($tokens['matched_pattern'])) {
            $terms[] = $this->stringifyTokenValue($tokens['matched_pattern']);
        } else {
            // Quantitative or other provider tokens (WordCountRule, GroupRule, custom providers)
            foreach ($tokens as $key => $val) {
                if (str_starts_with((string) $key, '__') || $key === 'rule_name') {
                    continue;
                }
                $strVal = $this->stringifyTokenValue($val);
                if ($strVal !== '') {
                    $terms[] = "{$key}: {$strVal}";
                }
            }
        }

        return $terms;
    }

    private function stringifyTokenValue(mixed $val): string
    {
        if (is_array($val)) {
            $flatten = function ($array) use (&$flatten) {
                $result = [];
                foreach ($array as $item) {
                    if (is_array($item)) {
                        $result = array_merge($result, $flatten($item));
                    } else {
                        $result[] = (string) $item;
                    }
                }

                return $result;
            };

            return implode(', ', array_unique($flatten($val)));
        }

        return is_scalar($val) ? (string) $val : '';
    }

    /**
     * @param User|null $actor
     * @param Collection $allActive
     * @param bool $globalEvasionActive
     * @param int $globalEvasionTimeout
     * @param int $globalEvasionThreshold
     * @return array|null
     */
    private function resolveEvasion($actor, $allActive, bool $globalEvasionActive, int $globalEvasionTimeout, int $globalEvasionThreshold): ?array
    {
        if (! $actor || $actor->isGuest()) {
            return null;
        }

        $evasionRulesets = $allActive->filter(function (Ruleset $ruleset) use ($globalEvasionActive) {
            return $ruleset->evasion_active ?? $globalEvasionActive;
        })->keyBy('id');

        if ($evasionRulesets->isEmpty()) {
            return null;
        }

        $maxTimeout = $this->computeMaxEvasionTimeout($evasionRulesets, $globalEvasionTimeout);

        if ($maxTimeout <= 0) {
            return null;
        }

        $recentLogs = FilterBlockLog::where('user_id', $actor->id)
            ->where('is_cleared', false)
            ->whereIn('ruleset_id', $evasionRulesets->keys())
            ->where('created_at', '>=', Carbon::now()->subMinutes($maxTimeout))
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->select('id', 'ruleset_id', 'created_at', 'tokens', 'message', 'content')
            ->get();

        return $this->findTriggeredEvasionContext($evasionRulesets, $recentLogs, $globalEvasionTimeout, $globalEvasionThreshold);
    }

    private function computeMaxEvasionTimeout(Collection $evasionRulesets, int $globalEvasionTimeout): int
    {
        $maxTimeout = 0;
        foreach ($evasionRulesets as $ruleset) {
            $t = $ruleset->evasion_timeout ?? $globalEvasionTimeout;
            if ($t > $maxTimeout) {
                $maxTimeout = $t;
            }
        }

        return $maxTimeout;
    }

    /**
     * @param Collection<int, Ruleset> $evasionRulesets
     * @param Collection<int, FilterBlockLog> $recentLogs
     * @param int $globalEvasionTimeout
     * @param int $globalEvasionThreshold
     * @return array|null
     */
    private function findTriggeredEvasionContext(Collection $evasionRulesets, Collection $recentLogs, int $globalEvasionTimeout, int $globalEvasionThreshold): ?array
    {
        foreach ($evasionRulesets as $rulesetId => $ruleset) {
            $timeout = $ruleset->evasion_timeout ?? $globalEvasionTimeout;
            $threshold = $ruleset->evasion_threshold ?? $globalEvasionThreshold;

            if ($timeout <= 0) {
                continue;
            }

            $cutoff = Carbon::now()->subMinutes($timeout);
            $matchingLogs = $recentLogs->filter(function ($log) use ($rulesetId, $cutoff) {
                return $log->ruleset_id == $rulesetId && Carbon::parse($log->created_at)->gte($cutoff);
            })->values();

            if ($matchingLogs->count() >= max(1, $threshold)) {
                return [
                    'ruleset' => $ruleset,
                    'ruleset_name' => $ruleset->name,
                    'logs' => $matchingLogs,
                    'count' => $matchingLogs->count(),
                    'timeout' => $timeout,
                    'threshold' => $threshold,
                ];
            }
        }

        return null;
    }

    /**
     * @param AbstractModel $entityBeingSaved
     * @param Post $post
     */
    private function applyApproval($entityBeingSaved, $post): void
    {
        /** @phpstan-ignore-next-line */
        $entityBeingSaved->is_approved = false;

        $entityBeingSaved->afterSave(function () use ($post) {
            /** @phpstan-ignore-next-line */
            if ($post->number == 1 && $post->discussion) {
                /** @phpstan-ignore-next-line */
                $post->discussion->is_approved = false;
                $post->discussion->save();
            }
        });
    }

    /**
     * @param AbstractModel $entityBeingSaved
     * @param Post $post
     * @param string $reasonDetail
     * @param string $type
     */
    private function createFlag($entityBeingSaved, $post, string $reasonDetail, string $type): void
    {
        // Prevent duplicate moderation actions on edits
        if ($post->exists) {
            if (Flag::where('post_id', $post->id)->where('type', $type)->exists()) {
                return;
            }
        }

        $entityBeingSaved->afterSave(function () use ($post, $reasonDetail, $type) {
            $flag = new Flag();
            $flag->post_id = $post->id;
            $flag->type = $type;
            $flag->reason_detail = $reasonDetail;
            $flag->created_at = Carbon::now();
            $flag->save();
        });
    }
}
