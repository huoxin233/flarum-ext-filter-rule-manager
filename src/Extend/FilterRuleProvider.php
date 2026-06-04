<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Extend;

use Flarum\Extension\Extension;
use Flarum\Extend\ExtenderInterface;
use Huoxin\FilterRuleManager\Provider\RuleProviderInterface;
use Illuminate\Contracts\Container\Container;

/**
 * Extender used by third-party extensions to register backend rule providers.
 *
 * Usage in extend.php:
 *
 *   (new \Huoxin\FilterRuleManager\Extend\FilterRuleProvider())
 *       ->registerProvider('my-extension-id', MyRuleProvider::class)
 *
 * Implementation note: extension load order is not guaranteed, so this
 * extender works whether filter-rule-manager's own service provider has
 * already bound `filter-rule-manager.rule_providers` or not.
 *
 * If the singleton already exists we extend it directly. Otherwise we stash
 * the provider classes in a side binding that the service provider will read
 * when it eventually resolves the singleton.
 */
class FilterRuleProvider implements ExtenderInterface
{
    public const PENDING_KEY = 'filter-rule-manager.pending_providers';
    public const REGISTRY_KEY = 'filter-rule-manager.rule_providers';

    /** @var array<string, class-string<RuleProviderInterface>> */
    private array $providers = [];

    /**
     * @param string $name          Unique provider name (use your extension ID)
     * @param class-string $class   Class implementing RuleProviderInterface
     */
    public function registerProvider(string $name, string $class): static
    {
        $this->providers[$name] = $class;
        return $this;
    }

    public function extend(Container $container, Extension $extension = null): void
    {
        if (empty($this->providers)) {
            return;
        }

        $providers = $this->providers;

        if ($container->bound(self::REGISTRY_KEY)) {
            $container->extend(self::REGISTRY_KEY, function (array $existing) use ($providers, $container) {
                foreach ($providers as $name => $class) {
                    $existing[$name] = $container->make($class);
                }
                return $existing;
            });
            return;
        }

        // Stash for later resolution by FilterRuleManagerServiceProvider.
        $pending = $container->bound(self::PENDING_KEY)
            ? $container->make(self::PENDING_KEY)
            : [];

        $container->instance(self::PENDING_KEY, array_merge($pending, $providers));
    }
}
