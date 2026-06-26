/// <reference types="mithril" />
import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
export interface IWordCountConfigAttrs extends ComponentAttrs {
    config?: Record<string, any>;
    onchange: (config: Record<string, any>) => void;
}
export default class WordCountConfig extends Component<IWordCountConfigAttrs> {
    min: string;
    max: string;
    excludeMentions: boolean;
    oninit(vnode: any): void;
    view(): JSX.Element;
    updateConfig(key: string, value: any): void;
}
