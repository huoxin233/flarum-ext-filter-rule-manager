<?php

namespace Huoxin\FilterRuleManager\Listener;

use Carbon\Carbon;
use Flarum\Extension\ExtensionManager;
use Flarum\Flags\Flag;
use Flarum\Post\Event\Saving;
use Flarum\Settings\SettingsRepositoryInterface;
use Huoxin\FilterRuleManager\Model\FilterBlockLog;
use Huoxin\FilterRuleManager\Model\Ruleset;
use Huoxin\FilterRuleManager\Service\RuleEvaluator;
use Huoxin\FilterRuleManager\Service\RulesetMatcher;
use Illuminate\Contracts\Events\Dispatcher;
use Symfony\Contracts\Translation\TranslatorInterface;

class ExecuteModerationActions
{
    public function __construct(
        protected RuleEvaluator $evaluator,
        protected RulesetMatcher $matcher,
        protected ExtensionManager $extensions,
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
        if ($post->exists && $post->isDirty('is_approved') && $post->is_approved && $post->user_id) {
            FilterBlockLog::where('user_id', $post->user_id)
                ->update(['is_cleared' => true]);
        }

        // Only evaluate if this is a new post or the content was edited.
        // This prevents re-evaluating during delete, recover, or approval actions.
        if ($post->exists && ! $post->isDirty('content')) {
            return;
        }

        $globalAutoFlag = (bool) $this->settings->get('huoxin-filter.global_auto_flag', true);
        $globalRequireApproval = (bool) $this->settings->get('huoxin-filter.global_require_approval', true);
        $globalEvasionActive = (bool) $this->settings->get('huoxin-filter.global_evasion_active', false);
        $globalEvasionTimeout = (int) $this->settings->get('huoxin-filter.global_evasion_timeout', 5);
        $globalEvasionThreshold = (int) $this->settings->get('huoxin-filter.global_evasion_threshold', 2);

        // Load all active rulesets once from in-memory cache, filter per concern.
        $allActive = Ruleset::getActiveRulesets();

        $rulesets = $allActive->filter(function (Ruleset $ruleset) use ($globalAutoFlag, $globalRequireApproval) {
            return ($ruleset->auto_flag ?? $globalAutoFlag) || ($ruleset->require_approval ?? $globalRequireApproval);
        });

        $defaultRulesets = [];
        $customMessages = [];
        $providers = $this->evaluator->getProviders();
        $requiresApproval = false;
        $requiresFlag = false;

        foreach ($rulesets as $ruleset) {
            $tokens = $this->matcher->match($ruleset, $post, $event->actor, $providers);
            if ($tokens !== null) {
                if (! empty($ruleset->flag_message)) {
                    $customMessages[] = $this->evaluator->interpolate($ruleset->flag_message, $tokens);
                } else {
                    $defaultRulesets[] = $ruleset->name;
                }

                $autoFlag = $ruleset->auto_flag ?? $globalAutoFlag;
                $requireApproval = $ruleset->require_approval ?? $globalRequireApproval;

                if ($requireApproval) {
                    $requiresApproval = true;
                }
                if ($autoFlag) {
                    $requiresFlag = true;
                }
            }
        }

        $actor = $event->actor;
        $isEvasion = false;
        $blockedRulesetName = null;

        if ($actor && ! $actor->isGuest()) {
            $evasionRulesets = $allActive->filter(function (Ruleset $ruleset) use ($globalEvasionActive) {
                return $ruleset->evasion_active ?? $globalEvasionActive;
            })->keyBy('id');

            if ($evasionRulesets->isNotEmpty()) {
                $maxTimeout = 0;
                foreach ($evasionRulesets as $ruleset) {
                    $t = $ruleset->evasion_timeout ?? $globalEvasionTimeout;
                    if ($t > $maxTimeout) {
                        $maxTimeout = $t;
                    }
                }

                if ($maxTimeout > 0) {
                    // Fetch only ruleset_id and created_at to avoid memory bloat from longText columns
                    $recentLogs = FilterBlockLog::where('user_id', $actor->id)
                        ->where('is_cleared', false)
                        ->whereIn('ruleset_id', $evasionRulesets->keys())
                        ->where('created_at', '>=', Carbon::now()->subMinutes($maxTimeout))
                        ->select('ruleset_id', 'created_at')
                        ->get();

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
            if ($hasFlags) {
                if (Flag::where('post_id', $post->id)->where('type', $flagType)->exists()) {
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
            $trans = $this->translator->trans('huoxin-filter-rule-manager.forum.evasion_flag_message', ['{ruleset}' => $blockedRulesetName ?? '']);
            $messages[] = is_array($trans) ? $trans[0] : $trans;
        }

        $reasonDetail = implode("\n\n", $messages);
        $reasonDetail = html_entity_decode($reasonDetail, ENT_QUOTES, 'UTF-8');

        if ($shouldApprove) {
            $post->is_approved = false;
        }

        $post->afterSave(function ($post) use ($shouldApprove, $shouldFlag, $reasonDetail, $flagType) {
            if ($shouldApprove && $post->number == 1 && $post->discussion) {
                $post->discussion->is_approved = false;
                $post->discussion->save();
            }

            if ($shouldFlag) {
                $flag = new Flag();
                $flag->post_id = $post->id;
                $flag->type = $flagType;
                $flag->reason_detail = $reasonDetail;
                $flag->created_at = Carbon::now();
                $flag->save();
            }
        });
    }
}
