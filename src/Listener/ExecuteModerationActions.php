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
        $events->listen(Saving::class, [$this, 'requireApproval']);
        $events->listen(Saved::class, [$this, 'autoFlag']);
    }

    public function requireApproval(Saving $event): void
    {
        if (!$this->extensions->isEnabled('flarum-approval')) {
            return;
        }

        $post = $event->post;
        if ($post->exists) {
            return;
        }

        $content = (string) $post->content;
        
        /** @var Ruleset[] $rulesets */
        $rulesets = Ruleset::active()->where('require_approval', true)->ordered()->with('rules')->get();
        if ($rulesets->isEmpty()) {
            return;
        }

        $providers = $this->evaluator->getProviders();

        foreach ($rulesets as $ruleset) {
            if (!$this->evaluator->scopeMatches($ruleset, $post->discussion)) {
                continue;
            }

            $tokens = $this->evaluator->evaluateRuleset($ruleset, $content, $providers);
            if ($tokens !== null) {
                $post->is_approved = false;
                break;
            }
        }
    }

    public function autoFlag(Saved $event): void
    {
        if (!$this->extensions->isEnabled('flarum-flags')) {
            return;
        }

        $post = $event->post;
        $content = (string) $post->content;

        $providers = $this->evaluator->getProviders();
        $triggeredRulesetNames = [];

        /** @var Ruleset[] $rulesets */
        $rulesets = Ruleset::active()->where('auto_flag', true)->ordered()->with('rules')->get();

        foreach ($rulesets as $ruleset) {
            if (!$this->evaluator->scopeMatches($ruleset, $post->discussion)) {
                continue;
            }

            $tokens = $this->evaluator->evaluateRuleset($ruleset, $content, $providers);
            if ($tokens !== null) {
                $triggeredRulesetNames[] = $ruleset->name;
            }
        }

        $actor = $event->actor;

        if (empty($triggeredRulesetNames)) {
            return;
        }

        $messages = [];
        
        $rulesStr = implode(', ', $triggeredRulesetNames);
        $trans = $this->translator->trans('huoxin-filter-rule-manager.forum.flag_message', ['{rulesets}' => $rulesStr]);
        $messages[] = is_array($trans) ? $trans[0] : $trans;

        $reasonDetail = implode("\n\n", $messages);

        if (class_exists(\Flarum\Flags\Flag::class)) {
            $flag = new \Flarum\Flags\Flag();
            $flag->post_id = $post->id;
            $flag->type = 'autoMod';
            $flag->reason_detail = $reasonDetail;
            $flag->created_at = Carbon::now();
            $flag->save();
        }
    }
}
