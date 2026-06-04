import FilterRuleModal from './components/FilterRuleModal';

/**
 * Watches the FilterEngine and dispatches non-inline display modes:
 *
 *   - `toast`  → app.alerts.show / app.alerts.dismiss; re-shown if the user
 *                manually dismisses while the rule is still firing
 *   - `modal`  → app.modal.show (once per ruleset firing — re-opens only
 *                after the rule clears and triggers again)
 *
 * `banner` and `sidebar` are rendered inline by FilterRuleBanner and
 * don't pass through this dispatcher.
 */
export default class RuleDispatcher {
  constructor(engine) {
    this.engine = engine;
    // ruleset id (or block message hash) → { displayMode, alertKey }
    this._displayed = new Map();
    this._unsubscribe = engine.subscribe(() => this.dispatch());
  }

  dispose() {
    if (typeof this._unsubscribe === 'function') this._unsubscribe();
    this.dismissAll();
  }

  dispatch() {
    const app = (typeof window !== 'undefined' && window.app) || null;
    if (!app || !app.alerts || !app.modal) return;

    const seen = new Set();

    // 1) frontend-evaluated (info/warning) alerts
    for (const alert of this.engine.activeAlerts) {
      const id = `rs-${alert.ruleset.id}`;
      const displayMode = alert.ruleset.displayMode;
      const type = alert.ruleset.effectType === 'warning' ? 'warning' : 'info';
      seen.add(id);
      this._maybeShow(id, displayMode, type, alert.message);
    }

    // 2) server-evaluated (block) alerts
    this.engine.blockResults.forEach((alert, i) => {
      const id = `block-${i}-${alert.message}`;
      seen.add(id);
      this._maybeShow(id, alert.displayMode, 'block', alert.message);
    });

    // 3) tear down anything that's no longer firing
    for (const id of Array.from(this._displayed.keys())) {
      if (!seen.has(id)) this._dismiss(id);
    }
  }

  _maybeShow(id, displayMode, type, message) {
    if (displayMode !== 'toast' && displayMode !== 'modal') return;

    const app = window.app;
    const existing = this._displayed.get(id);

    if (existing) {
      // Toasts: if the user dismissed it by hand, app.alerts no longer holds
      // our key — re-display while the rule is still firing so the alert
      // stays persistent. Modals: always treat as "still shown" since
      // re-opening on every poll tick is hostile UX.
      if (existing.displayMode === 'toast' && this._isToastDismissed(app, existing.alertKey)) {
        this._displayed.delete(id);
        // fall through to re-show
      } else {
        return;
      }
    }

    if (displayMode === 'toast') {
      const alertKey = app.alerts.show(
        {
          type: type === 'block' ? 'error' : type,
          dismissible: true,
        },
        m.trust(message)
      );
      this._displayed.set(id, { displayMode, alertKey });
      return;
    }

    if (displayMode === 'modal') {
      app.modal.show(FilterRuleModal, { message, type });
      this._displayed.set(id, { displayMode, alertKey: null });
      return;
    }
  }

  /**
   * Returns true if our previously-shown toast is no longer in the alert
   * manager's active set (i.e. the user clicked the X). Flarum's
   * AlertManager exposes `activeAlerts` as an object keyed by id.
   */
  _isToastDismissed(app, alertKey) {
    if (alertKey == null) return false;
    if (!app.alerts || !app.alerts.activeAlerts) return false;
    return !Object.prototype.hasOwnProperty.call(app.alerts.activeAlerts, alertKey);
  }

  _dismiss(id) {
    const info = this._displayed.get(id);
    if (!info) return;
    const app = (typeof window !== 'undefined' && window.app) || null;
    if (info.displayMode === 'toast' && info.alertKey != null && app && app.alerts) {
      try { app.alerts.dismiss(info.alertKey); } catch (e) { /* ignore */ }
    }
    this._displayed.delete(id);
  }

  dismissAll() {
    for (const id of Array.from(this._displayed.keys())) this._dismiss(id);
  }
}
