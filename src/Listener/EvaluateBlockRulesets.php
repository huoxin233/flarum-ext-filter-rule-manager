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
use Flarum\Post\Event\Saving;
use Flarum\Post\Exception\FloodingException;
use Flarum\Settings\SettingsRepositoryInterface;
use Huoxin\FilterRuleManager\Exception\RuleBlockException;
use Huoxin\FilterRuleManager\Model\FilterBlockLog;
use Huoxin\FilterRuleManager\Repository\RulesetRepository;
use Huoxin\FilterRuleManager\Service\RuleEvaluator;
use Huoxin\FilterRuleManager\Service\RulesetMatcher;

class EvaluateBlockRulesets
{
    public function __construct(
        protected RuleEvaluator $evaluator,
        protected RulesetMatcher $matcher,
        protected SettingsRepositoryInterface $settings,
        protected RulesetRepository $rulesets
    ) {
    }

    public function handle(Saving $event): void
    {
        $post = $event->post;

        // Evaluate new posts, and existing posts only if their content was modified.
        // This closes the edit loophole while preventing blocking on delete/recover actions.
        if ($post->exists && ! $post->isDirty('content')) {
            return;
        }

        $content = (string) $post->content;
        $discussion = $post->discussion;
        $title = $discussion ? (string) $discussion->title : '';

        $rulesets = $this->rulesets->getActiveRulesets();

        $providers = $this->evaluator->getProviders();

        $triggered = [];

        foreach ($rulesets as $ruleset) {
            $tokens = $this->matcher->match($ruleset, $post, $event->actor, $providers);
            if ($tokens === null) {
                continue;
            }

            if ($ruleset->intervention_type === 'block') {
                $targetContent = $this->matcher->getTargetContent($ruleset, $post, $discussion);

                $triggered[] = [
                    'ruleset_id' => $ruleset->id,
                    'display_mode' => $ruleset->display_mode,
                    'intervention_type' => 'block',
                    'message' => $this->evaluator->interpolate($ruleset->message, $tokens),
                    'tokens' => $tokens,
                    'content' => $targetContent,
                    'display_settings' => $ruleset->display_settings,
                ];
            }

            if ($ruleset->block_cascade) {
                break;
            }
        }

        if (! empty($triggered)) {
            $actor = $event->actor;
            if ($actor && ! $actor->isGuest()) {
                $now = Carbon::now();

                $lastBlockTime = FilterBlockLog::where('user_id', $actor->id)
                    ->orderBy('created_at', 'desc')
                    ->value('created_at');

                if ($lastBlockTime && Carbon::parse($lastBlockTime)->diffInSeconds($now, true) < 10) {
                    throw new FloodingException();
                }

                $nowStr = $now->toDateTimeString();
                $rows = array_map(fn ($t) => [
                    'user_id' => $actor->id,
                    'ruleset_id' => $t['ruleset_id'],
                    'content' => $t['content'],
                    'message' => $t['message'],
                    'tokens' => json_encode($t['tokens']),
                    'created_at' => $nowStr,
                ], $triggered);

                FilterBlockLog::insert($rows);
            }

            throw new RuleBlockException($triggered);
        }
    }
}
