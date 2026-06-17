import Component, { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
export interface BuiltinTemplateSettingsAttrs extends ComponentAttrs {
    displaySetting: (key: string, value?: string) => string;
    effectType: string;
    displayMode: string;
}
export default class BuiltinTemplateSettings extends Component<BuiltinTemplateSettingsAttrs> {
    showIconPicker: boolean;
    titleInput: HTMLInputElement | null;
    oninit(vnode: Mithril.Vnode<BuiltinTemplateSettingsAttrs, this>): void;
    getDefaultStylesForEffect(effect: string): {
        icon: string;
        iconColor: string;
        textColor: string;
        backgroundColor: string;
    };
    view(): Mithril.Children;
}
