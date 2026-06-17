/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import app from 'flarum/forum/app';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import filterEngine from '../../common/FilterEngine';
import icon from 'flarum/common/helpers/icon';
import type Mithril from 'mithril';

export interface FilterRuleInlineDisplayAttrs extends ComponentAttrs {
  variant: string;
}

/**
 * Renders a single class of inline alert. Accepts `attrs.variant`:
 *
 *   - `banner`         → Inline strip injected at .App-composer > .container
 *                        level, ABOVE the composer. Sized to align with the
 *                        editor (see .FilterRuleManager--banner in LESS).
 *   - `header_banner`  → Inline strip rendered inside ComposerBody.headerItems,
 *                        immediately above the textarea.
 *   - `sidebar`        → Vertical floating card pinned to the right.
 *
 * `toast` and `modal` are handled by FilterRulePopupDispatcher and never reach this
 * component.
 */
export default class FilterRuleInlineDisplay extends Component<FilterRuleInlineDisplayAttrs> {
  dismissedAlerts: Set<string> = new Set();

  oninit(vnode: Mithril.Vnode<FilterRuleInlineDisplayAttrs, this>) {
    super.oninit(vnode);
    this.dismissedAlerts.clear();
  }

  view(vnode: Mithril.Vnode<FilterRuleInlineDisplayAttrs, this>): Mithril.Children {
    const variant = this.attrs.variant;
    const items = this._matchingItems(variant);
    if (items.length === 0) return null;

    if (variant === 'sidebar') {
      return (
        <aside className="FilterRuleManager FilterRuleManager--sidebar" aria-label="Composer hints">
          {items.map((alert, i) => this._renderItem(alert, i, 'sidebar'))}
        </aside>
      );
    }

    return (
      <div className={`FilterRuleManager FilterRuleManager--${variant}`}>
        <div className="FilterRuleManager-banners">{items.map((alert, i) => this._renderItem(alert, i, variant))}</div>
      </div>
    );
  }

  _matchingItems(variant: string): any[] {
    const isMobile = window.innerWidth <= 768;
    if (isMobile && variant !== 'banner') return [];

    const active = filterEngine.activeAlerts
      .filter((a) => (isMobile ? true : a.ruleset.displayMode === variant))
      .map((a) => ({
        type: a.ruleset.interventionType,
        message: a.message,
        key: `rs-${a.ruleset.id}`,
        displaySettings: a.displaySettings,
      }));
    const blocks = filterEngine.blockResults
      .filter((a) => (isMobile ? true : a.displayMode === variant))
      .map((a, i) => ({
        type: a.interventionType,
        message: a.message,
        key: `block-${i}-${a.message}`,
        displaySettings: null,
      }));
    return [...active, ...blocks].filter((a) => !this.dismissedAlerts.has(a.key));
  }

  _renderItem(alert: any, i: number, variant: string): Mithril.Children {
    const templateName = (alert.displaySettings && alert.displaySettings.template) || 'builtin';
    let TemplateComponent = filterEngine.getTemplate(templateName);

    // Fallback to builtin if template is unknown
    if (!TemplateComponent) {
      TemplateComponent = filterEngine.getTemplate('builtin');
    }

    if (!TemplateComponent) return null;

    const TemplateComp = TemplateComponent as any;
    const content = <TemplateComp alert={alert} variant={variant} />;

    if (variant === 'sidebar') {
      return (
        <div key={alert.key || i} className="FilterRuleManager-item-wrapper">
          {content}
          <button
            className="Button Button--icon Button--link FilterRuleManager-item-dismiss"
            onclick={() => {
              this.dismissedAlerts.add(alert.key || String(i));
            }}
            title={String(app.translator.trans('core.lib.error.dismiss_button')) || 'Dismiss'}
          >
            {icon('fas fa-times')}
          </button>
        </div>
      );
    }

    return <div key={alert.key || i}>{content}</div>;
  }
}
