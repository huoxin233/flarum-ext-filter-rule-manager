/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import app from 'flarum/admin/app';
import ExtensionPage, { ExtensionPageAttrs } from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import extractText from 'flarum/common/utils/extractText';
import icon from 'flarum/common/helpers/icon';
import type Mithril from 'mithril';
import type Model from 'flarum/common/Model';
import type { ASTNode } from '../../common/FilterEngine';

import RulesetEditorModal from './RulesetEditorModal';

const SCOPES = ['all', 'global', 'normal_post', 'private_post', 'tag'];

export default class RulesetManagerPage extends ExtensionPage<ExtensionPageAttrs> {
  loading: boolean = true;
  activeTab: string = 'rulesets';
  scopeFilter: string = 'all';
  rulesets: Model[] = [];
  providers: Record<string, any>[] = [];
  reordering: boolean = false;
  toggling: Set<string> = new Set();
  registryFilter: string = 'providers';

  oninit(vnode: Mithril.Vnode<ExtensionPageAttrs, this>) {
    super.oninit(vnode);

    this.loading = true;
    this.activeTab = 'rulesets';
    this.scopeFilter = 'all';
    this.rulesets = [];
    this.providers = [];
    this.reordering = false;
    this.toggling = new Set();
    this.registryFilter = 'providers';

    this.loadData();
  }

  async loadData() {
    this.loading = true;
    m.redraw();

    try {
      const [, providersResponse] = await Promise.all([
        app.store.find('filter-rule-rulesets'),
        app.request<Record<string, any>>({
          method: 'GET',
          url: app.forum.attribute('apiUrl') + '/filter-rule-providers',
        }),
      ]);

      this.rulesets = (app.store.all('filter-rule-rulesets') || [])
        .slice()
        .sort((a: Model & { priority?: () => number }, b: Model & { priority?: () => number }) => (a.priority?.() || 0) - (b.priority?.() || 0));

      this.providers = (providersResponse.data || []).map((p: Record<string, any>) => Object.assign({}, p, { scope: 'backend' }));
      const frontendProviders = app.filterRuleManager ? app.filterRuleManager.getRegisteredFrontendTypes() : [];
      frontendProviders.forEach((fp: Record<string, any>) => {
        const existing = this.providers.find((p) => p.provider === fp.provider && p.type === fp.type);
        if (existing) {
          existing.scope = 'both';
        } else {
          this.providers.push({
            provider: fp.provider,
            type: fp.type,
            label: fp.label || fp.type,
            scope: 'frontend',
            tokens: [],
          });
        }
      });
    } catch (err) {
      console.error('Failed to load filter-rule data:', err);
      app.alerts.show({ type: 'error' }, app.translator.trans('huoxin-filter-rule-manager.admin.load_error'));
    } finally {
      this.loading = false;
      m.redraw();
    }
  }

