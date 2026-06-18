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
use Huoxin\FilterRuleManager\Expression\Lexer;
use Huoxin\FilterRuleManager\Expression\Parser;
use Huoxin\FilterRuleManager\Model\Ruleset;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;
use Tobscure\JsonApi\Exception\InvalidParameterException;

class UpdateRulesetController extends AbstractShowController
{
    use RulesetValidationTrait;

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

        if (array_key_exists('expression', $attributes)) {
            $expression = trim((string) $attributes['expression']);
            $ruleset->expression = $expression;

            if ($expression !== '') {
                try {
                    $lexer = new Lexer($expression);
                    $tokens = $lexer->tokenize();
                    $parser = new Parser($tokens);
                    $ast = $parser->parse();
                    $ruleset->compiled_ast = $ast->toArray();
                } catch (\Exception $e) {
                    throw new InvalidParameterException('Invalid expression syntax: ' . $e->getMessage());
                }
            } else {
                $ruleset->compiled_ast = null;
            }
        }

        if (isset($attributes['interventionType']))   $ruleset->intervention_type   = $this->validEnum($attributes['interventionType'], ['info', 'warning', 'block', 'silent'], $ruleset->intervention_type);
        if (isset($attributes['displayMode']))  $ruleset->display_mode  = $this->validEnum($attributes['displayMode'], ['banner', 'header_banner', 'toast', 'modal', 'sidebar'], $ruleset->display_mode);
        if (isset($attributes['message']))      $ruleset->message       = (string) $attributes['message'];
        if (array_key_exists('flagMessage', $attributes)) $ruleset->flag_message = $attributes['flagMessage'] === null ? null : (string) $attributes['flagMessage'];
        if (isset($attributes['evaluateAllRules'])) $ruleset->evaluate_all_rules = (bool) $attributes['evaluateAllRules'];
        if (array_key_exists('evaluateTitle', $attributes)) $ruleset->evaluate_title = $attributes['evaluateTitle'] === null ? null : (bool) $attributes['evaluateTitle'];
        if (array_key_exists('evasionActive', $attributes)) $ruleset->evasion_active = $attributes['evasionActive'] === null ? null : (bool) $attributes['evasionActive'];
        if (array_key_exists('evasionTimeout', $attributes)) $ruleset->evasion_timeout = $attributes['evasionTimeout'] === null ? null : max(0, (int) $attributes['evasionTimeout']);
        if (array_key_exists('evasionThreshold', $attributes)) $ruleset->evasion_threshold = $attributes['evasionThreshold'] === null ? null : max(1, (int) $attributes['evasionThreshold']);
        if (isset($attributes['blockCascade'])) $ruleset->block_cascade = (bool) $attributes['blockCascade'];
        if (isset($attributes['isActive']))     $ruleset->is_active     = (bool) $attributes['isActive'];
        if (array_key_exists('autoFlag', $attributes)) $ruleset->auto_flag = $attributes['autoFlag'] === null ? null : (bool) $attributes['autoFlag'];
        if (array_key_exists('requireApproval', $attributes)) $ruleset->require_approval = $attributes['requireApproval'] === null ? null : (bool) $attributes['requireApproval'];
        if (isset($attributes['scopeType']))    $ruleset->scope_type    = $this->validEnum($attributes['scopeType'], ['global', 'normal_post', 'private_post', 'tag'], $ruleset->scope_type);
        if (array_key_exists('scopeTagIds', $attributes)) {
            $ruleset->scope_tag_ids = $this->sanitizeIds($attributes['scopeTagIds']);
        }
        if (array_key_exists('bypassGroupIds', $attributes)) {
            $ruleset->bypass_group_ids = $this->sanitizeIds($attributes['bypassGroupIds']);
        }
        if (array_key_exists('displaySettings', $attributes)) {
            $ruleset->display_settings = is_array($attributes['displaySettings']) ? $attributes['displaySettings'] : null;
        }

        $ruleset->save();

        return $ruleset;
    }
}
