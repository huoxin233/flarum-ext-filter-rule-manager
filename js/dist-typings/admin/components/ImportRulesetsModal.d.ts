/// <reference types="mithril" />
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
interface ImportRulesetsModalAttrs extends IInternalModalAttrs {
    onsuccess?: () => void;
}
export default class ImportRulesetsModal extends Modal<ImportRulesetsModalAttrs> {
    jsonPayload: string;
    overrideMode: boolean;
    overrideConfirmed: boolean;
    preservePriority: boolean;
    loading: boolean;
    className(): string;
    title(): string | any[];
    content(): JSX.Element;
    toggleOverride(val: boolean): void;
    handleFileUpload(e: Event): void;
    onsubmit(e: Event): void;
}
export {};