  content(): any {
    if (this.loading) {
      return (
        <div className="FilterRuleManager-Page">
          <LoadingIndicator />
        </div>
      );
    }

    return (
      <div className="FilterRuleManager-Page">
        <div className="FilterRuleManager-Page-header">
          <div className="FilterRuleManager-Page-tabs">
            <Button
              className={`Button ${this.activeTab === 'rulesets' ? 'active' : ''}`}
              onclick={() => {
                this.activeTab = 'rulesets';
                m.redraw();
              }}
            >
              <i className="fas fa-shield-alt"></i>
              {app.translator.trans('huoxin-filter-rule-manager.admin.tabs.rulesets')}
              <span className="Button-badge">{this.rulesets.length}</span>
            </Button>
            <Button
              className={`Button ${this.activeTab === 'registry' ? 'active' : ''}`}
              onclick={() => {
                this.activeTab = 'registry';
                m.redraw();
              }}
            >
              <i className="fas fa-plug"></i>
              {app.translator.trans('huoxin-filter-rule-manager.admin.tabs.registry')}
            </Button>
            <Button
              className={`Button ${this.activeTab === 'settings' ? 'active' : ''}`}
              onclick={() => {
                this.activeTab = 'settings';
                m.redraw();
              }}
            >
              <i className="fas fa-cog"></i>
              {app.translator.trans('huoxin-filter-rule-manager.admin.tabs.settings')}
            </Button>
          </div>
          <div className="FilterRuleManager-Page-actions">
            {this.activeTab === 'rulesets' && (
              <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => this.showEditor(null)}>
                {app.translator.trans('huoxin-filter-rule-manager.admin.add_ruleset')}
              </Button>
            )}
          </div>
        </div>

        <div className="FilterRuleManager-Page-content">
          {this.activeTab === 'rulesets' && this.rulesetsTab()}
          {this.activeTab === 'registry' && this.registryTab()}
          {this.activeTab === 'settings' && this.settingsTab()}
        </div>
      </div>
    );
  }

  settingsTab(): Mithril.Children {
    const requireApprovalVal = this.setting('huoxin-filter.global_require_approval', '1')();
    const autoFlagVal = this.setting('huoxin-filter.global_auto_flag', '1')();
    const requireApproval = requireApprovalVal === true || requireApprovalVal === '1';
    const autoFlag = autoFlagVal === true || autoFlagVal === '1';

    return (
      <div className="FilterRuleManager-RulesetEditor-section">
        <div className="FilterRuleManager-RulesetEditor-section-header">
          <i className="fas fa-cog"></i>
          <h4>{app.translator.trans('huoxin-filter-rule-manager.admin.settings_header')}</h4>
        </div>

        <div className="Form-group">
          <Switch
            state={
              this.setting('huoxin-filter.global_evaluate_title', '1')() === '1' ||
              this.setting('huoxin-filter.global_evaluate_title', '1')() === true
            }
            onchange={(val: boolean) => this.setting('huoxin-filter.global_evaluate_title')(val ? '1' : '0')}
          >
            {app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evaluate_title')}
          </Switch>
          <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evaluate_title_help')}</div>
        </div>
        <div className="Form-group">
          <Switch
            state={this.setting('huoxin-filter.global_auto_flag', '1')() === '1' || this.setting('huoxin-filter.global_auto_flag', '1')() === true}
            onchange={(val: boolean) => this.setting('huoxin-filter.global_auto_flag')(val ? '1' : '0')}
          >
            {app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_auto_flag')}
          </Switch>
          <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_auto_flag_help')}</div>
        </div>
        <div className="Form-group">
          <Switch
            state={
              this.setting('huoxin-filter.global_require_approval', '1')() === '1' ||
              this.setting('huoxin-filter.global_require_approval', '1')() === true
            }
            onchange={(val: boolean) => this.setting('huoxin-filter.global_require_approval')(val ? '1' : '0')}
          >
            {app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_require_approval')}
          </Switch>
          <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_require_approval_help')}</div>
        </div>

        {requireApproval && !autoFlag ? (
          <div className="Alert Alert--warning">
            <p>
              <i className="fas fa-exclamation-circle"></i>{' '}
              {app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_approval_without_flag_warning')}
            </p>
          </div>
        ) : null}

        <hr className="FilterRuleManager-RulesetEditor-divider" />

        <div className="Form-group">
          <Switch
            state={
              this.setting('huoxin-filter.global_evasion_active', '0')() === '1' ||
              this.setting('huoxin-filter.global_evasion_active', '0')() === true
            }
            onchange={(val: boolean) => this.setting('huoxin-filter.global_evasion_active')(val ? '1' : '0')}
          >
            {app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evasion_active')}
          </Switch>
          <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evasion_active_help')}</div>
        </div>
        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evasion_timeout')}</label>
          {this.buildSettingComponent({
            type: 'number',
            setting: 'huoxin-filter.global_evasion_timeout',
            help: String(app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evasion_timeout_help')),
            default: 5,
          })}
        </div>
        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evasion_threshold')}</label>
          {this.buildSettingComponent({
            type: 'number',
            setting: 'huoxin-filter.global_evasion_threshold',
            help: String(app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evasion_threshold_help')),
            default: 2,
          })}
        </div>
        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evasion_log_keep_days')}</label>
          {this.buildSettingComponent({
            type: 'number',
            setting: 'huoxin-filter.global_evasion_log_keep_days',
            help: String(app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evasion_log_keep_days_help')),
            default: 0,
          })}
        </div>

        <hr className="FilterRuleManager-RulesetEditor-divider" />

        <div className="Form-group">
          <Switch
            state={this.setting('huoxin-filter.obfuscate_active', '1')() === '1' || this.setting('huoxin-filter.obfuscate_active', '1')() === true}
            onchange={(val: boolean) => this.setting('huoxin-filter.obfuscate_active')(val ? '1' : '0')}
          >
            {app.translator.trans('huoxin-filter-rule-manager.admin.settings.obfuscate_active')}
          </Switch>
          <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.settings.obfuscate_active_help')}</div>
        </div>
        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.settings.obfuscate_key')}</label>
          {this.buildSettingComponent({
            type: 'string',
            setting: 'huoxin-filter.obfuscate_key',
            help: String(app.translator.trans('huoxin-filter-rule-manager.admin.settings.obfuscate_key_help')),
            default: 'HuoxinFilterRuleManager',
          })}
        </div>
        <div className="Form-group">{this.submitButton()}</div>
      </div>
    );
  }

  rulesetsTab(): Mithril.Children {
    const counts: Record<string, number> = {
      all: this.rulesets.length,
      global: this.rulesets.filter((r) => (r as any).scopeType() === 'global').length,
      normal_post: this.rulesets.filter((r) => (r as any).scopeType() === 'normal_post').length,
      private_post: this.rulesets.filter((r) => (r as any).scopeType() === 'private_post').length,
      tag: this.rulesets.filter((r) => (r as any).scopeType() === 'tag').length,
    };

    return (
      <div className="FilterRuleManager-RulesetsTab">
        <div className="FilterRuleManager-Page-subtabs">
          {SCOPES.map((scope) => (
            <button
              type="button"
              key={scope}
              className={`FilterRuleManager-SubTab ${this.scopeFilter === scope ? 'active' : ''}`}
              onclick={() => {
                this.scopeFilter = scope;
                m.redraw();
              }}
            >
              <span className="FilterRuleManager-SubTab-label">
                {app.translator.trans(`huoxin-filter-rule-manager.admin.scope_filters.${scope}`)}
              </span>
              <span className="FilterRuleManager-SubTab-count">{counts[scope]}</span>
            </button>
          ))}
        </div>

        {this.renderList(this.filteredRulesets())}
      </div>
    );
  }

  filteredRulesets() {
    if (this.scopeFilter === 'all') return this.rulesets;
    return this.rulesets.filter((r) => (r as any).scopeType() === this.scopeFilter);
  }

  renderList(list: Model[]): Mithril.Children {
    if (list.length === 0) {
      return (
        <div className="FilterRuleManager-EmptyState">
          <div className="FilterRuleManager-EmptyState-icon">
            <i className="fas fa-shield-alt"></i>
          </div>
          <p className="FilterRuleManager-EmptyState-text">
            {this.scopeFilter === 'all'
              ? app.translator.trans('huoxin-filter-rule-manager.admin.no_rulesets')
              : app.translator.trans('huoxin-filter-rule-manager.admin.no_rulesets_in_scope')}
          </p>
          {this.scopeFilter === 'all' && (
            <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => this.showEditor(null)}>
              {app.translator.trans('huoxin-filter-rule-manager.admin.add_ruleset')}
            </Button>
          )}
        </div>
      );
    }

    return (
      <div className="FilterRuleManager-RulesetsList">
        <div className="FilterRuleManager-CardList">
          <div className="FilterRuleManager-CardList-header">
            <span>{app.translator.trans('huoxin-filter-rule-manager.admin.headers.order')}</span>
            <span>{app.translator.trans('huoxin-filter-rule-manager.admin.headers.name')}</span>
            <span>{app.translator.trans('huoxin-filter-rule-manager.admin.headers.intervention')}</span>
            <span>{app.translator.trans('huoxin-filter-rule-manager.admin.headers.scope')}</span>
            <span>{app.translator.trans('huoxin-filter-rule-manager.admin.headers.display')}</span>
            <span>{app.translator.trans('huoxin-filter-rule-manager.admin.headers.rules')}</span>
            <span>{app.translator.trans('huoxin-filter-rule-manager.admin.headers.active')}</span>
            <span></span>
          </div>
          {list.map((ruleset, i) => this.rulesetRow(ruleset, i, list))}
        </div>
      </div>
    );
  }

  rulesetRow(ruleset: Model & Record<string, any>, filteredIndex: number, filteredList: Model[]): Mithril.Children {
    const isActive = ruleset.isActive();
    const isFirst = filteredIndex === 0;
    const isLast = filteredIndex === filteredList.length - 1;
    const intervention = ruleset.interventionType();
    const scope = ruleset.scopeType();
    const display = ruleset.displayMode();
    const isTogglingThis = this.toggling.has(ruleset.id() as string);
    const rulesCount = this.countRules(ruleset.compiledAst());

    return (
      <div className={`FilterRuleManager-CardList-item ${!isActive ? 'FilterRuleManager-CardList-item--inactive' : ''}`} key={ruleset.id() as string}>
        <div className="FilterRuleManager-CardList-item-cell FilterRuleManager-CardList-item-cell--order">
          <Button
            className="Button Button--icon Button--small"
            icon="fas fa-arrow-up"
            disabled={isFirst || this.reordering}
            onclick={() => this.move(filteredIndex, -1, filteredList)}
            aria-label={String(app.translator.trans('huoxin-filter-rule-manager.admin.move_up'))}
          />
          <Button
            className="Button Button--icon Button--small"
            icon="fas fa-arrow-down"
            disabled={isLast || this.reordering}
            onclick={() => this.move(filteredIndex, 1, filteredList)}
            aria-label={String(app.translator.trans('huoxin-filter-rule-manager.admin.move_down'))}
          />
        </div>

        <div
          className="FilterRuleManager-CardList-item-cell FilterRuleManager-CardList-item-cell--primary"
          data-label={app.translator.trans('huoxin-filter-rule-manager.admin.headers.name')}
        >
          {ruleset.name()}
        </div>
        <div
          className="FilterRuleManager-CardList-item-cell"
          data-label={app.translator.trans('huoxin-filter-rule-manager.admin.headers.intervention')}
        >
          <span className={`FilterRuleManager-InterventionBadge FilterRuleManager-InterventionBadge--${intervention}`}>
            {icon(this.interventionIcon(intervention))}
            {app.translator.trans(`huoxin-filter-rule-manager.admin.interventions.${intervention}`)}
          </span>
        </div>
        <div className="FilterRuleManager-CardList-item-cell" data-label={app.translator.trans('huoxin-filter-rule-manager.admin.headers.scope')}>
          <span className={`FilterRuleManager-ScopeBadge FilterRuleManager-ScopeBadge--${scope}`}>
            {app.translator.trans(`huoxin-filter-rule-manager.admin.scopes.${scope}`)}
          </span>
        </div>

        <div
          className="FilterRuleManager-CardList-item-cell FilterRuleManager-CardList-item-cell--muted"
          data-label={app.translator.trans('huoxin-filter-rule-manager.admin.headers.display')}
        >
          {app.translator.trans(`huoxin-filter-rule-manager.admin.displays.${display}`)}
        </div>

        <div
          className="FilterRuleManager-CardList-item-cell FilterRuleManager-CardList-item-cell--muted FilterRuleManager-CardList-item-cell--rules"
          data-label={app.translator.trans('huoxin-filter-rule-manager.admin.headers.rules')}
        >
          <span className="FilterRuleManager-CountBadge">{rulesCount}</span>
        </div>

        <div
          className="FilterRuleManager-CardList-item-cell FilterRuleManager-CardList-item-cell--switch"
          data-label={app.translator.trans('huoxin-filter-rule-manager.admin.headers.active')}
        >
          <Switch state={isActive} disabled={isTogglingThis} onchange={(val: boolean) => this.toggleActive(ruleset, val)} />
        </div>
        <div className="FilterRuleManager-CardList-item-actions">
          <Button
            className="Button"
            icon="fas fa-edit"
            onclick={() => this.showEditor(ruleset)}
            aria-label={String(app.translator.trans('huoxin-filter-rule-manager.admin.edit'))}
          >
            {app.translator.trans('huoxin-filter-rule-manager.admin.edit')}
          </Button>
          <Button
            className="Button Button--danger"
            icon="fas fa-trash"
            onclick={() => this.deleteRuleset(ruleset)}
            aria-label={String(app.translator.trans('huoxin-filter-rule-manager.admin.delete'))}
          >
            {app.translator.trans('huoxin-filter-rule-manager.admin.delete')}
          </Button>
        </div>
      </div>
    );
  }

  countRules(ast: ASTNode | null | undefined): number {
    if (!ast) return 0;
    if (ast.type === 'rule') return 1;
    if (ast.type === 'logical') return this.countRules(ast.left) + this.countRules(ast.right);
    if (ast.type === 'not') return this.countRules(ast.node);
    return 0;
  }

  interventionIcon(intervention: string) {
    if (intervention === 'block') return 'fas fa-ban';
    if (intervention === 'warning') return 'fas fa-exclamation-triangle';
    return 'fas fa-info-circle';
  }

  registryTab(): Mithril.Children {
    const REGISTRY_SCOPES = ['providers', 'templates', 'modes'];
    return (
      <div className="FilterRuleManager-RegistryTab">
        <div className="FilterRuleManager-Page-subtabs">
          {REGISTRY_SCOPES.map((scope) => (
            <button
              type="button"
              key={scope}
              className={`FilterRuleManager-SubTab ${this.registryFilter === scope ? 'active' : ''}`}
              onclick={() => {
                this.registryFilter = scope;
                m.redraw();
              }}
            >
              <span className="FilterRuleManager-SubTab-label">
                {app.translator.trans(`huoxin-filter-rule-manager.admin.registry_tabs.${scope}`)}
              </span>
            </button>
          ))}
        </div>

        {this.registryFilter === 'providers' && this.renderProviders()}
        {this.registryFilter === 'templates' && this.renderTemplates()}
        {this.registryFilter === 'modes' && this.renderModes()}
      </div>
    );
  }

  renderProviders(): Mithril.Children {
    if (this.providers.length === 0) {
      return (
        <div className="FilterRuleManager-EmptyState">
          <div className="FilterRuleManager-EmptyState-icon">
            <i className="fas fa-plug"></i>
          </div>
          <p className="FilterRuleManager-EmptyState-text">{app.translator.trans('huoxin-filter-rule-manager.admin.no_providers')}</p>
        </div>
      );
    }

    const byProvider: Record<string, Record<string, unknown>[]> = {};
    for (const p of this.providers) {
      (byProvider[p.provider] = byProvider[p.provider] || []).push(p);
    }

    return (
      <div className="FilterRuleManager-ProvidersList">
        {Object.entries(byProvider).map(([name, items]) => (
          <div className="FilterRuleManager-ProvidersList-group" key={name}>
            <h3 className="FilterRuleManager-ProvidersList-groupTitle">
              {(() => {
                const key = `huoxin-filter-rule-manager.admin.providers.${name}`;
                const translated = app.translator.trans(key);
                const translatedStr = extractText(translated);
                return translatedStr === key ? name.toUpperCase() : translated;
              })()}
            </h3>
            <div className="FilterRuleManager-CardList">
              <div className="FilterRuleManager-CardList-header">
                <span>{app.translator.trans('huoxin-filter-rule-manager.admin.headers.type')}</span>
                <span>{app.translator.trans('huoxin-filter-rule-manager.admin.headers.label')}</span>
                <span>{app.translator.trans('huoxin-filter-rule-manager.admin.headers.runs')}</span>
                <span>{app.translator.trans('huoxin-filter-rule-manager.admin.headers.tokens')}</span>
              </div>
              {items.map((p) => (
                <div className="FilterRuleManager-CardList-item" key={`${p.provider}:${p.type}`}>
                  <div
                    className="FilterRuleManager-CardList-item-cell FilterRuleManager-CardList-item-cell--primary"
                    data-label={String(app.translator.trans('huoxin-filter-rule-manager.admin.headers.type'))}
                  >
                    <code>{p.type}</code>
                  </div>
                  <div
                    className="FilterRuleManager-CardList-item-cell"
                    data-label={String(app.translator.trans('huoxin-filter-rule-manager.admin.headers.label'))}
                  >
                    {p.label || p.type}
                  </div>
                  <div
                    className="FilterRuleManager-CardList-item-cell"
                    data-label={String(app.translator.trans('huoxin-filter-rule-manager.admin.headers.runs'))}
                  >
                    <span className={`FilterRuleManager-ScopeBadge FilterRuleManager-ScopeBadge--${p.scope}`}>
                      {app.translator.trans(`huoxin-filter-rule-manager.admin.scopes.${p.scope}`)}
                    </span>
                  </div>
                  <div
                    className="FilterRuleManager-CardList-item-cell FilterRuleManager-CardList-item-cell--muted"
                    data-label={String(app.translator.trans('huoxin-filter-rule-manager.admin.headers.tokens'))}
                  >
                    {p.tokens && (p.tokens as unknown[]).length > 0 ? (
                      (p.tokens as any[]).map((t: Record<string, unknown>) => (
                        <code className="FilterRuleManager-TokenInlineChip" key={t.name as string}>{`{{${t.name}}}`}</code>
                      ))
                    ) : (
                      <em>—</em>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>
    );
  }

  renderTemplates(): Mithril.Children {
    const templates = app.filterRuleManager ? app.filterRuleManager.templates : {};

    return (
      <div className="FilterRuleManager-ProvidersList">
        <div className="FilterRuleManager-ProvidersList-group">
          <h3 className="FilterRuleManager-ProvidersList-groupTitle">
            {app.translator.trans('huoxin-filter-rule-manager.admin.registry_tabs.templates')}
          </h3>
          <div className="FilterRuleManager-CardList FilterRuleManager-CardList--two-col">
            <div className="FilterRuleManager-CardList-header">
              <span>{app.translator.trans('huoxin-filter-rule-manager.admin.headers.template')}</span>
              <span>{app.translator.trans('huoxin-filter-rule-manager.admin.headers.has_settings')}</span>
            </div>
            {Object.keys(templates).map((name) => {
              const settingsComp = app.filterRuleManager.getTemplateSettingsComponent(name);
              return (
                <div className="FilterRuleManager-CardList-item" key={name}>
                  <div
                    className="FilterRuleManager-CardList-item-cell FilterRuleManager-CardList-item-cell--primary"
                    data-label={String(app.translator.trans('huoxin-filter-rule-manager.admin.headers.template'))}
                  >
                    <code>{name}</code>
                  </div>
                  <div
                    className="FilterRuleManager-CardList-item-cell"
                    data-label={String(app.translator.trans('huoxin-filter-rule-manager.admin.headers.has_settings'))}
                  >
                    {settingsComp ? <i className="fas fa-check text-success"></i> : <i className="fas fa-times text-muted"></i>}
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </div>
    );
  }

  renderModes(): Mithril.Children {
    const modes = app.filterRuleManager ? app.filterRuleManager.getDisplayModes() : {};

    return (
      <div className="FilterRuleManager-ProvidersList">
        <div className="FilterRuleManager-ProvidersList-group">
          <h3 className="FilterRuleManager-ProvidersList-groupTitle">
            {app.translator.trans('huoxin-filter-rule-manager.admin.registry_tabs.modes')}
          </h3>
          <div className="FilterRuleManager-CardList FilterRuleManager-CardList--two-col">
            <div className="FilterRuleManager-CardList-header">
              <span>{app.translator.trans('huoxin-filter-rule-manager.admin.headers.identifier')}</span>
              <span>{app.translator.trans('huoxin-filter-rule-manager.admin.headers.label')}</span>
            </div>
            {Object.keys(modes).map((mode) => (
              <div className="FilterRuleManager-CardList-item" key={mode}>
                <div
                  className="FilterRuleManager-CardList-item-cell FilterRuleManager-CardList-item-cell--primary"
                  data-label={String(app.translator.trans('huoxin-filter-rule-manager.admin.headers.identifier'))}
                >
                  <code>{mode}</code>
                </div>
                <div
                  className="FilterRuleManager-CardList-item-cell"
                  data-label={String(app.translator.trans('huoxin-filter-rule-manager.admin.headers.label'))}
                >
                  {app.translator.trans(modes[mode])}
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  showEditor(ruleset: any) {
    app.modal.show(RulesetEditorModal, {
      ruleset,
      providers: this.providers,
      onsave: () => this.loadData(),
    });
  }

  async deleteRuleset(ruleset: any) {
    const ok = confirm(
      extractText(
        app.translator.trans('huoxin-filter-rule-manager.admin.delete_ruleset_confirm', {
          name: ruleset.name(),
        })
      )
    );
    if (!ok) return;

    try {
      await ruleset.delete();
      this.rulesets = this.rulesets.filter((r) => r.id() !== ruleset.id());
      app.alerts.show({ type: 'success' }, app.translator.trans('huoxin-filter-rule-manager.admin.delete_success'));
      m.redraw();
    } catch (err) {
      console.error('Failed to delete ruleset:', err);
      app.alerts.show({ type: 'error' }, app.translator.trans('huoxin-filter-rule-manager.admin.delete_error'));
    }
  }

  async toggleActive(ruleset: Model, isActive: boolean) {
    if (this.toggling.has(ruleset.id() as string)) return;
    this.toggling.add(ruleset.id() as string);
    m.redraw();

    try {
      await ruleset.save({ isActive });
    } catch (err) {
      console.error('Failed to toggle isActive:', err);
      app.alerts.show({ type: 'error' }, app.translator.trans('huoxin-filter-rule-manager.admin.toggle_error'));
    } finally {
      this.toggling.delete(ruleset.id() as string);
      m.redraw();
    }
  }

  /**
   * Move a ruleset within the filtered view by `delta` (-1 / +1).
   *
   * Swaps it with the adjacent ruleset *in the filtered list*, then persists
   * the FULL ruleset order so non-filtered items keep their existing priority
   * positions. This way moving "up" within "Global" still does the right
   * thing even when private/tag rulesets exist with different priorities.
   */
  async move(filteredIndex: number, delta: number, filteredList: any[]) {
    if (this.reordering) return;
    const target = filteredIndex + delta;
    if (target < 0 || target >= filteredList.length) return;

    const a = filteredList[filteredIndex];
    const b = filteredList[target];

    const all = this.rulesets.slice();
    const aIdx = all.findIndex((r) => r.id() === a.id());
    const bIdx = all.findIndex((r) => r.id() === b.id());
    if (aIdx === -1 || bIdx === -1) return;

    [all[aIdx], all[bIdx]] = [all[bIdx], all[aIdx]];
    this.rulesets = all;
    this.reordering = true;
    m.redraw();

    try {
      await app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/filter-rule-rulesets/reorder',
        body: { data: { ids: all.map((r) => r.id()) } },
      });
    } catch (err) {
      console.error('Failed to reorder:', err);
      app.alerts.show({ type: 'error' }, app.translator.trans('huoxin-filter-rule-manager.admin.reorder_error'));
      await this.loadData();
    } finally {
      this.reordering = false;
      m.redraw();
    }
  }
}
