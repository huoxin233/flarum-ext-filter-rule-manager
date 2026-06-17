import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
export interface FilterRuleWarningModalAttrs extends IInternalModalAttrs {
    alerts: Record<string, string>[];
    onconfirm: () => void;
    oncancel: () => void;
}
export default class FilterRuleWarningModal extends Modal<FilterRuleWarningModalAttrs> {
    className(): string;
    title(): Mithril.Children;
    content(): Mithril.Children;
}
