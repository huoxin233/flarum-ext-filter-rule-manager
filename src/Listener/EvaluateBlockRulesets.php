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

use Flarum\Post\Event\Saving;
use Huoxin\FilterRuleManager\Exception\RuleBlockException;
use Huoxin\FilterRuleManager\Extend\FilterRuleProvider;
use Huoxin\FilterRuleManager\Model\Rule;
use Huoxin\FilterRuleManager\Model\Ruleset;
use Huoxin\FilterRuleManager\Provider\RuleProviderInterface;
use Huoxin\FilterRuleManager\Service\RuleEvaluator;
use Flarum\Post\Exception\FloodingException;
use Illuminate\Database\ConnectionInterface;
use Carbon\Carbon;

class EvaluateBlockRulesets
{
    public function __construct(
        protected RuleEvaluator $evaluator,
        protected ConnectionInterface $db
    ) {
    }

    public function handle(Saving $event): void
    {
        $post = $event->post;

        // Only run on new post creation. The plan's submit flow only covers
        // first-time submission; blocking on edits would surprise moderators
        // fixing typos in old content under newer rules.
        if ($post->exists) {
            return;
        }

        $content = (string) $post->content;
        $discussion = $post->discussion;

        /** @var Ruleset[] $rulesets */
        $rulesets = Ruleset::active()->block()->ordered()->with('rules')->get();

        $providers = $this->evaluator->getProviders();

        $triggered = [];

        foreach ($rulesets as $ruleset) {
            if (! $this->evaluator->scopeMatches($ruleset, $discussion)) {
                continue;
            }

            $tokens = $this->evaluator->evaluateRuleset($ruleset, $content, $providers);
            if ($tokens === null) {
                continue;
            }

            $triggered[] = [
                'ruleset_id' => $ruleset->id,
                'display_mode' => $ruleset->display_mode,
                'effect_type' => 'block',
                'message' => $this->evaluator->interpolate($ruleset->message, $tokens),
                'tokens' => $tokens,
            ];

            if ($ruleset->block_cascade) {
                break;
            }
        }

        if (! empty($triggered)) {
            $actor = $event->actor;
            if ($actor && ! $actor->isGuest()) {
                $lastBlock = $this->db->table('filter_rule_block_logs')
                    ->where('user_id', $actor->id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($lastBlock && Carbon::parse($lastBlock->created_at)->diffInSeconds(Carbon::now()) < 10) {
                    throw new FloodingException();
                }

                $logs = array_map(fn ($t) => [
                    'user_id' => $actor->id,
                    'ruleset_id' => $t['ruleset_id'],
                    'created_at' => Carbon::now(),
                ], $triggered);
                $this->db->table('filter_rule_block_logs')->insert($logs);
            }
            throw new RuleBlockException($triggered);
        }
    }
}
