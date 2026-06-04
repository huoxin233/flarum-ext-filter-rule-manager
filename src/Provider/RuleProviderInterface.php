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

/**
 * Implement this interface to supply backend (block) rule evaluation logic.
 *
 * Register via:
 *   (new \Huoxin\FilterRuleManager\Extend\FilterRuleProvider())
 *       ->registerProvider('my-extension-id', MyProvider::class)
 */
interface RuleProviderInterface
{
    /**
     * The rule type strings this provider handles on the backend.
     * Return an empty array if this provider only has frontend checks.
     *
     * @return string[]
     */
    public function getSupportedBackendTypes(): array;

    /**
     * Evaluate a single rule against post content.
     *
     * @param string $type   The rule type string (one of getSupportedBackendTypes())
     * @param string $content The raw post content
     * @param array  $config  Rule config JSON decoded to array
     *
     * @return array|null  null = not triggered; array (may be empty) = triggered with tokens
     */
    public function evaluate(string $type, string $content, array $config): ?array;

    /**
     * Human-readable labels for each supported type (shown in admin rule builder).
     *
     * @return array<string, string>  ['type_string' => 'Human Label']
     */
    public function getBackendTypeLabels(): array;
}
