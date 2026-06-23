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

use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Http\RequestUtil;
use Huoxin\FilterRuleManager\Api\Serializer\RulesetSerializer;
use Huoxin\FilterRuleManager\Expression\Lexer;
use Huoxin\FilterRuleManager\Expression\Parser;
use Huoxin\FilterRuleManager\Model\Ruleset;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;
use Tobscure\JsonApi\Exception\InvalidParameterException;

/**
 * @TODO: Remove this in favor of one of the API resource classes that were added.
 *      Or extend an existing API Resource to add this to.
 *      Or use a vanilla RequestHandlerInterface controller.
 *      @link https://docs.flarum.org/2.x/extend/api#endpoints
 */
class CreateRulesetController extends AbstractCreateController
{
    use RulesetValidationTrait;

    public $serializer = RulesetSerializer::class;

    protected function data(ServerRequestInterface $request, Document $document): Ruleset
    {
        RequestUtil::getActor($request)->assertAdmin();

        $body = $request->getParsedBody();
        $attributes = $body['data']['attributes'] ?? [];

        $name = trim((string) ($attributes['name'] ?? ''));
        if ($name === '') {
            throw new InvalidParameterException('Ruleset name is required.');
        }

        $ruleset = new Ruleset();
        $ruleset->name = $name;
        $ruleset->priority = ((int) Ruleset::max('priority')) + 10;

        $expression = trim((string) ($attributes['expression'] ?? ''));
        $ruleset->expression = $expression;

        if ($expression !== '') {
            try {
                $lexer = new Lexer($expression);
                $tokens = $lexer->tokenize();
                $parser = new Parser($tokens);
                $ast = $parser->parse();
                $ruleset->compiled_ast = $ast->toArray();
            } catch (\Exception $e) {
                throw new InvalidParameterException('Invalid expression syntax: '.$e->getMessage());
            }
        } else {
            $ruleset->compiled_ast = null;
        }

        $ruleset->intervention_type = $this->validEnum($attributes['interventionType'] ?? 'info', ['info', 'warning', 'block', 'silent'], 'info');
        $ruleset->display_mode = $this->validEnum($attributes['displayMode'] ?? 'banner', ['banner', 'header_banner', 'toast', 'modal', 'sidebar'], 'banner');
        $ruleset->message = (string) ($attributes['message'] ?? '');
        $ruleset->flag_message = array_key_exists('flagMessage', $attributes) ? ($attributes['flagMessage'] === null ? null : (string) $attributes['flagMessage']) : null;
        $ruleset->evaluate_all_rules = (bool) ($attributes['evaluateAllRules'] ?? false);
        $ruleset->evaluate_title = array_key_exists('evaluateTitle', $attributes) ? ($attributes['evaluateTitle'] === null ? null : (bool) $attributes['evaluateTitle']) : null;
        $ruleset->evasion_active = array_key_exists('evasionActive', $attributes) ? ($attributes['evasionActive'] === null ? null : (bool) $attributes['evasionActive']) : null;
        $ruleset->evasion_timeout = array_key_exists('evasionTimeout', $attributes) ? ($attributes['evasionTimeout'] === null ? null : max(0, (int) $attributes['evasionTimeout'])) : null;
        $ruleset->evasion_threshold = array_key_exists('evasionThreshold', $attributes) ? ($attributes['evasionThreshold'] === null ? null : max(1, (int) $attributes['evasionThreshold'])) : null;
        $ruleset->block_cascade = (bool) ($attributes['blockCascade'] ?? false);
        $ruleset->is_active = (bool) ($attributes['isActive'] ?? true);
        $ruleset->auto_flag = array_key_exists('autoFlag', $attributes) ? ($attributes['autoFlag'] === null ? null : (bool) $attributes['autoFlag']) : null;
        $ruleset->require_approval = array_key_exists('requireApproval', $attributes) ? ($attributes['requireApproval'] === null ? null : (bool) $attributes['requireApproval']) : null;
        $ruleset->scope_type = $this->validEnum($attributes['scopeType'] ?? 'global', ['global', 'normal_post', 'private_post', 'tag'], 'global');
        $ruleset->scope_tag_ids = $this->sanitizeIds($attributes['scopeTagIds'] ?? null);
        $ruleset->bypass_group_ids = $this->sanitizeIds($attributes['bypassGroupIds'] ?? null);
        $ruleset->display_settings = is_array($attributes['displaySettings'] ?? null) ? $attributes['displaySettings'] : null;
        $ruleset->save();

        return $ruleset;
    }
}
