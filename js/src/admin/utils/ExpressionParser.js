export const T_AND = 'AND';
export const T_OR = 'OR';
export const T_NOT = 'NOT';
export const T_LPAREN = 'LPAREN';
export const T_RPAREN = 'RPAREN';
export const T_LBRACKET = 'LBRACKET';
export const T_RBRACKET = 'RBRACKET';
export const T_COMMA = 'COMMA';
export const T_FIELD = 'FIELD';
export const T_OP = 'OP';
export const T_STRING = 'STRING';
export const T_NUMBER = 'NUMBER';
export const T_BOOLEAN = 'BOOLEAN';
export const T_LBRACE = 'LBRACE';
export const T_RBRACE = 'RBRACE';
export const T_COLON = 'COLON';
export const T_EOF = 'EOF';

class Lexer {
  constructor(input) {
    this.input = input;
    this.length = input.length;
    this.position = 0;
  }

  tokenize() {
    const tokens = [];
    while (this.position < this.length) {
      const char = this.input[this.position];

      if (/\s/.test(char)) {
        this.position++;
        continue;
      }

      if (char === '(') { tokens.push({ type: T_LPAREN, value: '(', position: this.position++ }); continue; }
      if (char === ')') { tokens.push({ type: T_RPAREN, value: ')', position: this.position++ }); continue; }
      if (char === '[') { tokens.push({ type: T_LBRACKET, value: '[', position: this.position++ }); continue; }
      if (char === ']') { tokens.push({ type: T_RBRACKET, value: ']', position: this.position++ }); continue; }
      if (char === '{') { tokens.push({ type: T_LBRACE, value: '{', position: this.position++ }); continue; }
      if (char === '}') { tokens.push({ type: T_RBRACE, value: '}', position: this.position++ }); continue; }
      if (char === ':') { tokens.push({ type: T_COLON, value: ':', position: this.position++ }); continue; }
      if (char === ',') { tokens.push({ type: T_COMMA, value: ',', position: this.position++ }); continue; }

      if (char === '"' || char === "'") {
        tokens.push(this.readString(char));
        continue;
      }

      if (/[0-9]/.test(char) || (char === '-' && this.position + 1 < this.length && /[0-9]/.test(this.input[this.position + 1]))) {
        tokens.push(this.readNumber());
        continue;
      }

      if (/[a-zA-Z_]/.test(char)) {
        const token = this.readIdentifier();
        const lower = token.value.toLowerCase();

        if (lower === 'and') token.type = T_AND;
        else if (lower === 'or') token.type = T_OR;
        else if (lower === 'not') token.type = T_NOT;
        else if (lower === 'true') { token.type = T_BOOLEAN; token.value = true; }
        else if (lower === 'false') { token.type = T_BOOLEAN; token.value = false; }
        else if (['eq', 'neq', 'gt', 'lt', 'lte', 'gte', 'in', 'contains', 'matches', 'contains_word', 'contains_regex'].includes(lower)) {
          token.type = T_OP;
          token.value = lower;
        } else {
          token.type = T_FIELD;
        }
        tokens.push(token);
        continue;
      }

      if (['=', '!', '<', '>'].includes(char)) {
        const op = this.readOperator();
        if (op) { tokens.push(op); continue; }
      }

      throw new Error(`Unexpected character '${char}' at position ${this.position}`);
    }
    tokens.push({ type: T_EOF, value: null, position: this.position });
    return tokens;
  }

  readString(quote) {
    const start = this.position;
    this.position++;
    let value = '';
    while (this.position < this.length) {
      const char = this.input[this.position];
      if (char === '\\') {
        this.position++;
        if (this.position < this.length) value += this.input[this.position];
      } else if (char === quote) {
        this.position++;
        return { type: T_STRING, value, position: start };
      } else {
        value += char;
      }
      this.position++;
    }
    throw new Error(`Unterminated string starting at position ${start}`);
  }

  readNumber() {
    const start = this.position;
    let value = '';
    if (this.input[this.position] === '-') { value += '-'; this.position++; }
    let hasDot = false;
    while (this.position < this.length) {
      const char = this.input[this.position];
      if (/[0-9]/.test(char)) { value += char; }
      else if (char === '.' && !hasDot) { hasDot = true; value += char; }
      else break;
      this.position++;
    }
    return { type: T_NUMBER, value: Number(value), position: start };
  }

  readIdentifier() {
    const start = this.position;
    let value = '';
    while (this.position < this.length) {
      const char = this.input[this.position];
      if (/[a-zA-Z0-9_.]/.test(char)) { value += char; this.position++; }
      else break;
    }
    return { type: T_FIELD, value, position: start };
  }

  readOperator() {
    const start = this.position;
    const char = this.input[this.position];
    const next = this.position + 1 < this.length ? this.input[this.position + 1] : '';
    let op = null;
    if (char === '=' && next === '=') { op = 'eq'; this.position += 2; }
    else if (char === '!' && next === '=') { op = 'neq'; this.position += 2; }
    else if (char === '<' && next === '=') { op = 'lte'; this.position += 2; }
    else if (char === '>' && next === '=') { op = 'gte'; this.position += 2; }
    else if (char === '<') { op = 'lt'; this.position++; }
    else if (char === '>') { op = 'gt'; this.position++; }

    return op ? { type: T_OP, value: op, position: start } : null;
  }
}

