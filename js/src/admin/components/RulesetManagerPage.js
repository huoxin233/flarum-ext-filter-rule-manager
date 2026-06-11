import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import extractText from 'flarum/common/utils/extractText';
import icon from 'flarum/common/helpers/icon';

import RulesetEditorModal from './RulesetEditorModal';

const SCOPES = ['all', 'global', 'normal_post', 'private_post', 'tag'];

export default class RulesetManagerPage extends ExtensionPage {
  oninit(vnode) {
    super.oninit(vnode);

    this.loading = true;
    this.activeTab = 'rulesets';        // main tabs: rulesets | providers
    this.scopeFilter = 'all';           // sub-tabs: all | global | normal_post | private_post | tag
    this.rulesets = [];
    this.providers = [];
    this.reordering = false;
    this.toggling = new Set();          // ruleset ids currently saving isActive

    this.loadData();
  }

  async loadData() {
    this.loading = true;
    m.redraw();

    try {
      const [, providersResponse] = await Promise.all([
        app.store.find('filter-rule-rulesets'),
        app.request({
          method: 'GET',
          url: app.forum.attribute('apiUrl') + '/filter-rule-providers',
        }),
      ]);

      this.rulesets = (app.store.all('filter-rule-rulesets') || [])
        .slice()
        .sort((a, b) => (a.priority() || 0) - (b.priority() || 0));

      this.providers = (providersResponse.data || []).slice();
      const frontendProviders = app.filterRuleManager
        ? app.filterRuleManager.getRegisteredFrontendTypes()
        : [];
      frontendProviders.forEach((fp) => {
        if (!this.providers.find((p) => p.provider === fp.provider && p.type === fp.type)) {
          this.providers.push({
            provider: fp.provider,
            type: fp.type,
            label: `${fp.label || fp.type} (frontend only)`,
            scope: 'frontend',
            tokens: [],
          });
        }
      });
    } catch (err) {
      console.error('Failed to load filter-rule data:', err);
      app.alerts.show(
        { type: 'error' },
        app.translator.trans('huoxin-filter-rule-manager.admin.load_error')
      );
    } finally {
      this.loading = false;
      m.redraw();
    }
  }

  content() {
    if (this.loading) {
      return <div className="FilterRulePage"><LoadingIndicator /></div>;
    }

    return (
      <div className="FilterRulePage">
        <div className="FilterRulePage-header">
          <div className="FilterRulePage-tabs">
            <Button
              className={`Button ${this.activeTab === 'rulesets' ? 'active' : ''}`}
              onclick={() => { this.activeTab = 'rulesets'; m.redraw(); }}
            >
              <i className="fas fa-shield-alt"></i>
              {app.translator.trans('huoxin-filter-rule-manager.admin.tabs.rulesets')}
              <span className="Button-badge">{this.rulesets.length}</span>
            </Button>
            <Button
              className={`Button ${this.activeTab === 'providers' ? 'active' : ''}`}
              onclick={() => { this.activeTab = 'providers'; m.redraw(); }}
            >
              <i className="fas fa-plug"></i>
              {app.translator.trans('huoxin-filter-rule-manager.admin.tabs.providers')}
              <span className="Button-badge">{this.providers.length}</span>
            </Button>
            <Button
              className={`Button ${this.activeTab === 'settings' ? 'active' : ''}`}
              onclick={() => { this.activeTab = 'settings'; m.redraw(); }}
            >
              <i className="fas fa-cog"></i>
              {app.translator.trans('huoxin-filter-rule-manager.admin.tabs.settings', {}, 'Settings')}
            </Button>
          </div>
          <div className="FilterRulePage-actions">
            {this.activeTab === 'rulesets' && (
              <Button
                className="Button Button--primary"
                icon="fas fa-plus"
                onclick={() => this.showEditor(null)}
              >
                {app.translator.trans('huoxin-filter-rule-manager.admin.add_ruleset')}
              </Button>
            )}
          </div>
        </div>

        <div className="FilterRulePage-content">
          {this.activeTab === 'rulesets' && this.rulesetsTab()}
          {this.activeTab === 'providers' && this.providersTab()}
          {this.activeTab === 'settings' && this.settingsTab()}
        </div>
      </div>
    );
  }

  // ── Settings Tab ────────────────────────────────────────────────────────
  
