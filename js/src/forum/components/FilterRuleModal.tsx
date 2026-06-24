/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import app from 'flarum/forum/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
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
  className(): string {
    const type = this.attrs.type || 'info';
    return `FilterRuleModal Modal--small FilterRuleModal--${type}`;
  }

  title(): Mithril.Children {
    const type = this.attrs.type || 'info';
    const settings = this.attrs.displaySettings || {};
    if (settings.title) return settings.title as string;
    return app.translator.trans(`huoxin-filter-rule-manager.forum.modal_title_${type}`);
  }

  content(): Mithril.Children {
    const type = this.attrs.type || 'info';
    const settings = this.attrs.displaySettings || {};

    let iconClass = settings.icon;
    if (!iconClass) {
      iconClass = type === 'block' ? 'fas fa-times-circle' : type === 'warning' ? 'fas fa-exclamation-triangle' : 'fas fa-info-circle';
    }

    const style: Record<string, string> = {};
    if (settings.textColor && settings.textColor !== 'transparent') style.color = settings.textColor as string;
    if (settings.backgroundColor && settings.backgroundColor !== 'transparent') style.backgroundColor = settings.backgroundColor as string;
    if (settings.backgroundColor === 'transparent') {
      style.backgroundColor = 'transparent';
      style.boxShadow = 'none';
      style.border = 'none';
    }

    const iconStyle: Record<string, string> = {};
    if (settings.iconColor && settings.iconColor !== 'transparent') iconStyle.color = settings.iconColor as string;

    return (
      <div className="Modal-body">
        <div className={`FilterRuleModal-message FilterRuleModal-message--${type}`} style={style}>
          {settings.icon !== 'none' && <i className={`FilterRuleModal-icon ${iconClass}`} style={iconStyle}></i>}
          <span className="FilterRuleModal-text">{m.trust(this.attrs.message)}</span>
        </div>
        <div className="Form-group FilterRuleModal-actions">
          <Button className="Button Button--primary" onclick={() => this.hide()}>
            {app.translator.trans('huoxin-filter-rule-manager.forum.modal_ok')}
          </Button>
        </div>
      </div>
    );
  }
}
