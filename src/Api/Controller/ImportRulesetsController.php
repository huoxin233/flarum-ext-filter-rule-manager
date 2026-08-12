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

use Exception;
use Flarum\Group\Group;
use Flarum\Http\RequestUtil;
use Flarum\Tags\Tag;
use Huoxin\FilterRuleManager\Model\Ruleset;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Huoxin\FilterRuleManager\Expression\Lexer;
use Huoxin\FilterRuleManager\Expression\Parser;
use Huoxin\FilterRuleManager\Service\RuleEvaluator;
use Flarum\Foundation\ValidationException;
use Psr\Http\Server\RequestHandlerInterface;

class ImportRulesetsController implements RequestHandlerInterface
{
    use RulesetValidationTrait;

    public function __construct(protected RuleEvaluator $evaluator)
    {
    }
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

                $ruleset->name = trim((string) ($data['name'] ?? ''));
                $ruleset->expression = trim((string) ($data['expression'] ?? ''));

                if ($ruleset->expression !== '') {
                    try {
                        $lexer = new Lexer($ruleset->expression);
                        $tokens = $lexer->tokenize();
                        $parser = new Parser($tokens);
                        $ast = $parser->parse();
                        $this->validateAstNode($ast, $this->evaluator->getProviders());
                        $ruleset->compiled_ast = $ast->toArray();
                    } catch (ValidationException $e) {
                        throw $e;
                    } catch (Exception $e) {
                        throw new ValidationException(['expression' => 'Invalid expression syntax in imported ruleset "'.$ruleset->name.'": '.$e->getMessage()]);
                    }
                } else {
                    $ruleset->compiled_ast = null;
                }

                // Map remaining attributes explicitly and safely (prevent mass-assignment vulnerabilities)
                $ruleset->intervention_type = $this->validEnum($data['intervention_type'] ?? 'info', ['info', 'warning', 'block', 'silent'], 'info');
                $ruleset->display_mode = $this->validEnum($data['display_mode'] ?? 'banner', ['none', 'banner', 'header_banner', 'toast', 'modal', 'sidebar'], 'banner');
                $ruleset->message = (string) ($data['message'] ?? '');
                $ruleset->flag_message = array_key_exists('flag_message', $data) ? ($data['flag_message'] === null ? null : (string) $data['flag_message']) : null;
                $ruleset->evaluate_all_rules = (bool) ($data['evaluate_all_rules'] ?? false);
                $ruleset->evaluate_title = array_key_exists('evaluate_title', $data) ? ($data['evaluate_title'] === null ? null : (bool) $data['evaluate_title']) : null;
                $ruleset->evasion_active = array_key_exists('evasion_active', $data) ? ($data['evasion_active'] === null ? null : (bool) $data['evasion_active']) : null;
                $ruleset->evasion_timeout = array_key_exists('evasion_timeout', $data) ? ($data['evasion_timeout'] === null ? null : max(0, (int) $data['evasion_timeout'])) : null;
                $ruleset->evasion_threshold = array_key_exists('evasion_threshold', $data) ? ($data['evasion_threshold'] === null ? null : max(1, (int) $data['evasion_threshold'])) : null;
                $ruleset->block_cascade = (bool) ($data['block_cascade'] ?? false);
                $ruleset->is_active = (bool) ($data['is_active'] ?? true);
                $ruleset->auto_flag = array_key_exists('auto_flag', $data) ? ($data['auto_flag'] === null ? null : (bool) $data['auto_flag']) : null;
                $ruleset->require_approval = array_key_exists('require_approval', $data) ? ($data['require_approval'] === null ? null : (bool) $data['require_approval']) : null;
                $ruleset->strict_edit = array_key_exists('strict_edit', $data) ? ($data['strict_edit'] === null ? null : (bool) $data['strict_edit']) : null;
                $ruleset->scope_type = $this->validEnum($data['scope_type'] ?? 'global', ['global', 'normal_post', 'private_post', 'tag'], 'global');
                $ruleset->display_settings = is_array($data['display_settings'] ?? null) ? $data['display_settings'] : null;

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
