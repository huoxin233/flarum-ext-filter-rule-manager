<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager;

use Flarum\Foundation\AbstractServiceProvider;
use Huoxin\FilterRuleManager\Extend\FilterContentModifier;
use Huoxin\FilterRuleManager\Extend\FilterRuleProvider;
use Huoxin\FilterRuleManager\Model\Ruleset;
use Huoxin\FilterRuleManager\Repository\RulesetRepository;
use Huoxin\FilterRuleManager\Service\RulesetMatcher;
use Huoxin\FilterRuleManager\Provider\BuiltinProvider;
use Illuminate\Contracts\Container\Container;

class FilterRuleManagerServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        /** @var Container $container */
        $container = $this->container;
        /** @phpstan-ignore-next-line */
        $container->scoped(RulesetRepository::class);
        /** @phpstan-ignore-next-line */
        $container->scoped(RulesetMatcher::class);

        $this->container->singleton(FilterRuleProvider::REGISTRY_KEY, function ($container) {
            $providers = [
                'builtin' => $container->make(BuiltinProvider::class),
            ];

            // Pick up any providers registered by third-party extenders that ran
            // before this service provider — see FilterRuleProvider::extend.
            if ($container->bound(FilterRuleProvider::PENDING_KEY)) {
                foreach ($container->make(FilterRuleProvider::PENDING_KEY) as $name => $class) {
                    $providers[$name] = $container->make($class);
                }
            }

            return $providers;
        });

        $this->container->singleton(FilterContentModifier::REGISTRY_KEY, function ($container) {
            $modifiers = [];

            if ($container->bound(FilterContentModifier::PENDING_KEY)) {
                $pendingClasses = $container->make(FilterContentModifier::PENDING_KEY);
                foreach ($pendingClasses as $class) {
                    $instance = $container->make($class);
                    $modifiers[$instance->key()] = [
                        'label' => $instance->name(),
                        'description' => $instance->description(),
                        'class' => $class
                    ];
                }
            }

            return $modifiers;
        });
    }

    public function boot(): void
    {
        Ruleset::saved(function () {
            $this->container->make(RulesetRepository::class)->flush();
        });

        Ruleset::deleted(function () {
            $this->container->make(RulesetRepository::class)->flush();
        });
    }
}
