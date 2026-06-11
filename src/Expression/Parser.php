<?php

namespace Huoxin\FilterRuleManager\Expression;

class Parser
{
    /** @var Token[] */
    private array $tokens;
    private int $position = 0;
    private int $length;

    public function __construct(array $tokens)
    {
        $this->tokens = $tokens;
        $this->length = count($tokens);
    }

    public function parse(): NodeInterface
    {
        $node = $this->parseLogicalOr();

        if (!$this->isAtEnd()) {
            $token = $this->peek();
            throw new \InvalidArgumentException("Unexpected token '{$token->value}' at position {$token->position}. Expected end of expression.");
        }

        return $node;
    }

    private function parseLogicalOr(): NodeInterface
    {
        $node = $this->parseLogicalAnd();

        while ($this->match(Token::T_OR)) {
            $operator = $this->previous()->value;
            $right = $this->parseLogicalAnd();
            $node = new LogicalNode('OR', $node, $right);
        }

        return $node;
    }

    private function parseLogicalAnd(): NodeInterface
    {
        $node = $this->parseUnary();

        while ($this->match(Token::T_AND)) {
            $operator = $this->previous()->value;
            $right = $this->parseUnary();
            $node = new LogicalNode('AND', $node, $right);
        }

        return $node;
    }

    private function parseUnary(): NodeInterface
    {
        if ($this->match(Token::T_NOT)) {
            $node = $this->parseUnary();
            return new NotNode($node);
        }

        return $this->parsePrimary();
    }

    private function parsePrimary(): NodeInterface
    {
        if ($this->match(Token::T_LPAREN)) {
            $node = $this->parseLogicalOr();
            $this->consume(Token::T_RPAREN, "Expected ')' after expression.");
            return $node;
        }

        return $this->parseRule();
    }

    private function parseRule(): NodeInterface
    {
        $fieldToken = $this->consume(Token::T_FIELD, "Expected field identifier (e.g. provider.type)");
        
        $parts = explode('.', $fieldToken->value, 2);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException("Field '{$fieldToken->value}' must be in format provider.type");
        }

        $provider = $parts[0];
        $ruleType = $parts[1];

        // check if next is an operator
        if ($this->check(Token::T_OP)) {
            $opToken = $this->advance();
            $operator = $opToken->value;
            $value = $this->parseValue();
        } else {
            // Implicit boolean true, e.g. `builtin.is_first_post`
            $operator = 'eq';
            $value = true;
        }

        return new RuleNode($provider, $ruleType, $operator, $value);
    }

    private function parseValue(): mixed
    {
        if ($this->match(Token::T_STRING)) {
            return $this->previous()->value;
        }
        if ($this->match(Token::T_NUMBER)) {
            return $this->previous()->value;
        }
        if ($this->match(Token::T_BOOLEAN)) {
            return $this->previous()->value;
        }
        if ($this->match(Token::T_LBRACKET)) {
            $arr = [];
            if (!$this->check(Token::T_RBRACKET)) {
                do {
                    $arr[] = $this->parseValue();
                } while ($this->match(Token::T_COMMA));
            }
            $this->consume(Token::T_RBRACKET, "Expected ']' after array elements.");
            return $arr;
        }
        if ($this->match(Token::T_LBRACE)) {
            $obj = [];
            if (!$this->check(Token::T_RBRACE)) {
                do {
                    $keyToken = $this->consume(Token::T_STRING, "Expected string key in object");
                    $this->consume(Token::T_COLON, "Expected ':' after object key");
                    $val = $this->parseValue();
                    $obj[$keyToken->value] = $val;
                } while ($this->match(Token::T_COMMA));
            }
            $this->consume(Token::T_RBRACE, "Expected '}' after object.");
            return $obj;
        }

        $token = $this->peek();
        throw new \InvalidArgumentException("Expected value (string, number, boolean, array) at position {$token->position}. Found: {$token->type}");
    }

    private function match(string ...$types): bool
    {
        foreach ($types as $type) {
            if ($this->check($type)) {
                $this->advance();
                return true;
            }
        }
        return false;
    }

    private function consume(string $type, string $message): Token
    {
        if ($this->check($type)) {
            return $this->advance();
        }

        throw new \InvalidArgumentException($message . " Found: " . $this->peek()->type);
    }

    private function check(string $type): bool
    {
        if ($this->isAtEnd()) {
            return false;
        }
        return $this->peek()->type === $type;
    }

    private function advance(): Token
    {
        if (!$this->isAtEnd()) {
            $this->position++;
        }
        return $this->previous();
    }

    private function isAtEnd(): bool
    {
        return $this->peek()->type === Token::T_EOF;
    }

    private function peek(): Token
    {
        return $this->tokens[$this->position];
    }

    private function previous(): Token
    {
        return $this->tokens[$this->position - 1];
    }
}
