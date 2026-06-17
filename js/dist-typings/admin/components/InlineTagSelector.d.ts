import Component, { ComponentAttrs } from 'flarum/common/Component';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
export interface Tag {
    id: () => string | number;
    name: () => string;
    icon: () => string | null;
    color: () => string | null;
    position: () => number | null | undefined;
    isChild: () => boolean;
    parent: () => Tag | null;
}
export interface InlineTagSelectorAttrs extends ComponentAttrs {
    tags?: Tag[];
    selectedIds: Stream<number[]>;
}
export default class InlineTagSelector extends Component<InlineTagSelectorAttrs> {
    tags: Tag[];
    selectedIds: Stream<number[]>;
    oninit(vnode: Mithril.Vnode<InlineTagSelectorAttrs, this>): void;
    view(): Mithril.Children;
    renderTag(tag: Tag, children: Tag[]): Mithril.Children;
    toggleTag(id: number, checked: boolean): void;
}
