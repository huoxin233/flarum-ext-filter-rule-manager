import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
export interface FilterRuleModalAttrs extends IInternalModalAttrs {
    type: string;
    message: string;
    displaySettings?: Record<string, unknown>;
}
/**
 * Generic alert modal used by the FilterRulePopupDispatcher when a ruleset's
 * `displayMode` is `modal`. Shown once per ruleset firing — the dispatcher
 * tracks which ruleset IDs are already on screen so it doesn't re-open
 * the modal on every 300ms poll tick.
 */
export default class FilterRuleModal extends Modal<FilterRuleModalAttrs> {
    className(): string;
    title(): Mithril.Children;
    content(): Mithril.Children;
}
