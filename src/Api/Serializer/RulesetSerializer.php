<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Api\Serializer;

use Flarum\Api\Serializer\AbstractSerializer;

use Huoxin\FilterRuleManager\Model\Ruleset;

/**
 * Rules are inlined as a JSON attribute rather than exposed as a JSON:API
 * relationship. This keeps the wire format symmetric with what the admin
 * editor sends back, so a round-trip (load → edit → save → reopen) preserves
 * the rules without relying on the Flarum store to reconcile relationship
 * data with the optimistic save body.
 */
class RulesetSerializer extends AbstractSerializer
{
    protected $type = 'filter-rule-rulesets';

    /**
     * @param Ruleset $model
     */
    protected function getDefaultAttributes($model): array
    {
        return [
            'name'          => $model->name,
            'priority'      => (int) $model->priority,
            'expression'    => $model->expression,
            'compiledAst'   => $model->compiled_ast ?? [],
            'interventionType'    => $model->intervention_type,
            'displayMode'      => $model->display_mode,
            'message'          => $model->message,
            'flagMessage'      => $model->flag_message,
            'evaluateAllRules' => (bool) $model->evaluate_all_rules,
            'evaluateTitle'    => $model->evaluate_title === null ? null : (bool) $model->evaluate_title,
            'evasionActive'    => $model->evasion_active === null ? null : (bool) $model->evasion_active,
            'evasionTimeout'   => $model->evasion_timeout === null ? null : (int) $model->evasion_timeout,
            'evasionThreshold' => $model->evasion_threshold === null ? null : (int) $model->evasion_threshold,
            'blockCascade'     => (bool) $model->block_cascade,
            'isActive'      => (bool) $model->is_active,
            'autoFlag'      => $model->auto_flag === null ? null : (bool) $model->auto_flag,
            'requireApproval' => $model->require_approval === null ? null : (bool) $model->require_approval,
            'scopeType'     => $model->scope_type,
            'scopeTagIds'   => $model->scope_tag_ids ?? [],
            'groupIds'      => $model->group_ids ?? [],
            'displaySettings' => $model->display_settings ?? [],

            'createdAt'     => $this->formatDate($model->created_at),
            'updatedAt'     => $this->formatDate($model->updated_at),
        ];
    }
}
