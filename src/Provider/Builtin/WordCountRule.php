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

class WordCountRule extends AbstractBuiltinRule
{
    public function name(): string
    {
        return 'word_count';
    }

    public function label(TranslatorInterface $translator): string
    {
        return $translator->trans('huoxin-filter-rule-manager.admin.type_word_count');
    }

    public function providedTokens(): array
    {
        return [
            ['name' => 'word_count', 'description' => 'huoxin-filter-rule-manager.admin.token_word_count_desc'],
        ];
    }

    public function evaluate(array $config, EvaluationContext $context): ?array
    {
        $text = $context->content;

        // CJK Character Range:
        // Chinese: \x{4e00}-\x{9fa5}
        // Japanese: \x{3040}-\x{309F} (Hiragana), \x{30A0}-\x{30FF} (Katakana)
        // Korean: \x{AC00}-\x{D7AF} (Hangul)
        $cjkRegex = '/[\x{4e00}-\x{9fa5}\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{AC00}-\x{D7AF}]/u';

        preg_match_all($cjkRegex, $text, $cjkMatches);
        $cjkCount = count($cjkMatches[0]);

        $latinText = preg_replace($cjkRegex, ' ', $text) ?? $text;
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
}
