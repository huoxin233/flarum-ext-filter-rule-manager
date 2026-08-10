import { AbstractBuiltinRule } from './AbstractBuiltinRule';
export default class WordCountRule extends AbstractBuiltinRule {
    name(): string;
    evaluate(content: string, config: Record<string, unknown>): Record<string, string> | null;
}
