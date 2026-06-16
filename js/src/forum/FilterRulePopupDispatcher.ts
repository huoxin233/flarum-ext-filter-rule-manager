/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import app from 'flarum/forum/app';
import FilterRuleModal from './components/FilterRuleModal';
import type { FilterEngine } from '../common/FilterEngine';

export interface DisplayedInfo {
  displayMode: string;
  alertKey: any;
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
  public engine: FilterEngine;
  private _displayed: Map<string, DisplayedInfo>;
  private _unsubscribe: () => void;

  constructor(engine: FilterEngine) {
    this.engine = engine;
    this._displayed = new Map();
    this._unsubscribe = engine.subscribe(() => this.dispatch());
  }

  dispose(): void {
    if (typeof this._unsubscribe === 'function') this._unsubscribe();
    this.dismissAll();
  }

  dispatch(): void {
    const application = (typeof window !== 'undefined' && (window as any).app) || null;
    if (!application || !application.alerts || !application.modal) return;

    const seen = new Set<string>();

    for (const alert of this.engine.activeAlerts) {
      const id = `rs-${alert.ruleset.id}`;
      const displayMode = alert.ruleset.displayMode;
      const type = alert.ruleset.effectType === 'warning' ? 'warning' : 'info';
      const settings = alert.ruleset.displaySettings || {};
      seen.add(id);
      this._maybeShow(id, displayMode, type, alert.message, settings);
    }

    this.engine.blockResults.forEach((alert, i) => {
      const id = `block-${i}-${alert.message}`;
      const settings = alert.displaySettings || {};
      seen.add(id);
      this._maybeShow(id, alert.displayMode, 'block', alert.message, settings);
    });

    for (const id of Array.from(this._displayed.keys())) {
      if (!seen.has(id)) this._dismiss(id);
    }
  }

  private _maybeShow(id: string, displayMode: string, type: string, message: string, displaySettings: any = {}): void {
    if (displayMode !== 'toast' && displayMode !== 'modal') return;

    const application = (window as any).app;
    const existing = this._displayed.get(id);

    if (existing) {
      return;
    }

    if (displayMode === 'toast') {
      const defaultToastType = type === 'block' ? 'error' : type;
      const alertAttrs: any = {
        type: displaySettings.toastTheme || defaultToastType,
        dismissible: true,
      };

      if (displaySettings.icon && displaySettings.icon !== 'none') {
        alertAttrs.icon = displaySettings.icon;
      }

      if (displaySettings.title) {
        alertAttrs.title = application.translator.trans(displaySettings.title);
      }

      const alertKey = application.alerts.show(alertAttrs, (window as any).m.trust(message));
      this._displayed.set(id, { displayMode, alertKey });
      return;
    }

    if (displayMode === 'modal') {
      application.modal.show(FilterRuleModal, { message, type, displaySettings });
      this._displayed.set(id, { displayMode, alertKey: null });
      return;
    }
  }

  private _dismiss(id: string): void {
    const info = this._displayed.get(id);
    if (!info) return;

    const application = (window as any).app;

    if (info.displayMode === 'toast' && info.alertKey != null && application && application.alerts) {
      try {
        application.alerts.dismiss(info.alertKey);
      } catch (e) {
        /* ignore */
      }
    }
    this._displayed.delete(id);
  }

  dismissAll(): void {
    for (const id of Array.from(this._displayed.keys())) this._dismiss(id);
  }
}
