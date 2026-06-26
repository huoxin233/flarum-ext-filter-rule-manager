<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Expression;

class Token
{
    public const T_AND = 'AND';
    public const T_OR = 'OR';
    public const T_NOT = 'NOT';
    public const T_LPAREN = 'LPAREN';
    public const T_RPAREN = 'RPAREN';
    public const T_LBRACKET = 'LBRACKET';
    public const T_RBRACKET = 'RBRACKET';
    public const T_LBRACE = 'LBRACE';
    public const T_RBRACE = 'RBRACE';
    public const T_COLON = 'COLON';
    public const T_COMMA = 'COMMA';

    public const T_FIELD = 'FIELD';
    public const T_OP = 'OP';

    public const T_STRING = 'STRING';
    public const T_NUMBER = 'NUMBER';
    public const T_BOOLEAN = 'BOOLEAN';

    public const T_EOF = 'EOF';

    public function __construct(
        public string $type,
        public mixed $value = null,
        public int $position = 0
    ) {
    }
}
