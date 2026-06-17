import Component, { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
export interface BuiltinTemplateAttrs extends ComponentAttrs {
    alert: {
        type?: string;
        message: string;
        displaySettings?: {
            icon?: string;
            textColor?: string;
            backgroundColor?: string;
            iconColor?: string;
            title?: string;
        };
    };
    variant: string;
}
export default class BuiltinTemplate extends Component<BuiltinTemplateAttrs> {
    view(vnode: Mithril.Vnode<BuiltinTemplateAttrs, this>): Mithril.Children;
}
