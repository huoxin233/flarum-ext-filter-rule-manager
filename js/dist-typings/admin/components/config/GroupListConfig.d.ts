import Component, { ComponentAttrs } from 'flarum/common/Component';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
export interface GroupListConfigAttrs extends ComponentAttrs {
    config?: Record<string, unknown>;
    type: string;
    onchange: (newConfig: Record<string, unknown>) => void;
}
/**
 * Config UI for the builtin `group` rule type.
 *
 * Rule config shape: { groupIds: number[] }
 */
export default class GroupListConfig extends Component<GroupListConfigAttrs> {
    groupIds: Stream<number[]>;
    oninit(vnode: Mithril.Vnode<GroupListConfigAttrs, this>): void;
    view(): Mithril.Children;
}
