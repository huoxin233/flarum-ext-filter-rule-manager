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

export interface WordsListConfigAttrs extends ComponentAttrs {
  config?: Record<string, unknown>;
  type: string;
  onchange: (newConfig: Record<string, unknown>) => void;
}

/**
 * Config UI for the builtin `contains_word` rule type.
 *
 * Rule config shape: { words: string[] }
 *
 * The raw textarea text is held in component state so the user's cursor and
 * partial input survive across redraws — the parsed `words` array is pushed
 * to onchange on every keystroke, but the displayed value mirrors what the
 * user typed (blank lines and all) until they leave the field.
 */
export default class WordsListConfig extends Component<WordsListConfigAttrs> {
  text!: Stream<string>;
  scanAll!: Stream<boolean>;

  oninit(vnode: Mithril.Vnode<WordsListConfigAttrs, this>) {
    super.oninit(vnode);

    const cfg = this.attrs.config || {};
    let initial: string[] = [];
    if (Array.isArray(cfg.words)) initial = cfg.words;
    else if (Array.isArray(cfg.value)) initial = cfg.value;
    else if (typeof cfg.value === 'string' && cfg.value !== '') initial = [cfg.value];
    else if (typeof cfg.word === 'string' && cfg.word !== '') initial = [cfg.word];

    this.text = Stream(initial.join('\n'));
    this.scanAll = Stream(cfg.scan_all || false);
  }

  view(): Mithril.Children {
    return (
      <div className="FilterRuleManager-ConfigForm">
        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.config_words_label')}</label>
          <textarea
            className="FormControl FilterRuleManager-LinesInput"
            rows={5}
            value={this.text()}
            oninput={(e: Event) => this.handleInput((e.target as HTMLTextAreaElement).value)}
            placeholder={String(app.translator.trans('huoxin-filter-rule-manager.admin.config_words_placeholder'))}
          ></textarea>
          <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.config_words_help')}</div>
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
          </Switch>
          <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.rule_scan_all_help')}</div>
        </div>
      </div>
    );
  }

  handleInput(val: string) {
    this.text(val);
    const words = val
      .split('\n')
      .map((w) => w.trim())
      .filter((w) => w.length > 0);
    const newCfg: Record<string, unknown> = { ...(this.attrs.config || {}), words };
    delete newCfg.value;
    delete newCfg.word;
    this.attrs.onchange(newCfg);
  }
}
