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
use Huoxin\FilterRuleManager\Model\Ruleset;

class RulesetMatcher
{
    public function __construct(
        protected RuleEvaluator $evaluator,
        protected SettingsRepositoryInterface $settings
    ) {
    }

    /**
     * Evaluates a ruleset against a post, automatically handling scope matching,
     * bypass checking, first-post title prepending, and provider injection.
     *
     * @return array|null Returns matched tokens if the ruleset triggers, null otherwise.
     */
    public function match(Ruleset $ruleset, Post $post, ?\Flarum\User\User $actor = null, ?array $providers = null): ?array
    {
        // Temporarily removed until Flarum natively supports a "Nobody" permission
        // if ($actor && $actor->can('huoxin-filter-rule-manager.bypassAllRules')) {
        //     return null;
        // }

        if ($actor && is_array($ruleset->bypass_group_ids) && count($ruleset->bypass_group_ids) > 0) {
            $userGroups = $actor->groups->pluck('id')->toArray();
            if (count(array_intersect($userGroups, $ruleset->bypass_group_ids)) > 0) {
                return null;
            }
        }

        $discussion = $post->discussion;

        if (! $this->evaluator->scopeMatches($ruleset, $discussion)) {
            return null;
        }

        $content = (string) $post->content;
        $title = $discussion ? (string) $discussion->title : '';

        $isFirstPost = false;
        if ($discussion) {
            $isFirstPost = $post->number === 1
                || $discussion->first_post_id === $post->id
                || $discussion->first_post_id === null;
        }

        $targetContent = $content;
        $evaluateTitle = $ruleset->evaluate_title ?? (bool) $this->settings->get('huoxin-filter.global_evaluate_title', true);

        if ($evaluateTitle && $title !== '' && $isFirstPost) {
            $targetContent = $title."\n\n".$content;
        }

        if ($providers === null) {
            $providers = $this->evaluator->getProviders();
        }

        return $this->evaluator->evaluateRuleset($ruleset, $targetContent, $providers);
    }
}
