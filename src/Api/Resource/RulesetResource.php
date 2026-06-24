<?php

namespace Huoxin\FilterRuleManager\Api\Resource;

use Flarum\Api\Endpoint;
use Flarum\Api\Resource;
use Flarum\Api\Schema;
use Huoxin\FilterRuleManager\Expression\Lexer;
use Huoxin\FilterRuleManager\Expression\Parser;
use Huoxin\FilterRuleManager\Model\Ruleset;
use Illuminate\Database\Eloquent\Builder;
use Tobscure\JsonApi\Exception\InvalidParameterException;
use Tobyz\JsonApiServer\Context;

/**
 * @extends Resource\AbstractDatabaseResource<Ruleset>
 */
class RulesetResource extends Resource\AbstractDatabaseResource
{
    public function type(): string
    {
        return 'filter-rule-rulesets';
    }

    public function model(): string
    {
        return Ruleset::class;
    }

    public function scope(Builder $query, Context $context): void
    {
        $query->whereVisibleTo($context->getActor());
    }

    public function creating(object $model, Context $context): ?object
    {
        if ($model->priority === null) {
            $model->priority = ((int) Ruleset::max('priority')) + 10;
        }
        return $model;
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Create::make()
                ->can('createRuleset'),
            Endpoint\Update::make()
                ->can('update'),
            Endpoint\Delete::make()
                ->can('delete'),
            Endpoint\Index::make()
                ->paginate(),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name')
                ->requiredOnCreate()
                ->writable()
                ->set(function (Ruleset $model, $value) {
                    $name = trim((string) $value);
                    if ($name === '') {
                        throw new InvalidParameterException('Ruleset name cannot be empty.');
                    }
                    $model->name = $name;
                }),
            Schema\Number::make('priority')
                ->writable(),
            Schema\Str::make('expression')
                ->writable()
                ->set(function (Ruleset $model, $value) {
                    $expression = trim((string) $value);
                    $model->expression = $expression;

                    if ($expression !== '') {
                        try {
                            $lexer = new Lexer($expression);
                            $tokens = $lexer->tokenize();
                            $parser = new Parser($tokens);
                            $ast = $parser->parse();
                            $model->compiled_ast = $ast->toArray();
                        } catch (\Exception $e) {
                            throw new InvalidParameterException('Invalid expression syntax: '.$e->getMessage());
                        }
                    } else {
                        $model->compiled_ast = null;
                    }
                }),
            Schema\Arr::make('compiledAst')
                ->property('compiled_ast'),
            Schema\Str::make('interventionType')
                ->property('intervention_type')
                ->writable()
                ->set(function (Ruleset $model, $value) {
                    $model->intervention_type = in_array($value, ['info', 'warning', 'block', 'silent'], true) ? $value : ($model->intervention_type ?? 'info');
                }),
            Schema\Str::make('displayMode')
                ->property('display_mode')
                ->writable()
                ->set(function (Ruleset $model, $value) {
                    $model->display_mode = in_array($value, ['banner', 'header_banner', 'toast', 'modal', 'sidebar'], true) ? $value : ($model->display_mode ?? 'banner');
                }),
            Schema\Str::make('message')
                ->writable(),
            Schema\Str::make('flagMessage')
                ->property('flag_message')
                ->writable()
                ->nullable(),
            Schema\Boolean::make('evaluateAllRules')
                ->property('evaluate_all_rules')
                ->writable(),
            Schema\Boolean::make('evaluateTitle')
                ->property('evaluate_title')
                ->writable()
                ->nullable(),
            Schema\Boolean::make('evasionActive')
                ->property('evasion_active')
                ->writable()
                ->nullable(),
            Schema\Number::make('evasionTimeout')
                ->property('evasion_timeout')
                ->writable()
                ->nullable()
                ->set(function (Ruleset $model, $value) {
                    $model->evasion_timeout = $value === null ? null : max(0, (int) $value);
                }),
            Schema\Number::make('evasionThreshold')
                ->property('evasion_threshold')
                ->writable()
                ->nullable()
                ->set(function (Ruleset $model, $value) {
                    $model->evasion_threshold = $value === null ? null : max(1, (int) $value);
                }),
            Schema\Boolean::make('blockCascade')
                ->property('block_cascade')
                ->writable(),
            Schema\Boolean::make('isActive')
                ->property('is_active')
                ->writable(),
            Schema\Boolean::make('autoFlag')
                ->property('auto_flag')
                ->writable()
                ->nullable(),
            Schema\Boolean::make('requireApproval')
                ->property('require_approval')
                ->writable()
                ->nullable(),
            Schema\Str::make('scopeType')
                ->property('scope_type')
                ->writable()
                ->set(function (Ruleset $model, $value) {
                    $model->scope_type = in_array($value, ['global', 'normal_post', 'private_post', 'tag'], true) ? $value : ($model->scope_type ?? 'global');
                }),
            Schema\Arr::make('scopeTagIds')
                ->property('scope_tag_ids')
                ->writable()
                ->nullable()
                ->set(function (Ruleset $model, $value) {
                    $model->scope_tag_ids = $this->sanitizeIds($value);
                }),
            Schema\Arr::make('bypassGroupIds')
                ->property('bypass_group_ids')
                ->writable()
                ->nullable()
                ->set(function (Ruleset $model, $value) {
                    $model->bypass_group_ids = $this->sanitizeIds($value);
                }),
            Schema\Arr::make('displaySettings')
                ->property('display_settings')
                ->writable()
                ->nullable(),

            Schema\DateTime::make('createdAt')
                ->property('created_at'),
            Schema\DateTime::make('updatedAt')
                ->property('updated_at'),
        ];
    }

    public function sorts(): array
    {
        return [];
    }

    private function sanitizeIds($raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }
        $ids = array_values(array_filter(array_map('intval', $raw), fn ($id) => $id > 0));

        return $ids === [] ? null : $ids;
    }
}
