/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Switch from 'flarum/common/components/Switch';

export interface IWordCountConfigAttrs extends ComponentAttrs {
  config?: Record<string, any>;
  onchange: (config: Record<string, any>) => void;
}

export default class WordCountConfig extends Component<IWordCountConfigAttrs> {
  min!: string;
  max!: string;
  excludeMentions!: boolean;
  excludeUrls!: boolean;

  oninit(vnode: any) {
    super.oninit(vnode);
    const config = this.attrs.config || {};
    this.min = config.min !== undefined ? String(config.min) : '';
    this.max = config.max !== undefined ? String(config.max) : '';
    this.excludeMentions = config.exclude_mentions ?? true;
    this.excludeUrls = config.exclude_urls ?? true;
  }

  view() {
    return (
      <div className="FilterRuleManager-ConfigForm">
        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.config_word_count_min')}</label>
          <input
            className="FormControl"
            type="number"
            min="0"
            value={this.min}
            oninput={(e: InputEvent) => this.updateConfig('min', (e.target as HTMLInputElement).value)}
            placeholder="e.g. 10"
          />
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.config_word_count_max')}</label>
          <input
            className="FormControl"
            type="number"
            min="1"
            value={this.max}
            oninput={(e: InputEvent) => this.updateConfig('max', (e.target as HTMLInputElement).value)}
            placeholder="e.g. 500"
          />
        </div>

        <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.config_word_count_help')}</div>

        <hr className="FilterRuleManager-divider" />

        <div className="Form-group">
          <Switch state={this.excludeMentions} onchange={(val: boolean) => this.updateConfig('exclude_mentions', val)}>
            {app.translator.trans('huoxin-filter-rule-manager.admin.config_exclude_mentions')}
          </Switch>
          <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.config_exclude_mentions_help')}</div>
        </div>

        <div className="Form-group">
          <Switch state={this.excludeUrls} onchange={(val: boolean) => this.updateConfig('exclude_urls', val)}>
            {app.translator.trans('huoxin-filter-rule-manager.admin.config_exclude_urls')}
          </Switch>
          <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.config_exclude_urls_help')}</div>
        </div>
      </div>
    );
  }

  updateConfig(key: string, value: any) {
    const config = { ...(this.attrs.config || {}) };

    if (key === 'min' || key === 'max') {
      if (value === '') {
        delete config[key];
      } else {
        config[key] = parseInt(value, 10);
      }
    } else {
      config[key] = value;
    }

    // Also update local state so UI reflects
    if (key === 'min') this.min = value;
    if (key === 'max') this.max = value;
    if (key === 'exclude_mentions') this.excludeMentions = value;
    if (key === 'exclude_urls') this.excludeUrls = value;

    this.attrs.onchange(config);
  }
}
