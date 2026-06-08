<?php

namespace Huoxin\FilterRuleManager\Listener;

use Flarum\Extension\ExtensionManager;
use Flarum\Post\Event\Saved;
use Flarum\Post\Event\Saving;
use Huoxin\FilterRuleManager\Model\Ruleset;
use Huoxin\FilterRuleManager\Service\RuleEvaluator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Carbon\Carbon;
use Symfony\Contracts\Translation\TranslatorInterface;

class ExecuteModerationActions
{
    public function __construct(
        protected RuleEvaluator $evaluator,
        protected ExtensionManager $extensions,
        protected ConnectionInterface $db,
        protected TranslatorInterface $translator
    ) {
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Saving::class, [$this, 'moderatePost']);
    }

    public function moderatePost(Saving $event): void
    {
        $hasApproval = $this->extensions->isEnabled('flarum-approval');
        $hasFlags    = $this->extensions->isEnabled('flarum-flags');

        if (! $hasApproval && ! $hasFlags) {
            return;
        }

        $post = $event->post;

        // Only evaluate if this is a new post or the content was edited.
        // This prevents re-evaluating during delete, recover, or approval actions.
        if ($post->exists && ! $post->isDirty('content')) {
            return;
        }

        $content = (string) $post->content;
        $discussion = $post->discussion;
        $title = $discussion ? (string) $discussion->title : '';
        $isFirstPost = $post->number === 1 || $post->number === null;

        /** @var Ruleset[] $rulesets */
        $rulesets = Ruleset::active()
            ->where(function ($query) {
                $query->where('auto_flag', true)
                      ->orWhere('require_approval', true);
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
            if ($isFirstPost && $title !== '' && $ruleset->evaluate_title !== false) {
                $targetContent = $title . "\n\n" . $content;
            }

            $tokens = $this->evaluator->evaluateRuleset($ruleset, $targetContent, $providers);
            if ($tokens !== null) {
                if (!empty($ruleset->flag_message)) {
                    $customMessages[] = $this->evaluator->interpolate($ruleset->flag_message, $tokens);
                } else {
                    $defaultRulesets[] = $ruleset->name;
                }

                if ($ruleset->require_approval) $requiresApproval = true;
                if ($ruleset->auto_flag) $requiresFlag = true;

                if ($ruleset->block_cascade) {
                    $blockedRulesetName = $ruleset->name;
                }
            }
        }

        $actor = $event->actor;
        $isEvasion = false;

        if ($actor && ! $actor->isGuest()) {
            $recentBlock = $this->db->table('filter_rule_block_logs')
                ->where('user_id', $actor->id)
                ->where('created_at', '>=', Carbon::now()->subMinutes(15))
                ->orderBy('created_at', 'desc')
                ->first();

            if ($recentBlock) {
                $isEvasion = true;
                $blockedRuleset = Ruleset::find($recentBlock->ruleset_id);
                $blockedRulesetName = $blockedRuleset ? $blockedRuleset->name : 'Unknown';
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
