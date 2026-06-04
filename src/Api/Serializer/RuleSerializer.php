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
use Huoxin\FilterRuleManager\Model\Rule;

class RuleSerializer extends AbstractSerializer
{
    protected $type = 'filter-rule-rules';

    /**
     * @param Rule $model
     */
    protected function getDefaultAttributes($model): array
    {
        return [
            'rulesetId' => (int) $model->ruleset_id,
            'provider'  => $model->provider,
            'type'      => $model->type,
            'config'    => $model->config ?? [],
            'negate'    => (bool) $model->negate,
            'sortOrder' => (int) $model->sort_order,
        ];
    }
}
