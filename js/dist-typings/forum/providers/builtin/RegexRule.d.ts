import { AbstractBuiltinRule } from './AbstractBuiltinRule';
export default class RegexRule extends AbstractBuiltinRule {
    name(): string;
    evaluate(content: string, config: Record<string, unknown>): Record<string, string> | null;
}
