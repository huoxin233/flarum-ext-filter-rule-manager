/// <reference types="mithril" />
import Component from 'flarum/common/Component';
export default class WordCountConfig extends Component {
    min: string;
    max: string;
    excludeMentions: boolean;
    oninit(vnode: any): void;
    view(): JSX.Element;
    updateConfig(key: string, value: any): void;
}
