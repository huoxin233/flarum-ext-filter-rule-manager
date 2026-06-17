import Component, { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
export interface FilterRuleInlineDisplayAttrs extends ComponentAttrs {
    variant: string;
}
/**
 * Renders a single class of inline alert. Accepts `attrs.variant`:
 *
 *   - `banner`         → Inline strip injected at .App-composer > .container
 *                        level, ABOVE the composer. Sized to align with the
 *                        editor (see .FilterRuleManager--banner in LESS).
 *   - `header_banner`  → Inline strip rendered inside ComposerBody.headerItems,
 *                        immediately above the textarea.
 *   - `sidebar`        → Vertical floating card pinned to the right.
 *
 * `toast` and `modal` are handled by FilterRulePopupDispatcher and never reach this
 * component.
 */
export default class FilterRuleInlineDisplay extends Component<FilterRuleInlineDisplayAttrs> {
    dismissedAlerts: Set<string>;
    oninit(vnode: Mithril.Vnode<FilterRuleInlineDisplayAttrs, this>): void;
    view(vnode: Mithril.Vnode<FilterRuleInlineDisplayAttrs, this>): Mithril.Children;
    _matchingItems(variant: string): any[];
    _renderItem(alert: any, i: number, variant: string): Mithril.Children;
}
