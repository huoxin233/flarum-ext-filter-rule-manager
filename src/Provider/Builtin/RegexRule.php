<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Provider\Builtin;

use Flarum\Foundation\ValidationException;
use Huoxin\FilterRuleManager\Model\EvaluationContext;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegexRule extends AbstractBuiltinRule
{
    public function name(): string
    {
        return 'regex';
    }

    public function label(TranslatorInterface $translator): string
    {
        return $translator->trans('huoxin-filter-rule-manager.admin.type_regex');
    }

    public function providedTokens(): array
    {
        return [
            ['name' => 'matched_pattern', 'description' => 'The regex pattern definition that triggered.'],
            ['name' => 'matched_string', 'description' => 'The text substring captured by the regex.'],
            ['name' => 'matched_text', 'description' => 'Aggregates matches across all content rules.', 'universal' => true],
        ];
    }

    public function evaluate(array $config, EvaluationContext $context): ?array
    {
        $scanAll = $config['scan_all'] ?? false;
        $patterns = $this->normalizeList($config, 'patterns', 'pattern');

        if ($patterns === []) {
            return null;
        }

        $matchedPatterns = [];
        $matchedStrings = [];
        $totalCount = 0;

        foreach ($patterns as $pattern) {
            $regex = str_starts_with($pattern, '/')
                ? $pattern
                : '/'.str_replace('/', '\/', $pattern).'/iu';

            if (@preg_match_all($regex, $context->content, $matches)) {
                $count = count($matches[0]);
                if ($count > 0) {
                    if (empty($matchedPatterns) || $scanAll) {
                        $matchedPatterns[] = $pattern;
                        $matchedStrings[] = $matches[0][0] ?? '';
                    }
                    $totalCount += $count;
                }
            }
        }

        if (! empty($matchedPatterns)) {
            $matchedStringsVal = implode(', ', $matchedStrings);

            return [
                'matched_pattern' => implode(', ', $matchedPatterns),
                'matched_string' => $matchedStringsVal,
                'matched_text' => $matchedStringsVal,
                '__count' => (string) $totalCount
            ];
        }

        return null;
    }

    public function validateConfig(array $config): void
    {
        $patterns = $this->normalizeList($config, 'patterns', 'pattern');
        foreach ($patterns as $pattern) {
            $regex = str_starts_with($pattern, '/')
                ? $pattern
                : '/'.str_replace('/', '\/', $pattern).'/iu';

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
