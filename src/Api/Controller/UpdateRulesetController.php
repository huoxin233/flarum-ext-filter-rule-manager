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

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Http\RequestUtil;
use Huoxin\FilterRuleManager\Api\Serializer\RulesetSerializer;
use Huoxin\FilterRuleManager\Model\Rule;
use Huoxin\FilterRuleManager\Model\Ruleset;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;
use Tobscure\JsonApi\Exception\InvalidParameterException;

class UpdateRulesetController extends AbstractShowController
{
    public $serializer = RulesetSerializer::class;

    protected function data(ServerRequestInterface $request, Document $document): Ruleset
    {
        RequestUtil::getActor($request)->assertAdmin();

        $id         = Arr::get($request->getQueryParams(), 'id');
        $body       = $request->getParsedBody();
        $attributes = $body['data']['attributes'] ?? [];

        $ruleset = Ruleset::findOrFail($id);

        if (isset($attributes['name'])) {
            $name = trim((string) $attributes['name']);
            if ($name === '') {
                throw new InvalidParameterException('Ruleset name cannot be empty.');
            }
            $ruleset->name = $name;
        }

        if (isset($attributes['priority']))     $ruleset->priority      = (int) $attributes['priority'];
        if (isset($attributes['ruleOperator'])) $ruleset->rule_operator = $this->validEnum($attributes['ruleOperator'], ['AND', 'OR'], $ruleset->rule_operator);
        if (isset($attributes['effectType']))   $ruleset->effect_type   = $this->validEnum($attributes['effectType'], ['info', 'warning', 'block'], $ruleset->effect_type);
        if (isset($attributes['displayMode']))  $ruleset->display_mode  = $this->validEnum($attributes['displayMode'], ['banner', 'header_banner', 'toast', 'modal', 'sidebar'], $ruleset->display_mode);
        if (isset($attributes['message']))      $ruleset->message       = (string) $attributes['message'];
        if (isset($attributes['blockCascade'])) $ruleset->block_cascade = (bool) $attributes['blockCascade'];
        if (isset($attributes['isActive']))     $ruleset->is_active     = (bool) $attributes['isActive'];
        if (isset($attributes['autoFlag']))     $ruleset->auto_flag     = (bool) $attributes['autoFlag'];
        if (isset($attributes['scopeType']))    $ruleset->scope_type    = $this->validEnum($attributes['scopeType'], ['global', 'normal_post', 'private_post', 'tag'], $ruleset->scope_type);
        if (array_key_exists('scopeTagIds', $attributes)) {
            $ruleset->scope_tag_ids = $this->sanitizeTagIds($attributes['scopeTagIds']);
        }

        $ruleset->save();

        // If a rules array is provided, replace rules entirely.
        if (array_key_exists('rules', $attributes)) {
            $ruleset->rules()->delete();
            foreach ($this->sanitizeRules($attributes['rules']) as $i => $ruleData) {
                $rule             = new Rule();
                $rule->ruleset_id = $ruleset->id;
                $rule->provider   = $ruleData['provider'];
                $rule->type       = $ruleData['type'];
                $rule->config     = $ruleData['config'];
                $rule->negate     = $ruleData['negate'];
                $rule->sort_order = $ruleData['sortOrder'] ?? $i;
                $rule->save();
            }
        }

        return $ruleset->load('rules');
    }

    /**
     * @return list<array{provider:string, type:string, config:array, negate:bool, sortOrder?:int}>
     */
    private function sanitizeRules($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $clean = [];
        foreach ($raw as $r) {
            if (!is_array($r)) continue;
            $provider = trim((string) ($r['provider'] ?? ''));
            $type     = trim((string) ($r['type'] ?? ''));
            if ($provider === '' || $type === '') continue;

            $clean[] = [
                'provider'  => $provider,
                'type'      => $type,
                'config'    => is_array($r['config'] ?? null) ? $r['config'] : [],
                'negate'    => (bool) ($r['negate'] ?? false),
                'sortOrder' => isset($r['sortOrder']) ? (int) $r['sortOrder'] : null,
            ];
        }

        return $clean;
    }

    private function sanitizeTagIds($raw): ?array
    {
        if (!is_array($raw)) {
            return null;
        }
        $ids = array_values(array_filter(array_map('intval', $raw), fn ($id) => $id > 0));
        return $ids === [] ? null : $ids;
    }

    private function validEnum(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }
}
