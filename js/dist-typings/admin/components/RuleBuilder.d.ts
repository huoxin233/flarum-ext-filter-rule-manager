import Component, { ComponentAttrs } from 'flarum/common/Component';
import { ASTNode } from '../../common/FilterEngine';
import type Mithril from 'mithril';
export interface FrontendProvider {
    provider: string;
    providerLabel?: string;
    type: string;
    label?: string;
    tokens?: string[];
}
export interface RuleBuilderAttrs extends ComponentAttrs {
    expression?: string;
    onchange?: (v: string) => void;
    providers?: FrontendProvider[];
}
export default class RuleBuilder extends Component<RuleBuilderAttrs> {
    mode: string;
    expression: string;
    ast: ASTNode | null;
    parseError: string | null;
    oninit(vnode: Mithril.Vnode<RuleBuilderAttrs, this>): void;
    syncToVisual(): void;
    syncToEditor(): void;
    emit(): void;
    view(): Mithril.Children;
}
