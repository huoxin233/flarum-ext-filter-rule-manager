<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Service;

use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Huoxin\FilterRuleManager\Model\EvaluationContext;
use Huoxin\FilterRuleManager\Model\Ruleset;
use WeakMap;

class RulesetMatcher
{
    /**
     * @var WeakMap<\Flarum\Post\Post, array<string, array|null>>
     */
    protected WeakMap $evaluationCache;

    /**
     * @var WeakMap<\Flarum\User\User, array<int>>
     */
    protected WeakMap $userGroupsCache;

    public function __construct(
        protected RuleEvaluator $evaluator,
        protected SettingsRepositoryInterface $settings
    ) {
        $this->evaluationCache = new WeakMap();
        $this->userGroupsCache = new WeakMap();
    }

    /**
     * Evaluates a ruleset against a post, automatically handling scope matching,
     * bypass checking, first-post title prepending, and provider injection.
     *
     * @return array|null Returns matched tokens if the ruleset triggers, null otherwise.
     */
    public function match(Ruleset $ruleset, Post $post, ?\Flarum\User\User $actor = null, ?array $providers = null): ?array
    {
        if (! isset($this->evaluationCache[$post])) {
            $this->evaluationCache[$post] = [];
        }

        $actorId = $actor ? $actor->id : 0;
        $cacheKey = $ruleset->id.'_'.$actorId;
        $postCache = $this->evaluationCache[$post];

        if (array_key_exists($cacheKey, $postCache)) {
            return $postCache[$cacheKey];
        }

        // Temporarily removed until Flarum natively supports a "Nobody" permission
        // if ($actor && $actor->can('huoxin-filter-rule-manager.bypassAllRules')) {
        //     return null;
        // }

        if ($actor && ! empty($ruleset->bypass_group_ids)) {
            if (! isset($this->userGroupsCache[$actor])) {
                $this->userGroupsCache[$actor] = $actor->groups->pluck('id')->toArray();
            }

            $userGroups = $this->userGroupsCache[$actor];

            if (count(array_intersect($userGroups, $ruleset->bypass_group_ids)) > 0) {
                $postCache[$cacheKey] = null;
                $this->evaluationCache[$post] = $postCache;

                return null;
            }
        }

        $discussion = $post->discussion;

        if (! $this->evaluator->scopeMatches($ruleset, $discussion)) {
            $postCache[$cacheKey] = null;
            $this->evaluationCache[$post] = $postCache;

            return null;
        }

        $targetContent = $this->getTargetContent($ruleset, $post, $discussion);

        if ($providers === null) {
            $providers = $this->evaluator->getProviders();
        }

        $context = new EvaluationContext($targetContent, $actor, $post);

        $result = $this->evaluator->evaluateRuleset($ruleset, $context, $providers);

        $postCache[$cacheKey] = $result;
        $this->evaluationCache[$post] = $postCache;

        return $result;
    }

    public function getTargetContent(Ruleset $ruleset, Post $post, $discussion = null): string
    {
        if ($discussion === null) {
            $discussion = $post->discussion;
        }

        $content = (string) $post->content;
        $title = $discussion ? (string) $discussion->title : '';

        $isFirstPost = false;
        if ($discussion) {
            $isFirstPost = $post->number === 1
                || $discussion->first_post_id === $post->id
                || $discussion->first_post_id === null;
        }

        $evaluateTitle = $ruleset->evaluate_title ?? (bool) $this->settings->get('huoxin-filter-rule-manager.global_evaluate_title', true);

        if ($evaluateTitle && $title !== '' && $isFirstPost) {
            return $title."\n\n".$content;
        }

        return $content;
    }
}
