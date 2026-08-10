import type { FilterRuleProvider } from '../../common/FilterEngine';
import type { AbstractBuiltinRule } from './builtin/AbstractBuiltinRule';
/**
 * Forum-side BuiltinProvider — handles real-time evaluation against the
 * composer content.
 */
export default class BuiltinProvider implements FilterRuleProvider {
    protected rules: Record<string, AbstractBuiltinRule>;
    constructor();
    registerRule(rule: AbstractBuiltinRule): void;
    getSupportedTypes(): string[];
    evaluate(type: string, content: string, config: Record<string, unknown>): Record<string, string> | null;
}
