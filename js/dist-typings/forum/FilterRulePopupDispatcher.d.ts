import type { FilterEngine } from '../common/FilterEngine';
export interface DisplayedInfo {
    displayMode: string;
    alertKey: unknown;
}
/**
 * Watches the FilterEngine and dispatches non-inline display modes:
 *
 *   - `toast`  → app.alerts.show / app.alerts.dismiss; re-shown if the user
 *                manually dismisses while the rule is still firing
 *   - `modal`  → app.modal.show (once per ruleset firing — re-opens only
 *                after the rule clears and triggers again)
 *
 * `banner` and `sidebar` are rendered inline by FilterRuleInlineDisplay and
 * don't pass through this dispatcher.
 */
export default class FilterRulePopupDispatcher {
    engine: FilterEngine;
    private _displayed;
    private _unsubscribe;
    constructor(engine: FilterEngine);
    dispose(): void;
    dispatch(): void;
    private _maybeShow;
    private _dismiss;
    dismissAll(): void;
}
