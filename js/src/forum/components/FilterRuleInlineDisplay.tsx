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
  isSidebarClosed: boolean = false;

  oninit(vnode: Mithril.Vnode<FilterRuleInlineDisplayAttrs, this>) {
    super.oninit(vnode);
    this.isSidebarClosed = false;
  }

  view(vnode: Mithril.Vnode<FilterRuleInlineDisplayAttrs, this>): Mithril.Children {
    const variant = this.attrs.variant;
    const items = this._matchingItems(variant);
    if (items.length === 0) return null;

    if (variant === 'sidebar') {
      if (this.isSidebarClosed) return null;
      return (
        <aside className="FilterRuleManager FilterRuleManager--sidebar" aria-label="Composer hints">
          <div className="FilterRuleManager-sidebarTitle">
            {icon('fas fa-shield-alt')}
            <span style={{ flex: 1 }}>{app.translator.trans('huoxin-filter-rule-manager.forum.sidebar_title') || 'Composer hints'}</span>
            <button 
              className="Button Button--icon Button--link" 
              onclick={() => this.isSidebarClosed = true}
              style={{ padding: '0', minWidth: '0', minHeight: '0', lineHeight: '1', color: 'inherit' }}
            >
              {icon('fas fa-times')}
            </button>
          </div>
          {items.map((alert, i) => this._renderItem(alert, i, 'sidebar'))}
        </aside>
      );
    }

    return (
      <div className={`FilterRuleManager FilterRuleManager--${variant}`}>
        <div className="FilterRuleManager-banners">
          {items.map((alert, i) => this._renderItem(alert, i, variant))}
        </div>
      </div>
    );
  }

  _matchingItems(variant: string): any[] {
    const isMobile = window.innerWidth <= 768;
    if (isMobile && variant !== 'banner') return [];
    
    const active = filterEngine.activeAlerts
      .filter((a) => isMobile ? true : a.ruleset.displayMode === variant)
      .map((a) => ({
        type: a.ruleset.effectType,
        message: a.message,
        key: `rs-${a.ruleset.id}`,
        displaySettings: a.ruleset.displaySettings,
      }));
    const blocks = filterEngine.blockResults
      .filter((a) => isMobile ? true : a.displayMode === variant)
      .map((a, i) => ({
        type: a.effectType,
        message: a.message,
        key: `block-${i}-${a.message}`,
        displaySettings: null,
      }));
    return [...active, ...blocks];
  }

  _renderItem(alert: any, i: number, variant: string): Mithril.Children {
    const templateName = (alert.displaySettings && alert.displaySettings.template) || 'builtin';
    let TemplateComponent = filterEngine.getTemplate(templateName);
    
    // Fallback to builtin if template is unknown
    if (!TemplateComponent) {
      TemplateComponent = filterEngine.getTemplate('builtin');
    }

    if (!TemplateComponent) return null;

    return (
      <TemplateComponent key={alert.key || i} alert={alert} variant={variant} />
    );
  }
}

