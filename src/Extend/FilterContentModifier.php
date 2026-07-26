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

use Flarum\Extend\ExtenderInterface;
use Flarum\Extension\Extension;
use Huoxin\FilterRuleManager\Modifier\ModifierInterface;
use Illuminate\Contracts\Container\Container;

class FilterContentModifier implements ExtenderInterface
{
    public const PENDING_KEY = 'filter-rule-manager.pending_modifiers';
    public const REGISTRY_KEY = 'filter-rule-manager.modifiers';

    private array $modifiers = [];

    /**
     * @param class-string<ModifierInterface> $class  Class implementing ModifierInterface
     */
    public function register(string $class): static
    {
        $this->modifiers[] = $class;

        return $this;
    }

    public function extend(Container $container, ?Extension $extension = null): void
    {
        if (empty($this->modifiers)) {
            return;
        }

        $modifiersToAdd = $this->modifiers;

        if ($container->bound(self::REGISTRY_KEY)) {
            $container->extend(self::REGISTRY_KEY, function (array $existing) use ($container, $modifiersToAdd) {
                foreach ($modifiersToAdd as $class) {
                    $instance = $container->make($class);
                    $existing[$instance->key()] = [
                        'label' => $instance->name(),
                        'class' => $class
                    ];
                }
                return $existing;
            });
            return;
        }

        $pending = $container->bound(self::PENDING_KEY)
            ? (array) $container->make(self::PENDING_KEY)
            : [];

        $container->instance(self::PENDING_KEY, array_merge($pending, $modifiersToAdd));
    }
}
