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
import Select from 'flarum/common/components/Select';
import Icon from 'flarum/common/components/Icon';
import type Mithril from 'mithril';

export interface IRuleTriggeredConfigAttrs extends ComponentAttrs {
  config?: Record<string, any>;
  onchange: (config: Record<string, any>) => void;
}

export default class RuleTriggeredConfig extends Component<IRuleTriggeredConfigAttrs> {
  matchRuleId!: string;
  matchName!: string;
  matchScope!: string;
  matchIntervention!: string;
  matchDisplay!: string;

  oninit(vnode: Mithril.Vnode<IRuleTriggeredConfigAttrs, this>) {
    super.oninit(vnode);
    const config = this.attrs.config || {};
    this.matchRuleId = config.match_rule_id !== undefined ? String(config.match_rule_id) : '';
    this.matchName = config.match_name !== undefined ? String(config.match_name) : '';
    this.matchScope = config.match_scope !== undefined ? String(config.match_scope) : '';
    this.matchIntervention = config.match_intervention !== undefined ? String(config.match_intervention) : '';
    this.matchDisplay = config.match_display !== undefined ? String(config.match_display) : '';
  }

  view(): Mithril.Children {
    const rulesets = app.store.all('filter-rule-rulesets') || [];
    const ruleOptions: Record<string, string> = {
      '': String(app.translator.trans('huoxin-filter-rule-manager.admin.config_rule_triggered_any')),
    };
    rulesets.forEach((r: any) => {
      ruleOptions[`id_${r.id()}`] = String(r.name() || `Rule #${r.id()}`);
    });

    const scopeOptions: Record<string, string> = {
      '': String(app.translator.trans('huoxin-filter-rule-manager.admin.config_rule_triggered_any')),
      global: String(app.translator.trans('huoxin-filter-rule-manager.admin.scopes.global')),
      normal_post: String(app.translator.trans('huoxin-filter-rule-manager.admin.scopes.normal_post')),
      private_post: String(app.translator.trans('huoxin-filter-rule-manager.admin.scopes.private_post')),
      tag: String(app.translator.trans('huoxin-filter-rule-manager.admin.scopes.tag')),
    };

    const interventionOptions: Record<string, string> = {
      '': String(app.translator.trans('huoxin-filter-rule-manager.admin.config_rule_triggered_any')),
      info: String(app.translator.trans('huoxin-filter-rule-manager.admin.interventions.info')),
      warning: String(app.translator.trans('huoxin-filter-rule-manager.admin.interventions.warning')),
      block: String(app.translator.trans('huoxin-filter-rule-manager.admin.interventions.block')),
      silent: String(app.translator.trans('huoxin-filter-rule-manager.admin.interventions.silent')),
    };

    const filterRuleManager = (app as Record<string, any>).filterRuleManager;
    const displayModes = filterRuleManager && typeof filterRuleManager.getDisplayModes === 'function' ? filterRuleManager.getDisplayModes() : {};
    const displayOptions: Record<string, string> = {
      '': String(app.translator.trans('huoxin-filter-rule-manager.admin.config_rule_triggered_any')),
      none: String(app.translator.trans('huoxin-filter-rule-manager.admin.displays.none')),
    };

    Object.entries(displayModes).forEach(([key, translationKey]) => {
      displayOptions[key] = String(app.translator.trans(translationKey as string));
    });
    return (
      <div className="FilterRuleManager-ConfigForm">
        <div className="Alert Alert--warning FilterRuleManager-RulesetEditor-warningAlert">
          <Icon name="fas fa-exclamation-circle" /> {app.translator.trans('huoxin-filter-rule-manager.admin.config_rule_triggered_position_warning')}
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.config_rule_triggered_match_rule_id')}</label>
          <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.config_rule_triggered_match_rule_id_help')}</div>
          <Select
            options={ruleOptions}
            value={this.matchRuleId ? `id_${this.matchRuleId}` : ''}
            onchange={(val: string) => this.updateConfig('match_rule_id', val.replace('id_', ''))}
          />
        </div>

        {!this.matchRuleId && (
          <div className="FilterRuleManager-RuleTriggered-AdvancedFilters">
            <div className="Form-group FilterRuleManager-RuleTriggered-NameField">
              <label>{app.translator.trans('huoxin-filter-rule-manager.admin.config_rule_triggered_match_name')}</label>
              <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.config_rule_triggered_match_name_help')}</div>
              <input
                className="FormControl"
                type="text"
                value={this.matchName}
                oninput={(e: InputEvent) => this.updateConfig('match_name', (e.target as HTMLInputElement).value)}
                placeholder=""
              />
            </div>

            <div className="Form-group FilterRuleManager-RuleTriggered-SelectField">
              <label>{app.translator.trans('huoxin-filter-rule-manager.admin.config_rule_triggered_match_scope')}</label>
              <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.config_rule_triggered_match_scope_help')}</div>
              <Select options={scopeOptions} value={this.matchScope} onchange={(val: string) => this.updateConfig('match_scope', val)} />
            </div>

            <div className="Form-group FilterRuleManager-RuleTriggered-SelectField">
              <label>{app.translator.trans('huoxin-filter-rule-manager.admin.config_rule_triggered_match_intervention')}</label>
              <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.config_rule_triggered_match_intervention_help')}</div>
              <Select
                options={interventionOptions}
                value={this.matchIntervention}
                onchange={(val: string) => this.updateConfig('match_intervention', val)}
              />
            </div>

            <div className="Form-group FilterRuleManager-RuleTriggered-SelectField">
              <label>{app.translator.trans('huoxin-filter-rule-manager.admin.config_rule_triggered_match_display')}</label>
              <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.config_rule_triggered_match_display_help')}</div>
              <Select options={displayOptions} value={this.matchDisplay} onchange={(val: string) => this.updateConfig('match_display', val)} />
            </div>
          </div>
        )}
      </div>
    );
  }

  updateConfig(key: string, value: any) {
    const config = { ...(this.attrs.config || {}) };

    if (value === '') {
      delete config[key];
    } else {
      config[key] = value;
    }

    if (key === 'match_rule_id') this.matchRuleId = value;
    if (key === 'match_name') this.matchName = value;
    if (key === 'match_scope') this.matchScope = value;
    if (key === 'match_intervention') this.matchIntervention = value;
    if (key === 'match_display') this.matchDisplay = value;

    this.attrs.onchange(config);
  }
}
