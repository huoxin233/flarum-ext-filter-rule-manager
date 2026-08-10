export declare abstract class AbstractBuiltinRule {
    abstract name(): string;
    abstract evaluate(content: string, config: Record<string, unknown>): Record<string, string> | null;
    /**
     * Normalise `{ plural: string[] }` into a clean, trimmed, non-empty string array.
     */
    protected normalizeList(config: Record<string, unknown>, plural: string): string[];
}
