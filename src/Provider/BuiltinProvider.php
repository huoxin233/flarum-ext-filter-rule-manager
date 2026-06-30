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

use Flarum\Foundation\ValidationException;
use Huoxin\FilterRuleManager\Model\EvaluationContext;
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
class BuiltinProvider implements RuleProviderInterface, ValidatesConfigInterface
{
    public function __construct(protected TranslatorInterface $translator)
    {
    }

    public function getBackendTypeLabels(): array
    {
        return [
            'contains_word' => $this->translator->trans('huoxin-filter-rule-manager.admin.type_contains_word'),
            'regex' => $this->translator->trans('huoxin-filter-rule-manager.admin.type_regex'),
            'group' => $this->translator->trans('huoxin-filter-rule-manager.admin.type_group'),
            'word_count' => $this->translator->trans('huoxin-filter-rule-manager.admin.type_word_count'),
        ];
    }

    public function getSupportedBackendTypes(): array
    {
        return ['contains_word', 'regex', 'group', 'word_count'];
    }

    /**
     * Tokens this provider exposes per rule type, for use in ruleset messages.
     *
     * @param string $type The rule type being evaluated
     * @return list<array{name:string, description:string}>
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
                ['name' => 'matched_string', 'description' => 'The substring of the post content that matched.'],
            ];
        }

        if ($type === 'group') {
            return [
                ['name' => 'matched_group', 'description' => 'The user group ID that triggered the rule.'],
            ];
        }

        if ($type === 'word_count') {
            return [
                ['name' => 'word_count', 'description' => 'The calculated word count of the post.'],
            ];
        }

        return [];
    }

    public function evaluate(string $type, array $config, EvaluationContext $context): ?array
    {
        $scanAll = $config['scan_all'] ?? false;

        if ($type === 'contains_word') {
            $words = $this->normalizeList($config, 'words', 'word');
            if ($words === []) {
                return null;
            }
            $matches = [];
            foreach ($words as $word) {
                if (stripos($context->content, $word) !== false) {
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

                if (@preg_match($regex, $context->content, $matches)) {
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

        if ($type === 'group') {
            if ($context->actor === null) {
                return null;
            }

            $userGroups = $context->actor->groups->pluck('id')->toArray();
            $targetGroups = $config['groupIds'] ?? [];
            if (! is_array($targetGroups)) {
                $targetGroups = [$targetGroups];
            }

            $targetGroups = array_map('intval', $targetGroups);
            $intersect = array_intersect($userGroups, $targetGroups);

            if (count($intersect) > 0) {
                return ['matched_group' => implode(', ', $intersect)];
            }

            return null;
        }

        if ($type === 'word_count') {
            $text = $context->content;

            $excludeMentions = $config['exclude_mentions'] ?? true;
            if ($excludeMentions) {
                // Strip mentions: @"User Name"#123, @"User Name"#p123, and @username
                $text = preg_replace('/@"?[^"#\n]+"?#(?:p)?\d+/', '', $text);
                $text = preg_replace('/@\w+/', '', $text);
            }

            $excludeUrls = $config['exclude_urls'] ?? true;
            if ($excludeUrls) {
                // Remove URLs to avoid them skewing word counts
                $text = preg_replace('#https?://[^\s]+#i', '', $text);
            }

            // CJK Character Range:
            // Chinese: \x{4e00}-\x{9fa5}
            // Japanese: \x{3040}-\x{309F} (Hiragana), \x{30A0}-\x{30FF} (Katakana)
            // Korean: \x{AC00}-\x{D7AF} (Hangul)
            $cjkRegex = '/[\x{4e00}-\x{9fa5}\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{AC00}-\x{D7AF}]/u';

            preg_match_all($cjkRegex, $text, $cjkMatches);
            $cjkCount = count($cjkMatches[0] ?? []);

            $latinText = preg_replace($cjkRegex, ' ', $text);
            $latinCount = str_word_count($latinText);

            $count = $cjkCount + $latinCount;

            $min = isset($config['min']) && $config['min'] !== '' ? (int) $config['min'] : null;
            $max = isset($config['max']) && $config['max'] !== '' ? (int) $config['max'] : null;

            if ($min !== null && $count < $min) {
                return ['word_count' => (string) $count];
            }

            if ($max !== null && $count > $max) {
                return ['word_count' => (string) $count];
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

    public function validateConfig(string $type, array $config): void
    {
        if ($type === 'regex') {
            $patterns = $this->normalizeList($config, 'patterns', 'pattern');
            foreach ($patterns as $pattern) {
                $regex = str_starts_with($pattern, '/')
                    ? $pattern
                    : '/'.str_replace('/', '\/', $pattern).'/i';

                error_clear_last();
                if (@preg_match($regex, '') === false) {
                    $error = error_get_last();
                    $msg = $error ? $error['message'] : preg_last_error_msg();

                    throw new ValidationException([
                        'expression' => "Invalid regex pattern '{$pattern}'. Error: {$msg}",
                    ]);
                }
            }
        }
    }
}
