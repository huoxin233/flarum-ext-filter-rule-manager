<?php

namespace Huoxin\FilterRuleManager\Expression;

class Lexer
{
    private string $input;
    private int $length;
    private int $position = 0;

    public function __construct(string $input)
    {
        $this->input = $input;
        $this->length = strlen($input);
    }

    public function tokenize(): array
    {
        $tokens = [];
        while ($this->position < $this->length) {
            $char = $this->input[$this->position];

            if (ctype_space($char)) {
                $this->position++;
                continue;
            }

            if ($char === '(') {
                $tokens[] = new Token(Token::T_LPAREN, '(', $this->position++);
                continue;
            }
            if ($char === ')') {
                $tokens[] = new Token(Token::T_RPAREN, ')', $this->position++);
                continue;
            }
            if ($char === '[') {
                $tokens[] = new Token(Token::T_LBRACKET, '[', $this->position++);
                continue;
            }
            if ($char === ']') {
                $tokens[] = new Token(Token::T_RBRACKET, ']', $this->position++);
                continue;
            }
            if ($char === '{') {
                $tokens[] = new Token(Token::T_LBRACE, '{', $this->position++);
                continue;
            }
            if ($char === '}') {
                $tokens[] = new Token(Token::T_RBRACE, '}', $this->position++);
                continue;
            }
            if ($char === ':') {
                $tokens[] = new Token(Token::T_COLON, ':', $this->position++);
                continue;
            }
            if ($char === ',') {
                $tokens[] = new Token(Token::T_COMMA, ',', $this->position++);
                continue;
            }

            if ($char === '"' || $char === "'") {
                $tokens[] = $this->readString($char);
                continue;
            }

            if (ctype_digit($char) || ($char === '-' && $this->position + 1 < $this->length && ctype_digit($this->input[$this->position + 1]))) {
                $tokens[] = $this->readNumber();
                continue;
            }

            if (ctype_alpha($char) || $char === '_') {
                $token = $this->readIdentifier();
                $lower = strtolower($token->value);

                if ($lower === 'and') $token->type = Token::T_AND;
                elseif ($lower === 'or') $token->type = Token::T_OR;
                elseif ($lower === 'not') $token->type = Token::T_NOT;
                elseif ($lower === 'true') { $token->type = Token::T_BOOLEAN; $token->value = true; }
                elseif ($lower === 'false') { $token->type = Token::T_BOOLEAN; $token->value = false; }
                elseif (in_array($lower, ['eq', 'neq', 'gt', 'lt', 'in', 'contains', 'matches', 'contains_word', 'contains_regex'])) {
                    $token->type = Token::T_OP;
                    $token->value = $lower;
                } elseif (strpos($token->value, '.') !== false) {
                    $token->type = Token::T_FIELD;
                } else {
                    // Default to field even if no dot, or operator if extended
                    $token->type = Token::T_FIELD;
                }

                $tokens[] = $token;
                continue;
            }

            // Operators like ==, !=, <, >, <=, >=
            if (in_array($char, ['=', '!', '<', '>'])) {
                $op = $this->readOperator();
                if ($op) {
                    $tokens[] = $op;
                    continue;
                }
            }

            throw new \InvalidArgumentException("Unexpected character '{$char}' at position {$this->position}");
        }

        $tokens[] = new Token(Token::T_EOF, null, $this->position);
        return $tokens;
    }

    private function readString(string $quote): Token
    {
        $start = $this->position;
        $this->position++; // skip opening quote
        $value = '';

        while ($this->position < $this->length) {
            $char = $this->input[$this->position];
            if ($char === '\\') {
                $this->position++;
                if ($this->position < $this->length) {
                    $value .= $this->input[$this->position];
                }
            } elseif ($char === $quote) {
                $this->position++; // skip closing quote
                return new Token(Token::T_STRING, $value, $start);
            } else {
                $value .= $char;
            }
            $this->position++;
        }

        throw new \InvalidArgumentException("Unterminated string starting at position {$start}");
    }

    private function readNumber(): Token
    {
        $start = $this->position;
        $value = '';
        if ($this->input[$this->position] === '-') {
            $value .= '-';
            $this->position++;
        }
        
        $hasDot = false;
        while ($this->position < $this->length) {
            $char = $this->input[$this->position];
            if (ctype_digit($char)) {
                $value .= $char;
            } elseif ($char === '.' && !$hasDot) {
                $hasDot = true;
                $value .= $char;
            } else {
                break;
            }
            $this->position++;
        }

        return new Token(Token::T_NUMBER, $hasDot ? (float)$value : (int)$value, $start);
    }

    private function readIdentifier(): Token
    {
        $start = $this->position;
        $value = '';
        while ($this->position < $this->length) {
            $char = $this->input[$this->position];
            // fields can be provider.type
            if (ctype_alnum($char) || $char === '_' || $char === '.') {
                $value .= $char;
                $this->position++;
            } else {
                break;
            }
        }
        return new Token(Token::T_FIELD, $value, $start); // Will be re-categorized by caller
    }

    private function readOperator(): ?Token
    {
        $start = $this->position;
        $char = $this->input[$this->position];
        $next = $this->position + 1 < $this->length ? $this->input[$this->position + 1] : '';

        $op = null;
        if ($char === '=' && $next === '=') {
            $op = 'eq';
            $this->position += 2;
        } elseif ($char === '!' && $next === '=') {
            $op = 'neq';
            $this->position += 2;
        } elseif ($char === '<' && $next === '=') {
            $op = 'lte';
            $this->position += 2;
        } elseif ($char === '>' && $next === '=') {
            $op = 'gte';
            $this->position += 2;
        } elseif ($char === '<') {
            $op = 'lt';
            $this->position++;
        } elseif ($char === '>') {
            $op = 'gt';
            $this->position++;
        }

        if ($op) {
            return new Token(Token::T_OP, $op, $start);
        }

        return null;
    }
}
