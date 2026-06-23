import { IFormModalAttrs } from 'flarum/common/components/FormModal';
import FormModal from 'flarum/common/components/FormModal';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
import type Model from 'flarum/common/Model';
export interface RulesetEditorModalAttrs extends IFormModalAttrs {
    ruleset?: Model & Record<string, any>;
    providers: Record<string, unknown>[];
    onsave?: () => void;
}
/**
 * Sectioned editor for a ruleset:
 *
 *   1. General   — name, priority, active toggle
 *   2. Scope    — global/normal/private/tag (+ tag IDs when applicable)
 *   3. Display  — intervention, display mode, message + token hint chips + preview
 *   4. Rules    — operator + RuleBuilder
 *
 * Token hints below the message textarea are discovered from each configured
 * rule's provider. Frontend-only providers expose them via
 * `provider.getProvidedTokens(type)`; backend providers ship them through the
 * `/filter-rule-providers` endpoint, attached to each (provider, type) row
 * in `this.providers`. Clicking a chip inserts `{{token}}` at the textarea
 * cursor.
 */
export default class RulesetEditorModal extends FormModal<RulesetEditorModalAttrs> {
    static readonly isDismissibleViaBackdropClick = false;
    static readonly isDismissibleViaEscKey = false;
    ruleset?: Model & Record<string, any>;
    providers: Record<string, unknown>[];
    loading: boolean;
    messageTextarea: HTMLTextAreaElement | null;
    flagMessageTextarea: HTMLTextAreaElement | null;
    showIconPicker: boolean;
    tagsLoading: boolean;
    showingDiscardConfirmation: boolean;
    availableTags: Model[];
    closeIconPickerHandler: ((e: MouseEvent) => void) | null;
    name: Stream<string>;
    expression: Stream<string>;
    interventionType: Stream<string>;
    displayMode: Stream<string>;
    message: Stream<string>;
    flagMessage: Stream<string>;
    evaluateAllRules: Stream<boolean>;
    evaluateTitle: Stream<boolean | null>;
    evasionActive: Stream<boolean | null>;
    evasionTimeout: Stream<number | null>;
    evasionThreshold: Stream<number | null>;
    blockCascade: Stream<boolean>;
    isActive: Stream<boolean>;
    autoFlag: Stream<boolean | null>;
    requireApproval: Stream<boolean | null>;
    scopeType: Stream<string>;
    scopeTagIds: Stream<number[]>;
    bypassGroupIds: Stream<number[]>;
    displaySettings: Stream<Record<string, unknown>>;
    oninit(vnode: Mithril.Vnode<RulesetEditorModalAttrs, this>): void;
    oncreate(vnode: Mithril.VnodeDOM<RulesetEditorModalAttrs, this>): void;
    onremove(vnode: Mithril.VnodeDOM<RulesetEditorModalAttrs, this>): void;
    className(): string;
    title(): string | any[];
    content(): Mithril.Children;
    nullableBooleanSelect(labelKey: string, helpKey: string, stream: Stream<boolean | null>): Mithril.Children;
    generalSection(): Mithril.Children;
    scopeSection(): Mithril.Children;
    displaySetting(key: string, val?: unknown): unknown;
    displaySection(): Mithril.Children;
    moderationSection(): Mithril.Children;
    rulesSection(): Mithril.Children;
    availableTokens(): Record<string, unknown>[];
    tokenChipsBlock(tokens: Record<string, unknown>[], targetField: string | ((name: string) => void)): Mithril.Children;
    insertToken(name: string, targetField: string | ((name: string) => void)): void;
    previewBlock(intervention: string, message: string): Mithril.Children;
    validationBlock(): Mithril.Children;
    actionsBlock(): Mithril.Children;
    interventionIcon(intervention: string): "fas fa-exclamation-triangle" | "fas fa-info-circle" | "fas fa-ban" | "fas fa-user-secret";
    isDirty(): boolean;
    hide(): void;
    canSave(): boolean;
    validationError(): string | any[] | null;
    onsubmit(e: Event): void;
    save(): Promise<void>;
}
