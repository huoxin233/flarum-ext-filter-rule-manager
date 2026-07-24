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
use Flarum\Discussion\Event\Saving as DiscussionSaving;
use Flarum\Post\Event\Saving as PostSaving;
use Flarum\Post\Exception\FloodingException;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Huoxin\FilterRuleManager\Exception\RuleBlockException;
use Huoxin\FilterRuleManager\Model\FilterBlockLog;
use Huoxin\FilterRuleManager\Model\Ruleset;
use Huoxin\FilterRuleManager\Repository\RulesetRepository;
use Huoxin\FilterRuleManager\Service\RuleEvaluator;
use Huoxin\FilterRuleManager\Service\RulesetMatcher;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Collection;

class EvaluateBlockRulesets
{
    public function __construct(
        protected RuleEvaluator $evaluator,
        protected RulesetMatcher $matcher,
        protected SettingsRepositoryInterface $settings,
        protected RulesetRepository $rulesets
    ) {
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(PostSaving::class, [$this, 'handlePost']);
        $events->listen(DiscussionSaving::class, [$this, 'handleDiscussion']);
    }

    public function handlePost(PostSaving $event): void
    {
        $post = $event->post;

        // Evaluate new posts, and existing posts only if their content was modified.
        // This closes the edit loophole while preventing blocking on delete/recover actions.
        if ($post->exists && ! $post->isDirty('content')) {
            return;
        }

        $onlyField = $post->exists ? 'content' : null;
        $this->evaluate($post, $event->actor, $onlyField);
    }

    public function handleDiscussion(DiscussionSaving $event): void
    {
        $discussion = $event->discussion;

        // Only evaluate if the discussion already exists and its title was modified.
        // New discussions are handled by handlePost because their first post is also saved.
        if ($discussion->exists && $discussion->isDirty('title')) {
            $firstPost = $discussion->firstPost;
            if ($firstPost) {
                $firstPost->setRelation('discussion', $discussion);
                $this->evaluate($firstPost, $event->actor, 'title');
            }
        }
    }

    /**
     * @param Post $post
     * @param User|null $actor
     * @param string|null $onlyField
     */
    private function evaluate($post, $actor, ?string $onlyField = null): void
    {
        $content = (string) $post->content;
        $discussion = $post->discussion;
        $title = $discussion ? (string) $discussion->title : '';

        /** @var Collection<int, Ruleset> $rulesets */
        $rulesets = $this->rulesets->getActiveRulesets();

        $providers = $this->evaluator->getProviders();

        $triggered = [];

        foreach ($rulesets as $ruleset) {
            // Optimization: If the ruleset isn't a block and doesn't break the cascade,
            // it has zero effect in this listener. Skip it immediately to save AST computation.
            if ($ruleset->intervention_type !== 'block' && ! $ruleset->block_cascade) {
                continue;
            }

            $tokens = $this->matcher->match($ruleset, $post, $actor, $providers, false, $onlyField);
            if ($tokens === null) {
                continue;
            }

            $strictEdit = $ruleset->strict_edit ?? (bool) $this->settings->get('huoxin-filter-rule-manager.strict_edit_evaluation', false);

            if ($post->exists && ! $strictEdit) {
                $oldTokens = $this->matcher->match($ruleset, $post, $actor, $providers, true, $onlyField);

                if ($oldTokens !== null && $oldTokens === $tokens) {
                    continue; // Violation already existed prior to this edit.
                }
            }

            if ($ruleset->intervention_type === 'block') {
                $targetContent = $this->matcher->getTargetContent($ruleset, $post, $discussion, false, $onlyField);

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
