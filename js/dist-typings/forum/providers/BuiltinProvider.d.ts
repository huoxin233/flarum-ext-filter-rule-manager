import type { FilterRuleProvider } from '../../common/FilterEngine';
/**
 * Forum-side BuiltinProvider — handles real-time evaluation against the
 * composer content. Supports four rule types:
 *
 *   contains_word  — config: { words: string[] }
 *   regex          — config: { patterns: string[] }
 *   group          — config: { groupIds: string[] | number[] }
 *   word_count     — config: { min?: number, max?: number, exclude_urls?: boolean, exclude_mentions?: boolean }
 *
 * For contains_word and regex, the rule triggers if ANY of the listed entries matches.
 * The matched value is exposed as a token for message interpolation.
 */
export default class BuiltinProvider implements FilterRuleProvider {
    getSupportedTypes(): string[];
    evaluate(type: string, content: string, config: Record<string, unknown>): Record<string, string> | null;
    /**
     * Normalise `{ plural: string[] }` into a clean, trimmed, non-empty string array.
     */
    normalizeList(config: Record<string, unknown>, plural: string): string[];
}