  settingsTab() {
    const requireApprovalVal = this.setting('huoxin-filter.global_require_approval', '1')();
    const autoFlagVal = this.setting('huoxin-filter.global_auto_flag', '1')();
    const requireApproval = requireApprovalVal === true || requireApprovalVal === '1';
    const autoFlag = autoFlagVal === true || autoFlagVal === '1';

    return (
      <div className="RulesetEditor-section">
        <div className="RulesetEditor-section-header">
          <i className="fas fa-cog"></i>
          <h4>{app.translator.trans('huoxin-filter-rule-manager.admin.settings_header', {}, 'Global Settings')}</h4>
        </div>
        
        <div className="Form-group">
          {this.buildSettingComponent({
            type: 'boolean',
            setting: 'huoxin-filter.global_evaluate_title',
            label: app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evaluate_title'),
            help: "If enabled, new posts will be evaluated against their discussion's title.",
            default: true,
          })}
        </div>
        <div className="Form-group">
          {this.buildSettingComponent({
            type: 'boolean',
            setting: 'huoxin-filter.global_auto_flag',
            label: app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_auto_flag'),
            help: "Automatically flag posts that trigger rulesets.",
            default: true,
          })}
        </div>
        <div className="Form-group">
          {this.buildSettingComponent({
            type: 'boolean',
            setting: 'huoxin-filter.global_require_approval',
            label: app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_require_approval'),
            help: "Automatically hold posts for approval.",
            default: true,
          })}
        </div>
        
        {requireApproval && !autoFlag ? (
          <div className="Alert Alert--warning">
            <p><i className="fas fa-exclamation-circle"></i> {app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_approval_without_flag_warning')}</p>
          </div>
        ) : null}

        <hr className="RulesetEditor-divider" />

        <div className="Form-group">
          {this.buildSettingComponent({
            type: 'boolean',
            setting: 'huoxin-filter.global_evasion_active',
            label: app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evasion_active'),
            help: "Track users who repeatedly trigger block rules.",
            default: false,
          })}
        </div>
        <div className="Form-group">
              <label>{app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evasion_timeout')}</label>
              {this.buildSettingComponent({
                type: 'number',
                setting: 'huoxin-filter.global_evasion_timeout',
                default: 5,
              })}
            </div>
        <div className="Form-group">
              <label>{app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evasion_threshold')}</label>
              {this.buildSettingComponent({
                type: 'number',
                setting: 'huoxin-filter.global_evasion_threshold',
                default: 2,
              })}
        </div>
        <div className="Form-group">
          {this.submitButton()}
        </div>
      </div>
    );
  }

  // ── Rulesets tab (sub-tabs + filtered list) ──────────────────────────────

  rulesetsTab() {
    const counts = {
      all: this.rulesets.length,
      global: this.rulesets.filter((r) => r.scopeType() === 'global').length,
      normal_post: this.rulesets.filter((r) => r.scopeType() === 'normal_post').length,
      private_post: this.rulesets.filter((r) => r.scopeType() === 'private_post').length,
      tag: this.rulesets.filter((r) => r.scopeType() === 'tag').length,
    };

    return (
      <div className="RulesetsTab">
        <div className="FilterRulePage-subtabs">
          {SCOPES.map((scope) => (
            <button
              type="button"
              key={scope}
              className={`SubTab ${this.scopeFilter === scope ? 'active' : ''}`}
              onclick={() => { this.scopeFilter = scope; m.redraw(); }}
            >
              <span className="SubTab-label">
                {app.translator.trans(`huoxin-filter-rule-manager.admin.scope_filters.${scope}`)}
              </span>
              <span className="SubTab-count">{counts[scope]}</span>
            </button>
          ))}
        </div>

        {this.renderList(this.filteredRulesets())}
      </div>
    );
  }

  filteredRulesets() {
    if (this.scopeFilter === 'all') return this.rulesets;
    return this.rulesets.filter((r) => r.scopeType() === this.scopeFilter);
  }

  renderList(list) {
    if (list.length === 0) {
      return (
        <div className="EmptyState">
          <div className="EmptyState-icon"><i className="fas fa-shield-alt"></i></div>
          <p className="EmptyState-text">
            {this.scopeFilter === 'all'
              ? app.translator.trans('huoxin-filter-rule-manager.admin.no_rulesets')
              : app.translator.trans('huoxin-filter-rule-manager.admin.no_rulesets_in_scope')}
          </p>
          {this.scopeFilter === 'all' && (
            <Button
              className="Button Button--primary"
              icon="fas fa-plus"
              onclick={() => this.showEditor(null)}
            >
              {app.translator.trans('huoxin-filter-rule-manager.admin.add_ruleset')}
            </Button>
          )}
        </div>
      );
    }

    return (
      <div className="RulesetsList">
        <div className="CardList">
          <div className="CardList-header">
            <span>{app.translator.trans('huoxin-filter-rule-manager.admin.headers.order')}</span>
            <span>{app.translator.trans('huoxin-filter-rule-manager.admin.headers.name')}</span>
            <span>{app.translator.trans('huoxin-filter-rule-manager.admin.headers.effect')}</span>
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

  rulesetRow(ruleset, filteredIndex, filteredList) {
    const isActive = ruleset.isActive();
    const isFirst = filteredIndex === 0;
    const isLast = filteredIndex === filteredList.length - 1;
    const effect = ruleset.effectType();
    const scope = ruleset.scopeType();
    const display = ruleset.displayMode();
    const isTogglingThis = this.toggling.has(ruleset.id());
    const rulesCount = this.countRules(ruleset.compiledAst());

    return (
      <div className={`CardList-item ${!isActive ? 'CardList-item--inactive' : ''}`} key={ruleset.id()}>
        <div className="CardList-item-cell CardList-item-cell--order">
          <Button
            className="Button Button--icon Button--small"
            icon="fas fa-arrow-up"
            disabled={isFirst || this.reordering}
            onclick={() => this.move(filteredIndex, -1, filteredList)}
            aria-label={app.translator.trans('huoxin-filter-rule-manager.admin.move_up')}
          />
          <Button
            className="Button Button--icon Button--small"
            icon="fas fa-arrow-down"
            disabled={isLast || this.reordering}
            onclick={() => this.move(filteredIndex, 1, filteredList)}
            aria-label={app.translator.trans('huoxin-filter-rule-manager.admin.move_down')}
          />
        </div>

        <div
          className="CardList-item-cell CardList-item-cell--primary"
          data-label={app.translator.trans('huoxin-filter-rule-manager.admin.headers.name')}
        >
          {ruleset.name()}
        </div>

        <div className="CardList-item-cell" data-label={app.translator.trans('huoxin-filter-rule-manager.admin.headers.effect')}>
          <span className={`EffectBadge EffectBadge--${effect}`}>
            {icon(this.effectIcon(effect))}
            {app.translator.trans(`huoxin-filter-rule-manager.admin.effects.${effect}`)}
          </span>
        </div>

        <div className="CardList-item-cell" data-label={app.translator.trans('huoxin-filter-rule-manager.admin.headers.scope')}>
          <span className={`ScopeBadge ScopeBadge--${scope}`}>
            {app.translator.trans(`huoxin-filter-rule-manager.admin.scopes.${scope}`)}
          </span>
        </div>

        <div className="CardList-item-cell CardList-item-cell--muted" data-label={app.translator.trans('huoxin-filter-rule-manager.admin.headers.display')}>
          {app.translator.trans(`huoxin-filter-rule-manager.admin.displays.${display}`)}
        </div>

        <div className="CardList-item-cell CardList-item-cell--muted" data-label={app.translator.trans('huoxin-filter-rule-manager.admin.headers.rules')}>
          <span className="CountBadge">{rulesCount}</span>
        </div>

        <div className="CardList-item-cell CardList-item-cell--switch" data-label={app.translator.trans('huoxin-filter-rule-manager.admin.headers.active')}>
          <Switch
            state={isActive}
            disabled={isTogglingThis}
            onchange={(val) => this.toggleActive(ruleset, val)}
          />
        </div>

        <div className="CardList-item-actions">
          <Button className="Button" icon="fas fa-edit" onclick={() => this.showEditor(ruleset)} aria-label={app.translator.trans('huoxin-filter-rule-manager.admin.edit')}>
            {app.translator.trans('huoxin-filter-rule-manager.admin.edit')}
          </Button>
          <Button className="Button Button--danger" icon="fas fa-trash" onclick={() => this.deleteRuleset(ruleset)} aria-label={app.translator.trans('huoxin-filter-rule-manager.admin.delete')}>
            {app.translator.trans('huoxin-filter-rule-manager.admin.delete')}
          </Button>
        </div>
      </div>
    );
  }

  countRules(ast) {
    if (!ast) return 0;
    if (ast.type === 'rule') return 1;
    if (ast.type === 'logical') return this.countRules(ast.left) + this.countRules(ast.right);
    if (ast.type === 'not') return this.countRules(ast.node);
    return 0;
  }

  effectIcon(effect) {
    if (effect === 'block')   return 'fas fa-ban';
    if (effect === 'warning') return 'fas fa-exclamation-triangle';
    return 'fas fa-info-circle';
  }

  // ── Providers tab ────────────────────────────────────────────────────────

  providersTab() {
    if (this.providers.length === 0) {
      return (
        <div className="EmptyState">
          <div className="EmptyState-icon"><i className="fas fa-plug"></i></div>
          <p className="EmptyState-text">
            {app.translator.trans('huoxin-filter-rule-manager.admin.no_providers')}
          </p>
        </div>
      );
    }

    const byProvider = {};
    for (const p of this.providers) {
      (byProvider[p.provider] = byProvider[p.provider] || []).push(p);
    }

    return (
      <div className="ProvidersList">
        {Object.entries(byProvider).map(([name, items]) => (
          <div className="ProvidersList-group" key={name}>
            <h3 className="ProvidersList-groupTitle">{name}</h3>
            <div className="CardList">
              <div className="CardList-header">
                <span>{app.translator.trans('huoxin-filter-rule-manager.admin.headers.type')}</span>
                <span>{app.translator.trans('huoxin-filter-rule-manager.admin.headers.label')}</span>
                <span>{app.translator.trans('huoxin-filter-rule-manager.admin.headers.runs')}</span>
                <span>{app.translator.trans('huoxin-filter-rule-manager.admin.headers.tokens')}</span>
              </div>
              {items.map((p) => (
                <div className="CardList-item" key={`${p.provider}:${p.type}`}>
                  <div className="CardList-item-cell CardList-item-cell--primary" data-label="Type">
                    <code>{p.type}</code>
                  </div>
                  <div className="CardList-item-cell" data-label="Label">{p.label || p.type}</div>
                  <div className="CardList-item-cell" data-label="Runs">
                    <span className={`ScopeBadge ScopeBadge--${p.scope}`}>{p.scope}</span>
                  </div>
                  <div className="CardList-item-cell CardList-item-cell--muted" data-label="Tokens">
                    {(p.tokens && p.tokens.length > 0)
                      ? p.tokens.map((t) => <code className="TokenInlineChip" key={t.name}>{`{{${t.name}}}`}</code>)
                      : <em>—</em>}
                  </div>
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>
    );
  }

  // ── Actions ──────────────────────────────────────────────────────────────

  showEditor(ruleset) {
    app.modal.show(RulesetEditorModal, {
      ruleset,
      providers: this.providers,
      onsave: () => this.loadData(),
    });
  }

  async deleteRuleset(ruleset) {
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
      app.alerts.show(
        { type: 'success' },
        app.translator.trans('huoxin-filter-rule-manager.admin.delete_success')
      );
      m.redraw();
    } catch (err) {
      console.error('Failed to delete ruleset:', err);
      app.alerts.show(
        { type: 'error' },
        app.translator.trans('huoxin-filter-rule-manager.admin.delete_error')
      );
    }
  }

  async toggleActive(ruleset, isActive) {
    if (this.toggling.has(ruleset.id())) return;
    this.toggling.add(ruleset.id());
    m.redraw();

    try {
      await ruleset.save({ isActive });
    } catch (err) {
      console.error('Failed to toggle isActive:', err);
      app.alerts.show(
        { type: 'error' },
        app.translator.trans('huoxin-filter-rule-manager.admin.toggle_error')
      );
    } finally {
      this.toggling.delete(ruleset.id());
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
  async move(filteredIndex, delta, filteredList) {
    if (this.reordering) return;
    const target = filteredIndex + delta;
    if (target < 0 || target >= filteredList.length) return;

    const a = filteredList[filteredIndex];
    const b = filteredList[target];

    // Swap a and b's positions in the full ruleset array.
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
      app.alerts.show(
        { type: 'error' },
        app.translator.trans('huoxin-filter-rule-manager.admin.reorder_error')
      );
      await this.loadData();
    } finally {
      this.reordering = false;
      m.redraw();
    }
  }
}
