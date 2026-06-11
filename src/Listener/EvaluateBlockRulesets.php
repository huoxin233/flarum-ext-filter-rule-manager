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
use Huoxin\FilterRuleManager\Model\Ruleset;
use Huoxin\FilterRuleManager\Service\RuleEvaluator;
use Flarum\Post\Exception\FloodingException;
use Illuminate\Database\ConnectionInterface;
use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;

class EvaluateBlockRulesets
{
    public function __construct(
        protected RuleEvaluator $evaluator,
        protected ConnectionInterface $db,
        protected SettingsRepositoryInterface $settings
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
        
        $isFirstPost = false;
        if ($discussion) {
            $isFirstPost = $post->number === 1 
                || $discussion->first_post_id === $post->id 
                || $discussion->first_post_id === null;
        }

        /** @var Ruleset[] $rulesets */
        $rulesets = Ruleset::active()->block()->ordered()->get();

        $providers = $this->evaluator->getProviders();

        $triggered = [];

        foreach ($rulesets as $ruleset) {
            if (! $this->evaluator->scopeMatches($ruleset, $discussion)) {
                continue;
            }

            $targetContent = $content;

            $evaluateTitle = $ruleset->evaluate_title ?? (bool) $this->settings->get('huoxin-filter.global_evaluate_title', true);

            if ($evaluateTitle && $title !== '' && $isFirstPost) {
                $targetContent = $title . "\n\n" . $content;
            }

            $tokens = $this->evaluator->evaluateRuleset($ruleset, $targetContent, $providers);
            if ($tokens === null) {
                continue;
            }

            $triggered[] = [
                'ruleset_id' => $ruleset->id,
                'display_mode' => $ruleset->display_mode,
                'effect_type' => 'block',
                'message' => $this->evaluator->interpolate($ruleset->message, $tokens),
                'tokens' => $tokens,
                'content' => $targetContent,
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
                    'content' => $t['content'],
                    'message' => $t['message'],
                    'tokens' => json_encode($t['tokens']),
                    'created_at' => Carbon::now(),
                ], $triggered);
                $this->db->table('filter_rule_block_logs')->insert($logs);
            }
            throw new RuleBlockException($triggered);
        }
    }
}
