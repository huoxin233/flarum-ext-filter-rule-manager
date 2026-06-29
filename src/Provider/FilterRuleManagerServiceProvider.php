<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Provider;

use Flarum\Foundation\AbstractServiceProvider;
use Huoxin\FilterRuleManager\Extend\FilterRuleProvider;
use Huoxin\FilterRuleManager\Repository\RulesetRepository;
use Huoxin\FilterRuleManager\Service\RulesetMatcher;

class FilterRuleManagerServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->scoped(RulesetRepository::class);
        $this->container->scoped(RulesetMatcher::class);

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
    }
}
