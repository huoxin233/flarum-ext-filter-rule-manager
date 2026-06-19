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

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Backend side of the builtin provider — kept in lockstep with the JS version.
 *
 *   contains_word  — config: { words: string[] }   (legacy: { word: string })
 *   regex          — config: { patterns: string[] } (legacy: { pattern: string })
 *
 * Each type triggers if ANY of the listed entries matches. The first match
 * becomes the token value used to interpolate the ruleset's message.
 */
class BuiltinProvider implements RuleProviderInterface
{
    public function __construct(protected TranslatorInterface $translator)
    {
    }

    public function getBackendTypeLabels(): array
    {
        return [
            'contains_word' => $this->translator->trans('huoxin-filter-rule-manager.admin.type_contains_word'),
            'regex' => $this->translator->trans('huoxin-filter-rule-manager.admin.type_regex'),
        ];
    }

    public function getSupportedBackendTypes(): array
    {
        return ['contains_word', 'regex'];
    }

    /**
     * Tokens this provider exposes per rule type, for use in ruleset messages.
     * This method is NOT part of RuleProviderInterface so that older 3rd-party
     * providers remain compatible — ListProvidersController checks for it via
     * `method_exists` before calling.
     *
     * @return array<string, list<array{name:string, description:string}>>
     */
    public function getProvidedTokens(string $type): array
    {
        if ($type === 'contains_word') {
            return [
                ['name' => 'matched_word', 'description' => 'The first listed word that was found in the post content.'],
            ];
        }

        if ($type === 'regex') {
            return [
                ['name' => 'matched_pattern', 'description' => 'The first listed regex pattern that matched.'],
                ['name' => 'matched_string',  'description' => 'The substring of the post content that matched.'],
            ];
        }

        return [];
    }

    public function evaluate(string $type, string $content, array $config): ?array
    {
        $scanAll = $config['scan_all'] ?? false;

        if ($type === 'contains_word') {
            $words = $this->normalizeList($config, 'words', 'word');
            if ($words === []) {
                return null;
            }
            $matches = [];
            foreach ($words as $word) {
                if (stripos($content, $word) !== false) {
                    $matches[] = $word;
                    if (! $scanAll) {
                        break;
                    }
                }
            }
            if (! empty($matches)) {
                return ['matched_word' => implode(', ', $matches)];
            }

            return null;
        }

        if ($type === 'regex') {
            $patterns = $this->normalizeList($config, 'patterns', 'pattern');
            if ($patterns === []) {
                return null;
            }
            $matchedPatterns = [];
            $matchedStrings = [];
            foreach ($patterns as $pattern) {
                $regex = str_starts_with($pattern, '/')
                    ? $pattern
                    : '/'.str_replace('/', '\/', $pattern).'/i';

                if (@preg_match($regex, $content, $matches)) {
                    $matchedPatterns[] = $pattern;
                    $matchedStrings[] = $matches[0] ?? '';
                    if (! $scanAll) {
                        break;
                    }
                }
            }
            if (! empty($matchedPatterns)) {
                return [
                    'matched_pattern' => implode(', ', $matchedPatterns),
                    'matched_string' => implode(', ', $matchedStrings),
                ];
            }

            return null;
        }

        return null;
    }

    /**
     * Normalise either `[plural => string[]]` (new) or `[singular => string]`
     * (legacy) into a clean, trimmed, non-empty list.
     *
     * @return list<string>
     */
    private function normalizeList(array $config, string $plural, string $singular): array
    {
        if (isset($config[$plural]) && is_array($config[$plural])) {
            $out = [];
            foreach ($config[$plural] as $v) {
                $v = trim((string) $v);
                if ($v !== '') {
                    $out[] = $v;
                }
            }

            return $out;
        }

        if (isset($config[$singular]) && is_string($config[$singular])) {
            $v = trim($config[$singular]);
            if ($v !== '') {
                return [$v];
            }
        }

        return [];
    }
}
