import type { ASTNode } from '../../common/FilterEngine';
export declare const T_AND = "AND";
export declare const T_OR = "OR";
export declare const T_NOT = "NOT";
export declare const T_LPAREN = "LPAREN";
export declare const T_RPAREN = "RPAREN";
export declare const T_LBRACKET = "LBRACKET";
export declare const T_RBRACKET = "RBRACKET";
export declare const T_COMMA = "COMMA";
export declare const T_FIELD = "FIELD";
export declare const T_OP = "OP";
export declare const T_STRING = "STRING";
export declare const T_NUMBER = "NUMBER";
export declare const T_BOOLEAN = "BOOLEAN";
export declare const T_LBRACE = "LBRACE";
export declare const T_RBRACE = "RBRACE";
export declare const T_COLON = "COLON";
export declare const T_EOF = "EOF";
export interface Token {
    type: string;
    value: unknown;
    position: number;
}
export declare class Parser {
    tokens: Token[];
    length: number;
    position: number;
    constructor(tokens: Token[]);
    parse(): ASTNode | null;
    parseLogicalOr(): ASTNode;
    parseLogicalAnd(): ASTNode;
    parseUnary(): ASTNode;
    parsePrimary(): ASTNode;
    parseRule(): ASTNode;
    parseValue(): unknown;
    match(...types: string[]): boolean;
    consume(type: string, message: string): Token;
    check(type: string): boolean;
    advance(): Token;
    isAtEnd(): boolean;
    peek(): Token;
    previous(): Token;
}
export declare function parseExpression(expression: string | null | undefined): ASTNode | null;
export declare function stringifyExpression(ast: ASTNode | null | undefined): string;
