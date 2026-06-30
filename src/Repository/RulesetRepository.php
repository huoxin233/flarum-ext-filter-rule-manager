<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Repository;

use Huoxin\FilterRuleManager\Model\Ruleset;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Collection;

class RulesetRepository
{
    /**
     * @var Collection|null
     */
    protected $activeRulesets;

    public function __construct(
        protected ConnectionInterface $db,
        protected CacheRepository $cache
    ) {
    }

    public function getActiveRulesets()
    {
        if ($this->activeRulesets === null) {
            $this->activeRulesets = Ruleset::active()->ordered()->get();
        }

        return $this->activeRulesets;
    }

    /**
     * Retrieves a globally cached array of all frontend rulesets.
     * The cache is invalidated automatically when rulesets are created, updated, or deleted.
     *
     * @return array
     */
    public function getActiveFrontendRulesetsArray(): array
    {
        return $this->cache->rememberForever('huoxin-filter-rule-manager.frontend_rulesets', function () {
            return Ruleset::active()
                ->frontend()
                ->ordered()
                ->get()
                ->map(fn (Ruleset $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'priority' => $r->priority,
                    'compiled_ast' => $r->compiled_ast,
                    'interventionType' => $r->intervention_type,
                    'evaluateAllRules' => $r->evaluate_all_rules,
                    'displayMode' => $r->display_mode,
                    'message' => $r->message,
                    'evaluateTitle' => $r->evaluate_title === null ? null : (bool) $r->evaluate_title,
                    'blockCascade' => $r->block_cascade,
                    'scopeType' => $r->scope_type,
                    'scopeTagIds' => $r->scope_tag_ids ?? [],
                    'displaySettings' => $r->display_settings,
                    'bypass_group_ids' => $r->bypass_group_ids,
                ])
                ->toArray();
        });
    }

    public function flush(): void
    {
        $this->activeRulesets = null;
        $this->cache->forget('huoxin-filter-rule-manager.frontend_rulesets');
    }

    public function reorder(array $values): void
    {
        $this->db->transaction(function () use ($values) {
            Ruleset::upsert($values, ['id'], ['priority']);
        });

        $this->flush();
    }
}
