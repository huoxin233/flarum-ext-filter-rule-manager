import type { FilterRuleProvider } from '../../common/FilterEngine';
/**
 * Forum-side BuiltinProvider — handles real-time evaluation against the
 * composer content. Supports two rule types:
 *
 *   contains_word  — config: { words: string[] }
 *   regex          — config: { patterns: string[] }
 *
 * For each type, the rule triggers if ANY of the listed entries matches.
 * The first match's value is exposed as a token for message interpolation.
 */
export default class BuiltinProvider implements FilterRuleProvider {
    getSupportedTypes(): string[];
    evaluate(type: string, content: string, config: Record<string, unknown>): Record<string, string> | null;
    /**
     * Normalise `{ plural: string[] }` into a clean, trimmed, non-empty string array.
     */
    normalizeList(config: Record<string, unknown>, plural: string): string[];
}
