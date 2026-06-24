/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import app from 'flarum/common/app';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import icon from 'flarum/common/helpers/icon';
import type Mithril from 'mithril';

export interface BuiltinTemplateAttrs extends ComponentAttrs {
  alert: {
    type?: string;
    message: string;
    displaySettings?: {
      icon?: string;
      textColor?: string;
      backgroundColor?: string;
      iconColor?: string;
      title?: string;
    };
  };
  variant: string;
}

export default class BuiltinTemplate extends Component<BuiltinTemplateAttrs> {
  view(vnode: Mithril.Vnode<BuiltinTemplateAttrs, this>): Mithril.Children {
    const { alert, variant } = this.attrs;
    const settings = alert.displaySettings || {};

    let iconName = settings.icon;
    if (!iconName) {
      iconName = alert.type === 'block' ? 'fas fa-times-circle' : alert.type === 'warning' ? 'fas fa-exclamation-triangle' : 'fas fa-info-circle';
    }

    // Use React.CSSProperties-like typing or just any for React/Mithril inline styles
    const style: Record<string, string> = {};
    if (settings.textColor) style.color = settings.textColor;
    if (settings.backgroundColor) style.backgroundColor = settings.backgroundColor;

    const iconStyle: Record<string, string> = {};
    if (settings.iconColor) iconStyle.color = settings.iconColor;

    return (
      <div className={`FilterRuleManager-item FilterRuleManager-item--${variant} FilterRuleManager-item--${alert.type}`} style={style}>
        {iconName !== 'none' && (
          <span className="FilterRuleManager-item-icon" style={iconStyle}>
            {icon(iconName)}
          </span>
        )}
        <div className="FilterRuleManager-item-content">
          {settings.title && <strong className="FilterRuleManager-item-title">{settings.title as string}</strong>}
          <span className="FilterRuleManager-item-message">{m.trust(alert.message)}</span>
        </div>
      </div>
    );
  }
}
