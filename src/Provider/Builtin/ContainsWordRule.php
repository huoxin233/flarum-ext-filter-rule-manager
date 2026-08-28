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

use Huoxin\FilterRuleManager\Model\EvaluationContext;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContainsWordRule extends AbstractBuiltinRule
{
    public function name(): string
    {
        return 'contains_word';
    }

    public function label(TranslatorInterface $translator): string
    {
        return $translator->trans('huoxin-filter-rule-manager.admin.type_contains_word');
    }

    public function providedTokens(): array
    {
        return [
            ['name' => 'matched_word', 'description' => 'huoxin-filter-rule-manager.admin.token_matched_word_desc'],
            ['name' => 'matched_text', 'description' => 'huoxin-filter-rule-manager.admin.token_matched_text_desc', 'universal' => true],
        ];
    }

    public function evaluate(array $config, EvaluationContext $context): ?array
    {
        $scanAll = $config['scan_all'] ?? false;
        $words = $this->normalizeList($config, 'words', 'word');

        if ($words === []) {
            return null;
        }

        $lowerContent = mb_strtolower($context->content);
        $matches = [];
        $totalCount = 0;

        foreach ($words as $word) {
            $count = substr_count($lowerContent, mb_strtolower($word));
            if ($count > 0) {
                if (empty($matches) || $scanAll) {
                    $matches[] = $word;
                }
                $totalCount += $count;
            }
        }

        if (! empty($matches)) {
            $matchedStr = implode(', ', $matches);

            return [
                'matched_word' => $matchedStr,
                'matched_text' => $matchedStr,
                '__count' => (string) $totalCount
            ];
        }

        return null;
    }
}
