import Component, { ComponentAttrs } from 'flarum/common/Component';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
export interface WordsListConfigAttrs extends ComponentAttrs {
    config?: Record<string, unknown>;
    type: string;
    onchange: (newConfig: Record<string, unknown>) => void;
}
/**
 * Config UI for the builtin `contains_word` rule type.
 *
 * Rule config shape: { words: string[] }
 *
 * The raw textarea text is held in component state so the user's cursor and
 * partial input survive across redraws — the parsed `words` array is pushed
 * to onchange on every keystroke, but the displayed value mirrors what the
 * user typed (blank lines and all) until they leave the field.
 */
export default class WordsListConfig extends Component<WordsListConfigAttrs> {
    text: Stream<string>;
    scanAll: Stream<boolean>;
    oninit(vnode: Mithril.Vnode<WordsListConfigAttrs, this>): void;
    view(): Mithril.Children;
    handleInput(val: string): void;
}
