import app from 'flarum/forum/app';
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
      const settings = alert.ruleset.displaySettings || {};
      seen.add(id);
      this._maybeShow(id, displayMode, type, alert.message, settings);
    }

    // 2) server-evaluated (block) alerts
    this.engine.blockResults.forEach((alert, i) => {
      const id = `block-${i}-${alert.message}`;
      const settings = alert.displaySettings || {};
      seen.add(id);
      this._maybeShow(id, alert.displayMode, 'block', alert.message, settings);
    });

    // 3) tear down anything that's no longer firing
    for (const id of Array.from(this._displayed.keys())) {
      if (!seen.has(id)) this._dismiss(id);
    }
  }

  _maybeShow(id, displayMode, type, message, displaySettings = {}) {
    if (displayMode !== 'toast' && displayMode !== 'modal') return;

    const app = window.app;
    const existing = this._displayed.get(id);

    if (existing) {
      return;
    }

    if (displayMode === 'toast') {
      const defaultToastType = type === 'block' ? 'error' : type;
      const alertAttrs = {
        type: displaySettings.toastTheme || defaultToastType,
        dismissible: true,
      };

      if (displaySettings.icon && displaySettings.icon !== 'none') {
        alertAttrs.icon = displaySettings.icon;
      }
      
      if (displaySettings.title) {
        alertAttrs.title = app.translator.trans(displaySettings.title);
      }

      const alertKey = app.alerts.show(
        alertAttrs,
        m.trust(message)
      );
      this._displayed.set(id, { displayMode, alertKey });
      return;
    }

    if (displayMode === 'modal') {
      app.modal.show(FilterRuleModal, { message, type, displaySettings });
      this._displayed.set(id, { displayMode, alertKey: null });
      return;
    }
  }

  _dismiss(id) {
    const info = this._displayed.get(id);
    if (!info) return;

    if (info.displayMode === 'toast' && info.alertKey != null && app && app.alerts) {
      try { app.alerts.dismiss(info.alertKey); } catch (e) { /* ignore */ }
    }
    this._displayed.delete(id);
  }

  dismissAll() {
    for (const id of Array.from(this._displayed.keys())) this._dismiss(id);
  }
}
