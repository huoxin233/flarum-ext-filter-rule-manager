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
use Illuminate\Database\Eloquent\Collection;
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
        /** @phpstan-ignore property.notFound */
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
        /** @var Collection<int, Ruleset> $allActive */
        $allActive = $this->rulesets->getActiveRulesets();

        $rulesets = $allActive->filter(function (Ruleset $ruleset) use ($globalAutoFlag, $globalRequireApproval, $hasFlags, $hasApproval) {
            $willFlag = $hasFlags && ($ruleset->auto_flag ?? $globalAutoFlag);
            $willApprove = $hasApproval && ($ruleset->require_approval ?? $globalRequireApproval);

            return $willFlag || $willApprove || $ruleset->block_cascade;
        });

        $providers = $this->evaluator->getProviders();

        [$defaultRulesets, $customMessages, $requiresApproval, $requiresFlag] =
            $this->collectModerationMatches($rulesets, $post, $actor, $providers, $globalAutoFlag, $globalRequireApproval, $hasFlags, $hasApproval, $onlyField);

        $blockedRulesetName = $this->resolveEvasion($actor, $allActive, $globalEvasionActive, $globalEvasionTimeout, $globalEvasionThreshold);
        $isEvasion = $blockedRulesetName !== null;

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

        $reasonDetail = $this->buildReasonDetail($defaultRulesets, $customMessages, $isEvasion, $blockedRulesetName);

        $entityBeingSaved = $event instanceof PostSaving ? $event->post : $event->discussion;

        if ($shouldApprove) {
            $this->applyApproval($entityBeingSaved, $post);
        }

        if ($shouldFlag) {
            $this->createFlag($entityBeingSaved, $post, $reasonDetail, 'autoMod');
        }
    }

    /**
     * @param Collection<int, Ruleset> $rulesets
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

    private function buildReasonDetail(array $defaultRulesets, array $customMessages, bool $isEvasion, ?string $blockedRulesetName): string
    {
        $messages = [];

        if (! empty($defaultRulesets)) {
            $rulesStr = implode(', ', $defaultRulesets);
            $trans = $this->translator->trans('huoxin-filter-rule-manager.forum.flag_message', ['{rulesets}' => $rulesStr]);
            $messages[] = $trans;
        }

        foreach ($customMessages as $customMsg) {
            $messages[] = $customMsg;
        }

        if ($isEvasion) {
            $trans = $this->translator->trans('huoxin-filter-rule-manager.forum.evasion_flag_message', ['{ruleset}' => $blockedRulesetName ?? '']);
            $messages[] = $trans;
        }

        $reasonDetail = implode("\n\n", $messages);

        return html_entity_decode($reasonDetail, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param User|null $actor
     * @param Collection<int, Ruleset> $allActive
     * @param bool $globalEvasionActive
     * @param int $globalEvasionTimeout
     * @param int $globalEvasionThreshold
     * @return string|null
     */
    private function resolveEvasion($actor, $allActive, bool $globalEvasionActive, int $globalEvasionTimeout, int $globalEvasionThreshold): ?string
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
            ->select('ruleset_id', 'created_at')
            ->get();

        $triggered = $this->findTriggeredEvasionRuleset($evasionRulesets, $recentLogs, $globalEvasionTimeout, $globalEvasionThreshold);

        return $triggered ? $triggered->name : null;
    }

    /**
     * @param Collection<int, Ruleset> $evasionRulesets
     * @param int $globalEvasionTimeout
     * @return int
     */
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
     * @return Ruleset|null
     */
    private function findTriggeredEvasionRuleset(Collection $evasionRulesets, Collection $recentLogs, int $globalEvasionTimeout, int $globalEvasionThreshold): ?Ruleset
    {
        foreach ($evasionRulesets as $rulesetId => $ruleset) {
            $timeout = $ruleset->evasion_timeout ?? $globalEvasionTimeout;
            $threshold = $ruleset->evasion_threshold ?? $globalEvasionThreshold;

            if ($timeout <= 0) {
                continue;
            }

            $cutoff = Carbon::now()->subMinutes($timeout);
            $count = $recentLogs->filter(function ($log) use ($rulesetId, $cutoff) {
                return $log->ruleset_id == $rulesetId && Carbon::parse($log->created_at)->gte($cutoff);
            })->count();

            if ($count >= max(1, $threshold)) {
                return $ruleset;
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
        /** @phpstan-ignore property.notFound */
        $entityBeingSaved->is_approved = false;

        $entityBeingSaved->afterSave(function () use ($post) {
            /** @phpstan-ignore binaryOp.invalid */
            if ($post->number == 1 && $post->discussion) {
                /** @phpstan-ignore property.notFound */
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
