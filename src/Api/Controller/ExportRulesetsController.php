<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Api\Controller;

use Flarum\Group\Group;
use Flarum\Http\RequestUtil;
use Flarum\Tags\Tag;
use Huoxin\FilterRuleManager\Model\Ruleset;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ExportRulesetsController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $id = Arr::get($request->getQueryParams(), 'id');

        $query = Ruleset::query();

        if ($id) {
            $query->where('id', $id);
        }

        $rulesets = $query->get();

        // Bulk load all required tags and groupsp
        $allTagIds = [];
        $allGroupIds = [];
        foreach ($rulesets as $ruleset) {
            if (! empty($ruleset->scope_tag_ids)) {
                $allTagIds = array_merge($allTagIds, $ruleset->scope_tag_ids);
            }
            if (! empty($ruleset->bypass_group_ids)) {
                $allGroupIds = array_merge($allGroupIds, $ruleset->bypass_group_ids);
            }
        }

        $tagMap = [];
        if (! empty($allTagIds) && class_exists(Tag::class)) {
            $tagMap = Tag::whereIn('id', array_unique($allTagIds))->pluck('slug', 'id')->toArray();
        }

        $groupMap = [];
        if (! empty($allGroupIds)) {
            $groupMap = Group::whereIn('id', array_unique($allGroupIds))->pluck('name_singular', 'id')->toArray();
        }

        $export = $rulesets->map(function (Ruleset $ruleset) use ($tagMap, $groupMap) {
            $data = $ruleset->toArray();

            // Strip database-specific fields
            unset($data['id']);
            unset($data['created_at']);
            unset($data['updated_at']);

            // Map scope_tag_ids to slugs
            $data['scope_tags'] = [];
            if (! empty($data['scope_tag_ids'])) {
                foreach ($data['scope_tag_ids'] as $id) {
                    if (isset($tagMap[$id])) {
                        $data['scope_tags'][] = $tagMap[$id];
                    }
                }
            }
            unset($data['scope_tag_ids']);

            // Map bypass_group_ids to names
            $data['bypass_groups'] = [];
            if (! empty($data['bypass_group_ids'])) {
                foreach ($data['bypass_group_ids'] as $id) {
                    if (isset($groupMap[$id])) {
                        $data['bypass_groups'][] = $groupMap[$id];
                    }
                }
            }
            unset($data['bypass_group_ids']);

            return $data;
        })->toArray();

        return new JsonResponse($export);
    }
}
