import Component, { ComponentAttrs } from 'flarum/common/Component';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
export interface PatternsListConfigAttrs extends ComponentAttrs {
    config?: Record<string, unknown>;
    type: string;
    onchange: (newConfig: Record<string, unknown>) => void;
}
/**
 * Config UI for the builtin `regex` rule type.
 *
 * Rule config shape: { patterns: string[] }
 *
 * Patterns may use bare regex syntax (`foo.*bar`) or PCRE-delimited form
 * (`/foo.*bar/i`) — the evaluator detects which.
 */
export default class PatternsListConfig extends Component<PatternsListConfigAttrs> {
    text: Stream<string>;
    scanAll: Stream<boolean>;
    oninit(vnode: Mithril.Vnode<PatternsListConfigAttrs, this>): void;
    view(): Mithril.Children;
    handleInput(raw: string): void;
}
