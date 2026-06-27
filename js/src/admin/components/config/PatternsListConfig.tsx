/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import app from 'flarum/admin/app';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import Stream from 'flarum/common/utils/Stream';
import Switch from 'flarum/common/components/Switch';
import type Mithril from 'mithril';

export interface PatternsListConfigAttrs extends ComponentAttrs {
  config?: Record<string, unknown>;
  type: string;
  onchange: (newConfig: Record<string, unknown>) => void;
}

/**
 * Config UI for the builtin `regex` rule type.
 *
 * Rule config shape: { patterns: string[] }
 *
 * Patterns may use bare regex syntax (`foo.*bar`) or PCRE-delimited form
 * (`/foo.*bar/i`) — the evaluator detects which.
 */
export default class PatternsListConfig extends Component<PatternsListConfigAttrs> {
  text!: Stream<string>;
  scanAll!: Stream<boolean>;

  oninit(vnode: Mithril.Vnode<PatternsListConfigAttrs, this>) {
    super.oninit(vnode);

    const cfg = this.attrs.config || {};
    let initial: string[] = [];
    if (Array.isArray(cfg.patterns)) initial = cfg.patterns;
    else if (Array.isArray(cfg.value)) initial = cfg.value;
    else if (typeof cfg.value === 'string' && cfg.value !== '') initial = [cfg.value];
    else if (typeof cfg.pattern === 'string' && cfg.pattern !== '') initial = [cfg.pattern];

    this.text = Stream(initial.join('\n'));
    this.scanAll = Stream(cfg.scan_all || false);
  }

  view(): Mithril.Children {
    return (
      <div className="FilterRuleManager-ConfigForm">
        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.config_patterns_label')}</label>
          <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.config_patterns_help')}</div>
          <textarea
            className="FormControl FilterRuleManager-LinesInput"
            rows={5}
            value={this.text()}
            oninput={(e: Event) => this.handleInput((e.target as HTMLTextAreaElement).value)}
            placeholder={String(app.translator.trans('huoxin-filter-rule-manager.admin.config_patterns_placeholder'))}
          ></textarea>
        </div>
        <hr className="FilterRuleManager-divider" />
        <div className="Form-group">
          <Switch
            state={this.scanAll()}
            onchange={(val: boolean) => {
              this.scanAll(val);
              this.attrs.onchange({ ...(this.attrs.config || {}), scan_all: val });
            }}
          >
            {app.translator.trans('huoxin-filter-rule-manager.admin.rule_scan_all')}
            <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.rule_scan_all_help')}</div>
          </Switch>
        </div>
      </div>
    );
  }

  handleInput(raw: string) {
    this.text(raw);
    const patterns = raw
      .split(/\r?\n/)
      .map((s) => s.trim())
      .filter((s) => s.length > 0);
    const newCfg: Record<string, unknown> = { ...(this.attrs.config || {}), patterns };
    delete newCfg.value;
    delete newCfg.pattern;
    this.attrs.onchange(newCfg);
  }
}
