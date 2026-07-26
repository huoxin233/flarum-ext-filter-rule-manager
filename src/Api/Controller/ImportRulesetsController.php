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

class ImportRulesetsController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $body = $request->getParsedBody();
        $rulesetsData = Arr::get($body, 'rulesets');
        $mode = Arr::get($body, 'mode', 'append');
        $preservePriority = (bool) Arr::get($body, 'preserve_priority', false);

        if (! is_array($rulesetsData)) {
            return new JsonResponse(['errors' => [['detail' => 'Invalid rulesets payload']]], 422);
        }

        $allTagSlugs = [];
        $allGroupNames = [];

        foreach ($rulesetsData as $data) {
            if (empty($data['name']) || empty($data['expression'])) {
                return new JsonResponse(['errors' => [['detail' => 'Rulesets must have a name and expression']]], 422);
            }
            if (! empty($data['scope_tags']) && is_array($data['scope_tags'])) {
                $allTagSlugs = array_merge($allTagSlugs, $data['scope_tags']);
            }
            if (! empty($data['bypass_groups']) && is_array($data['bypass_groups'])) {
                $allGroupNames = array_merge($allGroupNames, $data['bypass_groups']);
            }
        }

        // Bulk load Tags and Groups mapping before locking the database
        $tagMap = [];
        if (! empty($allTagSlugs) && class_exists(Tag::class)) {
            $tagMap = Tag::whereIn('slug', array_unique($allTagSlugs))->pluck('id', 'slug')->toArray();
        }

        $groupMap = [];
        if (! empty($allGroupNames)) {
            $groupMap = Group::whereIn('name_singular', array_unique($allGroupNames))->pluck('id', 'name_singular')->toArray();
        }

        Ruleset::query()->getConnection()->transaction(function () use ($rulesetsData, $mode, $preservePriority, $tagMap, $groupMap) {
            if ($mode === 'override') {
                Ruleset::query()->delete();
            }

            $currentMaxPriority = Ruleset::query()->max('priority') ?? 0;

            foreach ($rulesetsData as $data) {
                $ruleset = new Ruleset();

                // Map slugs back to scope_tag_ids
                $tagIds = [];
                if (! empty($data['scope_tags']) && is_array($data['scope_tags'])) {
                    foreach ($data['scope_tags'] as $slug) {
                        if (isset($tagMap[$slug])) {
                            $tagIds[] = $tagMap[$slug];
                        }
                    }
                }
                $ruleset->scope_tag_ids = empty($tagIds) ? null : $tagIds;

                // Map names back to bypass_group_ids
                $groupIds = [];
                if (! empty($data['bypass_groups']) && is_array($data['bypass_groups'])) {
                    foreach ($data['bypass_groups'] as $name) {
                        if (isset($groupMap[$name])) {
                            $groupIds[] = $groupMap[$name];
                        }
                    }
                }
                $ruleset->bypass_group_ids = empty($groupIds) ? null : $groupIds;

                unset($data['scope_tags']);
                unset($data['bypass_groups']);
                unset($data['scope_tag_ids']);
                unset($data['bypass_group_ids']);

                // Fill other attributes safely
                foreach ($data as $key => $value) {
                    // Prevent primary key or timestamps from being accidentally imported if present
                    if (in_array($key, ['id', 'created_at', 'updated_at'])) {
                        continue;
                    }
                    if ($key === 'priority' && ! $preservePriority) {
                        continue;
                    }
                    $ruleset->{$key} = $value;
                }

                if (! $preservePriority || ! isset($data['priority'])) {
                    $currentMaxPriority++;
                    $ruleset->priority = $currentMaxPriority;
                } else {
                    $ruleset->priority = $data['priority'];
                    if ($ruleset->priority > $currentMaxPriority) {
                        $currentMaxPriority = $ruleset->priority;
                    }
                }

                $ruleset->save();
            }
        });

        return new JsonResponse(['success' => true]);
    }
}
