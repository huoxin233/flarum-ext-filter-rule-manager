<?php

namespace Huoxin\FilterRuleManager\Listener;

use Flarum\Extension\ExtensionManager;
use Flarum\Post\Event\Saving;
use Huoxin\FilterRuleManager\Model\Ruleset;
use Huoxin\FilterRuleManager\Service\RuleEvaluator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Carbon\Carbon;
use Symfony\Contracts\Translation\TranslatorInterface;
use Flarum\Settings\SettingsRepositoryInterface;

class ExecuteModerationActions
{
    public function __construct(
        protected RuleEvaluator $evaluator,
        protected ExtensionManager $extensions,
        protected ConnectionInterface $db,
        protected TranslatorInterface $translator,
        protected SettingsRepositoryInterface $settings
    ) {
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Saving::class, [$this, 'moderatePost']);
    }

    public function moderatePost(Saving $event): void
    {
        $hasApproval = $this->extensions->isEnabled('flarum-approval');
        $hasFlags = $this->extensions->isEnabled('flarum-flags');

        if (! $hasApproval && ! $hasFlags) {
            return;
        }

        $post = $event->post;

        // If the post is being explicitly approved by a moderator, forgive evasion for this user
        if ($post->exists && $post->isDirty('is_approved') && $post->is_approved == true && $post->user_id) {
            $this->db->table('filter_rule_block_logs')
                ->where('user_id', $post->user_id)
                ->update(['is_cleared' => true]);
        }

        // Only evaluate if this is a new post or the content was edited.
        // This prevents re-evaluating during delete, recover, or approval actions.
        if ($post->exists && ! $post->isDirty('content')) {
            return;
        }

        $content = (string) $post->content;
        $discussion = $post->discussion;
        $title = $discussion ? (string) $discussion->title : '';

        $isFirstPost = false;
        if ($discussion) {
            $isFirstPost = $discussion->first_post_id === $post->id
                || $discussion->first_post_id === null;
        }

        $globalAutoFlag = (bool) $this->settings->get('huoxin-filter.global_auto_flag', true);
        $globalRequireApproval = (bool) $this->settings->get('huoxin-filter.global_require_approval', true);
        $globalEvaluateTitle = (bool) $this->settings->get('huoxin-filter.global_evaluate_title', true);

        /** @var Ruleset[] $rulesets */
        $rulesets = Ruleset::active()
            ->where(function ($query) use ($globalAutoFlag, $globalRequireApproval) {
                $query->where(function ($q) use ($globalAutoFlag) {
                    if ($globalAutoFlag) {
                        $q->where('auto_flag', true)->orWhereNull('auto_flag');
                    } else {
                        $q->where('auto_flag', true);
                    }
                })->orWhere(function ($q) use ($globalRequireApproval) {
                    if ($globalRequireApproval) {
                        $q->where('require_approval', true)->orWhereNull('require_approval');
                    } else {
                        $q->where('require_approval', true);
                    }
                });
            })
            ->ordered()
            ->with('rules')
            ->get();

        $defaultRulesets = [];
        $customMessages = [];
        $providers = $this->evaluator->getProviders();
        $blockedRulesetName = null;
        $requiresApproval = false;
        $requiresFlag = false;

        foreach ($rulesets as $ruleset) {
            if (! $this->evaluator->scopeMatches($ruleset, $post->discussion)) {
                continue;
            }

            $targetContent = $content;

            $evaluateTitle = $ruleset->evaluate_title ?? $globalEvaluateTitle;
            if ($isFirstPost && $title !== '' && $evaluateTitle !== false) {
                $targetContent = $title."\n\n".$content;
            }

            $tokens = $this->evaluator->evaluateRuleset($ruleset, $targetContent, $providers);
            if ($tokens !== null) {
                if (! empty($ruleset->flag_message)) {
                    $customMessages[] = $this->evaluator->interpolate($ruleset->flag_message, $tokens);
                } else {
                    $defaultRulesets[] = $ruleset->name;
                }

                $autoFlag = $ruleset->auto_flag ?? $globalAutoFlag;
                $requireApproval = $ruleset->require_approval ?? $globalRequireApproval;

                if ($requireApproval)
                    $requiresApproval = true;
                if ($autoFlag)
                    $requiresFlag = true;

                if ($ruleset->block_cascade) {
                    $blockedRulesetName = $ruleset->name;
                }
            }
        }

        $actor = $event->actor;
        $isEvasion = false;

        if ($actor && ! $actor->isGuest()) {
            $globalEvasionActive = (bool) $this->settings->get('huoxin-filter.global_evasion_active', false);
            $globalEvasionTimeout = (int) $this->settings->get('huoxin-filter.global_evasion_timeout', 5);
            $globalEvasionThreshold = (int) $this->settings->get('huoxin-filter.global_evasion_threshold', 2);

            $evasionRulesets = Ruleset::where(function ($query) use ($globalEvasionActive) {
                if ($globalEvasionActive) {
                    $query->where('evasion_active', true)->orWhereNull('evasion_active');
                } else {
                    $query->where('evasion_active', true);
                }
            })->get()->keyBy('id');

            if ($evasionRulesets->isNotEmpty()) {
                $maxTimeout = 0;
                foreach ($evasionRulesets as $ruleset) {
                    $timeout = $ruleset->evasion_timeout ?? $globalEvasionTimeout;
                    if ($timeout > $maxTimeout)
                        $maxTimeout = $timeout;
                }

                if ($maxTimeout > 0) {
                    $recentBlocks = $this->db->table('filter_rule_block_logs')
                        ->where('user_id', $actor->id)
                        ->where('is_cleared', false)
                        ->where('created_at', '>=', Carbon::now()->subMinutes($maxTimeout))
                        ->get();

                    foreach ($evasionRulesets as $rulesetId => $ruleset) {
                        $timeout = $ruleset->evasion_timeout ?? $globalEvasionTimeout;
                        $threshold = $ruleset->evasion_threshold ?? $globalEvasionThreshold;

                        if ($timeout <= 0)
                            continue;

                        $cutoff = Carbon::now()->subMinutes($timeout);
                        $count = $recentBlocks->filter(function ($log) use ($rulesetId, $cutoff) {
                            return $log->ruleset_id == $rulesetId && Carbon::parse($log->created_at)->gte($cutoff);
                        })->count();

                        if ($count >= max(1, $threshold)) {
                            $isEvasion = true;
                            $blockedRulesetName = $ruleset->name;
                            break;
                        }
                    }
                }
            }
        }

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

        $flagType = 'autoMod';

        // Prevent duplicate moderation actions on edits
        if ($post->exists) {
            // If the post is already held for approval and not flagged, we still want to flag it if needed.
            // But if it already has our autoMod flag, skip.
            if ($hasFlags && class_exists(\Flarum\Flags\Flag::class)) {
                if (\Flarum\Flags\Flag::where('post_id', $post->id)->where('type', $flagType)->exists()) {
                    return;
                }
            }
        }

        $messages = [];

        if (! empty($defaultRulesets)) {
            $rulesStr = implode(', ', $defaultRulesets);
            $trans = $this->translator->trans('huoxin-filter-rule-manager.forum.flag_message', ['{rulesets}' => $rulesStr]);
            $messages[] = is_array($trans) ? $trans[0] : $trans;
        }

        foreach ($customMessages as $customMsg) {
            $messages[] = $customMsg;
        }

        if ($isEvasion) {
            $messages[] = "Suspicious: User was recently blocked by ruleset '{$blockedRulesetName}', then successfully submitted this post. Please review for filter evasion.";
        }

        $reasonDetail = implode("\n\n", $messages);

        if ($shouldApprove) {
            $post->is_approved = false;
        }

        $post->afterSave(function ($post) use ($shouldApprove, $shouldFlag, $reasonDetail, $flagType) {
            if ($shouldApprove && $post->number == 1 && $post->discussion) {
                $post->discussion->is_approved = false;
                $post->discussion->save();
            }

            if ($shouldFlag && class_exists(\Flarum\Flags\Flag::class)) {
                $flag = new \Flarum\Flags\Flag();
                $flag->post_id = $post->id;
                $flag->type = $flagType;
                $flag->reason_detail = $reasonDetail;
                $flag->created_at = Carbon::now();
                $flag->save();
            }
        });
    }
}