export class Parser {
  constructor(tokens) {
    this.tokens = tokens;
    this.length = tokens.length;
    this.position = 0;
  }

  parse() {
    const node = this.parseLogicalOr();
    if (!this.isAtEnd()) {
      const token = this.peek();
      throw new Error(`Unexpected token '${token.value}' at position ${token.position}. Expected end of expression.`);
    }
    return node;
  }

  parseLogicalOr() {
    let node = this.parseLogicalAnd();
    while (this.match(T_OR)) {
      node = { type: 'logical', operator: 'OR', left: node, right: this.parseLogicalAnd() };
    }
    return node;
  }

  parseLogicalAnd() {
    let node = this.parseUnary();
    while (this.match(T_AND)) {
      node = { type: 'logical', operator: 'AND', left: node, right: this.parseUnary() };
    }
    return node;
  }

  parseUnary() {
    if (this.match(T_NOT)) return { type: 'not', node: this.parseUnary() };
    return this.parsePrimary();
  }

  parsePrimary() {
    if (this.match(T_LPAREN)) {
      const node = this.parseLogicalOr();
      this.consume(T_RPAREN, "Expected ')' after expression.");
      return node;
    }
    return this.parseRule();
  }

  parseRule() {
    const fieldToken = this.consume(T_FIELD, "Expected field identifier (e.g. provider.type)");
    const parts = fieldToken.value.split('.');
    if (parts.length !== 2) throw new Error(`Field '${fieldToken.value}' must be in format provider.type`);
    const [provider, ruleType] = parts;

    let operator = 'eq';
    let value = true;

    if (this.check(T_OP)) {
      operator = this.advance().value;
      value = this.parseValue();
    }

    return { type: 'rule', provider, ruleType, operator, value };
  }

  parseValue() {
    if (this.match(T_STRING)) return this.previous().value;
    if (this.match(T_NUMBER)) return this.previous().value;
    if (this.match(T_BOOLEAN)) return this.previous().value;
    if (this.match(T_LBRACKET)) {
      const arr = [];
      if (!this.check(T_RBRACKET)) {
        do { arr.push(this.parseValue()); } while (this.match(T_COMMA));
      }
      this.consume(T_RBRACKET, "Expected ']' after array elements.");
      return arr;
    }
    if (this.match(T_LBRACE)) {
      const obj = {};
      if (!this.check(T_RBRACE)) {
        do {
          const keyToken = this.consume(T_STRING, "Expected string key in object");
          this.consume(T_COLON, "Expected ':' after object key");
          const val = this.parseValue();
          obj[keyToken.value] = val;
        } while (this.match(T_COMMA));
      }
      this.consume(T_RBRACE, "Expected '}' after object.");
      return obj;
    }
    const token = this.peek();
    throw new Error(`Expected value at position ${token.position}. Found: ${token.type}`);
  }

  match(...types) {
    for (const type of types) {
      if (this.check(type)) { this.advance(); return true; }
    }
    return false;
  }

  consume(type, message) {
    if (this.check(type)) return this.advance();
    throw new Error(`${message} Found: ${this.peek().type}`);
  }

  check(type) {
    if (this.isAtEnd()) return false;
    return this.peek().type === type;
  }

  advance() {
    if (!this.isAtEnd()) this.position++;
    return this.previous();
  }

  isAtEnd() { return this.peek().type === T_EOF; }
  peek() { return this.tokens[this.position]; }
  previous() { return this.tokens[this.position - 1]; }
}

export function parseExpression(expression) {
  if (!expression || expression.trim() === '') return null;
  const lexer = new Lexer(expression);
  const parser = new Parser(lexer.tokenize());
  return parser.parse();
}

export function stringifyExpression(ast) {
  if (!ast) return '';
  if (ast.type === 'logical') {
    const left = stringifyExpression(ast.left);
    const right = stringifyExpression(ast.right);
    // Add parentheses for complex nested logic
    const lStr = ast.left.type === 'logical' && ast.left.operator !== ast.operator ? `(${left})` : left;
    const rStr = ast.right.type === 'logical' && ast.right.operator !== ast.operator ? `(${right})` : right;
    return `${lStr} ${ast.operator.toLowerCase()} ${rStr}`;
  }
  if (ast.type === 'not') return `not (${stringifyExpression(ast.node)})`;
  if (ast.type === 'rule') {
    let valStr = '';
    if (typeof ast.value === 'string') valStr = `"${ast.value.replace(/"/g, '\\"')}"`;
    else if (Array.isArray(ast.value)) valStr = `[${ast.value.map(v => typeof v === 'string' ? `"${v.replace(/"/g, '\\"')}"` : v).join(', ')}]`;
    else if (typeof ast.value === 'object' && ast.value !== null) {
      // Serialize basic object literals for rule config
      valStr = `{` + Object.entries(ast.value).map(([k, v]) => `"${k}": ${typeof v === 'string' ? `"${v.replace(/"/g, '\\"')}"` : (Array.isArray(v) ? `[${v.map(item => typeof item === 'string' ? `"${item.replace(/"/g, '\\"')}"` : item).join(', ')}]` : v)}`).join(', ') + `}`;
    }
    else valStr = ast.value;
    return `${ast.provider}.${ast.ruleType} ${ast.operator} ${valStr}`;
  }
  return '';
}
