import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import Select from 'flarum/common/components/Select';
import Stream from 'flarum/common/utils/Stream';
import icon from 'flarum/common/helpers/icon';

import RuleBuilder from './RuleBuilder';
import filterEngine from '../../common/FilterEngine';

/**
 * Sectioned editor for a ruleset:
 *
 *   1. Basics   — name, priority, active toggle
 *   2. Scope    — global/normal/private/tag (+ tag IDs when applicable)
 *   3. Display  — effect, display mode, message + token hint chips + preview
 *   4. Rules    — operator + RuleBuilder
 *
 * Token hints below the message textarea are discovered from each configured
 * rule's provider. Frontend-only providers expose them via
 * `provider.getProvidedTokens(type)`; backend providers ship them through the
 * `/filter-rule-providers` endpoint, attached to each (provider, type) row
 * in `this.providers`. Clicking a chip inserts `{{token}}` at the textarea
 * cursor.
 */
export default class RulesetEditorModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);

    this.ruleset = this.attrs.ruleset;
    this.providers = this.attrs.providers;
    this.loading = false;
    this.messageTextarea = null;

    this.name = Stream(this.ruleset ? this.ruleset.name() : '');
    this.priority = Stream(this.ruleset ? this.ruleset.priority() : 0);
    this.ruleOperator = Stream(this.ruleset ? this.ruleset.ruleOperator() : 'AND');
    this.effectType = Stream(this.ruleset ? this.ruleset.effectType() : 'info');
    this.displayMode = Stream(this.ruleset ? this.ruleset.displayMode() : 'banner');
    this.message = Stream(this.ruleset ? this.ruleset.message() : '');
    this.blockCascade = Stream(this.ruleset ? this.ruleset.blockCascade() : false);
    this.isActive = Stream(this.ruleset ? this.ruleset.isActive() : true);
    this.autoFlag = Stream(this.ruleset ? this.ruleset.autoFlag() : false);
    this.requireApproval = Stream(this.ruleset ? this.ruleset.requireApproval() : false);
    this.scopeType = Stream(this.ruleset ? this.ruleset.scopeType() : 'global');
    this.scopeTagIds = Stream(this.ruleset ? this.ruleset.scopeTagIds() : []);

    const rawRules = (this.ruleset && this.ruleset.rules && this.ruleset.rules()) || [];
    const initialRules = rawRules.map((r) => {
      const get = (key) => (typeof r[key] === 'function' ? r[key]() : r[key]);
      return {
        provider: get('provider') || '',
        type: get('type') || '',
        config: get('config') || {},
        negate: !!get('negate'),
        sortOrder: typeof get('sortOrder') === 'number' ? get('sortOrder') : undefined,
      };
    });
    this.rules = Stream(initialRules);
  }

  className() {
    return 'RulesetEditorModal Modal--large';
  }

  title() {
    return this.ruleset
      ? app.translator.trans('huoxin-filter-rule-manager.admin.edit_ruleset')
      : app.translator.trans('huoxin-filter-rule-manager.admin.add_ruleset');
  }

  content() {
    return (
      <div className="Modal-body">
        <div className="Form">
          {this.basicsSection()}
          {this.scopeSection()}
          {this.displaySection()}
          {this.moderationSection()}
          {this.rulesSection()}
          {this.validationBlock()}
          {this.actionsBlock()}
        </div>
      </div>
    );
  }

  // ── Sections ─────────────────────────────────────────────────────────────

  basicsSection() {
    return (
      <div className="RulesetEditor-section">
        <div className="RulesetEditor-section-header">
          <i className="fas fa-tag"></i>
          <h4>{app.translator.trans('huoxin-filter-rule-manager.admin.section_basics')}</h4>
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_name')}</label>
          <input
            className="FormControl"
            value={this.name()}
            oninput={(e) => this.name(e.target.value)}
            placeholder={app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_name_placeholder')}
            required
          />
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_priority')}</label>
          <input
            className="FormControl RulesetEditor-priorityInput"
            type="number"
            value={this.priority()}
            oninput={(e) => this.priority(parseInt(e.target.value, 10) || 0)}
          />
          <div className="helpText">
            {app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_priority_help')}
          </div>
        </div>

        <div className="Form-group">
          <Switch state={this.isActive()} onchange={this.isActive}>
            {app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_is_active')}
          </Switch>
        </div>
      </div>
    );
  }

  scopeSection() {
    return (
      <div className="RulesetEditor-section">
        <div className="RulesetEditor-section-header">
          <i className="fas fa-crosshairs"></i>
          <h4>{app.translator.trans('huoxin-filter-rule-manager.admin.section_scope')}</h4>
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_scope_type')}</label>
          <Select
            options={{
              global: app.translator.trans('huoxin-filter-rule-manager.admin.scopes.global'),
              normal_post: app.translator.trans('huoxin-filter-rule-manager.admin.scopes.normal_post'),
              private_post: app.translator.trans('huoxin-filter-rule-manager.admin.scopes.private_post'),
              tag: app.translator.trans('huoxin-filter-rule-manager.admin.scopes.tag'),
            }}
            value={this.scopeType()}
            onchange={this.scopeType}
          />
        </div>

        {this.scopeType() === 'tag' && (
          <div className="Form-group">
            <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_scope_tags')}</label>
            <input
              className="FormControl"
              value={(this.scopeTagIds() || []).join(', ')}
              onchange={(e) => this.scopeTagIds(
                e.target.value
                  .split(',')
                  .map((s) => parseInt(s.trim(), 10))
                  .filter((n) => Number.isInteger(n) && n > 0)
              )}
              placeholder="1, 5, 12"
            />
            <div className="helpText">
              {app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_scope_tags_help')}
            </div>
          </div>
        )}
      </div>
    );
  }

  displaySection() {
    const effect = this.effectType();
    const displayMode = this.displayMode();
    const tokens = this.availableTokens();

    return (
      <div className="RulesetEditor-section">
        <div className="RulesetEditor-section-header">
          <i className="fas fa-bell"></i>
          <h4>{app.translator.trans('huoxin-filter-rule-manager.admin.section_display')}</h4>
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_effect_type')}</label>
          <div className="RulesetEditor-effectSelector">
            {['info', 'warning', 'block'].map((value) => (
              <button
                type="button"
                key={value}
                className={`RulesetEditor-effectOption RulesetEditor-effectOption--${value} ${effect === value ? 'active' : ''}`}
                onclick={() => this.effectType(value)}
              >
                {icon(this.effectIcon(value))}
                <span>{app.translator.trans(`huoxin-filter-rule-manager.admin.effects.${value}`)}</span>
              </button>
            ))}
          </div>
          <div className="helpText">
            {app.translator.trans(`huoxin-filter-rule-manager.admin.effects.${effect}_help`)}
          </div>
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_display_mode')}</label>
          <Select
            options={{
              banner: app.translator.trans('huoxin-filter-rule-manager.admin.displays.banner'),
              header_banner: app.translator.trans('huoxin-filter-rule-manager.admin.displays.header_banner'),
              sidebar: app.translator.trans('huoxin-filter-rule-manager.admin.displays.sidebar'),
              toast: app.translator.trans('huoxin-filter-rule-manager.admin.displays.toast'),
              modal: app.translator.trans('huoxin-filter-rule-manager.admin.displays.modal'),
            }}
            value={displayMode}
            onchange={this.displayMode}
          />
          <div className="helpText">
            {app.translator.trans(`huoxin-filter-rule-manager.admin.displays.${displayMode}_help`)}
          </div>
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_message')}</label>
          <textarea
            className="FormControl"
            oncreate={(vnode) => { this.messageTextarea = vnode.dom; }}
            onremove={() => { this.messageTextarea = null; }}
            value={this.message()}
            oninput={(e) => this.message(e.target.value)}
            placeholder={app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_message_placeholder')}
            rows={2}
            required
          ></textarea>
          <div className="helpText">
            {app.translator.trans('huoxin-filter-rule-manager.admin.message_help')}
          </div>
          {this.tokenChipsBlock(tokens)}
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.preview')}</label>
          {this.previewBlock(effect, this.message() || app.translator.trans('huoxin-filter-rule-manager.admin.preview_placeholder'))}
        </div>

        <div className="Form-group">
          <Switch state={this.blockCascade()} onchange={this.blockCascade}>
            {app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_block_cascade')}
          </Switch>
          <div className="helpText">
            {app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_block_cascade_help')}
          </div>
        </div>
      </div>
    );
  }

  moderationSection() {
    return (
      <div className="RulesetEditor-section">
        <div className="RulesetEditor-section-header">
          <i className="fas fa-shield-alt"></i>
          <h4>{app.translator.trans('huoxin-filter-rule-manager.admin.section_moderation')}</h4>
        </div>

        <div className="Form-group">
          <Switch state={this.autoFlag()} onchange={this.autoFlag}>
            {app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_auto_flag')}
          </Switch>
          <div className="helpText">
            {app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_auto_flag_help')}
          </div>
        </div>

        <div className="Form-group">
          <Switch state={this.requireApproval()} onchange={this.requireApproval}>
            {app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_require_approval')}
          </Switch>
          <div className="helpText">
            {app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_require_approval_help')}
          </div>
        </div>

        {this.requireApproval() && !this.autoFlag() ? (
          <div className="Alert Alert--warning">
            <p>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_approval_without_flag_warning')}</p>
          </div>
        ) : null}
      </div>
    );
  }

  rulesSection() {
    return (
      <div className="RulesetEditor-section">
        <div className="RulesetEditor-section-header">
          <i className="fas fa-sliders-h"></i>
          <h4>{app.translator.trans('huoxin-filter-rule-manager.admin.section_rules')}</h4>
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_rule_operator')}</label>
          <Select
            options={{
              AND: app.translator.trans('huoxin-filter-rule-manager.admin.rule_operator_and'),
              OR: app.translator.trans('huoxin-filter-rule-manager.admin.rule_operator_or'),
            }}
            value={this.ruleOperator()}
            onchange={this.ruleOperator}
          />
        </div>

        <RuleBuilder
          rules={this.rules()}
          onchange={this.rules}
          providers={this.providers}
        />

        <div className="helpText">
          {app.translator.trans('huoxin-filter-rule-manager.admin.rule_regex_warning')}
        </div>
      </div>
    );
  }

  // ── Bits ─────────────────────────────────────────────────────────────────

  /**
   * Returns the merged set of tokens exposed by every rule currently in the
   * ruleset. Each token is `{ name, description, source }`.
   *
   * Resolution order per rule:
   *   1. Frontend provider object (registered into FilterEngine in this bundle)
   *      via `getProvidedTokens(type)` — covers JS-only providers
   *   2. Backend provider metadata (the `tokens` field on `this.providers`
   *      rows, served by ListProvidersController) — covers PHP-only providers
   *
   * Tokens dedupe by name; first source wins.
   */
  availableTokens() {
    const seen = new Set();
    const out = [];

    const push = (token, source) => {
      if (!token || !token.name || seen.has(token.name)) return;
      seen.add(token.name);
      out.push({
        name: token.name,
        description: token.description || '',
        source,
      });
    };

    for (const rule of this.rules() || []) {
      if (!rule.provider || !rule.type) continue;

      // 1) Frontend provider
      const fp = (app.filterRuleManager && typeof app.filterRuleManager.getProvider === 'function')
        ? app.filterRuleManager.getProvider(rule.provider)
        : null;
      if (fp && typeof fp.getProvidedTokens === 'function') {
        const tokens = fp.getProvidedTokens(rule.type) || [];
        tokens.forEach((t) => push(t, `${rule.provider}/${rule.type}`));
        continue; // frontend takes precedence
      }

      // 2) Backend metadata from /providers
      const meta = (this.providers || []).find(
        (p) => p.provider === rule.provider && p.type === rule.type
      );
      const tokens = (meta && Array.isArray(meta.tokens)) ? meta.tokens : [];
      tokens.forEach((t) => push(t, `${rule.provider}/${rule.type}`));
    }

    return out;
  }

  tokenChipsBlock(tokens) {
    if (!tokens || tokens.length === 0) {
      return (
        <div className="TokenHints TokenHints--empty">
          {app.translator.trans('huoxin-filter-rule-manager.admin.tokens_none')}
        </div>
      );
    }

    return (
      <div className="TokenHints">
        <div className="TokenHints-label">
          {app.translator.trans('huoxin-filter-rule-manager.admin.tokens_available')}
        </div>
        <div className="TokenHints-list">
          {tokens.map((t) => (
            <button
              type="button"
              className="TokenHints-chip"
              key={t.name}
              title={t.description || t.name}
              onclick={() => this.insertToken(t.name)}
            >
              <code>{`{{${t.name}}}`}</code>
              {t.description && <span className="TokenHints-chip-desc">{t.description}</span>}
            </button>
          ))}
        </div>
      </div>
    );
  }

  insertToken(name) {
    const insertion = `{{${name}}}`;
    const textarea = this.messageTextarea;
    const current = this.message() || '';

    if (!textarea) {
      this.message(current + insertion);
      return;
    }

    const start = textarea.selectionStart != null ? textarea.selectionStart : current.length;
    const end   = textarea.selectionEnd   != null ? textarea.selectionEnd   : current.length;
    const next  = current.substring(0, start) + insertion + current.substring(end);

    this.message(next);
    // Update the DOM synchronously and restore the cursor right after the
    // insertion. m.redraw is async; updating textarea.value avoids the race.
    textarea.value = next;
    textarea.focus();
    const cursor = start + insertion.length;
    try { textarea.setSelectionRange(cursor, cursor); } catch (e) { /* ignore */ }
  }

  previewBlock(effect, message) {
    const iconName = effect === 'block'   ? 'fas fa-times-circle'
                   : effect === 'warning' ? 'fas fa-exclamation-triangle'
                   :                        'fas fa-info-circle';

    // Sample tokens so admins see something rendered for known names.
    const sampleTokens = {
      matched_word: 'spam',
      matched_pattern: '/sample/',
      matched_string: 'sample-match',
    };
    const rendered = filterEngine.interpolate(message, sampleTokens);

    return (
      <div className={`FilterRuleManager-preview FilterRuleManager-item--${effect}`}>
        <span className="FilterRuleManager-item-icon">{icon(iconName)}</span>
        <span className="FilterRuleManager-item-message">{m.trust(rendered)}</span>
      </div>
    );
  }

  validationBlock() {
    const err = this.validationError();
    if (!err) return null;
    return (
      <div className="Form-group">
        <div className="Alert Alert--error">
          <i className="fas fa-exclamation-circle"></i> {err}
        </div>
      </div>
    );
  }

  actionsBlock() {
    return (
      <div className="Form-group RulesetEditor-actions">
        <Button
          className="Button Button--primary"
          loading={this.loading}
          disabled={!this.canSave()}
          onclick={() => this.save()}
        >
          {app.translator.trans('huoxin-filter-rule-manager.admin.save')}
        </Button>
        <Button className="Button" onclick={() => this.hide()}>
          {app.translator.trans('huoxin-filter-rule-manager.admin.cancel')}
        </Button>
      </div>
    );
  }

  effectIcon(effect) {
    if (effect === 'block')   return 'fas fa-ban';
    if (effect === 'warning') return 'fas fa-exclamation-triangle';
    return 'fas fa-info-circle';
  }

  // ── Save flow ────────────────────────────────────────────────────────────

  canSave() {
    if (this.loading) return false;
    if (!this.name() || !this.name().trim()) return false;
    if (!this.message() || !this.message().trim()) return false;
    if (this.scopeType() === 'tag' && (!this.scopeTagIds() || this.scopeTagIds().length === 0)) return false;
    if ((this.rules() || []).length === 0) return false;
    return true;
  }

  validationError() {
    if (!this.name() || !this.name().trim()) {
      return app.translator.trans('huoxin-filter-rule-manager.admin.validation.name_required');
    }
    if (!this.message() || !this.message().trim()) {
      return app.translator.trans('huoxin-filter-rule-manager.admin.validation.message_required');
    }
    if (this.scopeType() === 'tag' && (!this.scopeTagIds() || this.scopeTagIds().length === 0)) {
      return app.translator.trans('huoxin-filter-rule-manager.admin.validation.tags_required');
    }
    if ((this.rules() || []).length === 0) {
      return app.translator.trans('huoxin-filter-rule-manager.admin.validation.rules_required');
    }
    return null;
  }

  onsubmit(e) {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    this.save();
  }

  async save() {
    if (!this.canSave()) return;

    this.loading = true;
    m.redraw();

    const data = {
      name: this.name(),
      priority: this.priority(),
      ruleOperator: this.ruleOperator(),
      effectType: this.effectType(),
      displayMode: this.displayMode(),
      message: this.message(),
      blockCascade: this.blockCascade(),
      isActive: this.isActive(),
      autoFlag: this.autoFlag(),
      requireApproval: this.requireApproval(),
      scopeType: this.scopeType(),
      scopeTagIds: this.scopeTagIds(),
      rules: this.rules(),
    };

    try {
      if (this.ruleset) {
        await this.ruleset.save(data);
      } else {
        await app.store.createRecord('filter-rule-rulesets').save(data);
      }

      app.alerts.show(
        { type: 'success' },
        app.translator.trans('huoxin-filter-rule-manager.admin.save_success')
      );

      if (typeof this.attrs.onsave === 'function') this.attrs.onsave();
      this.hide();
    } catch (err) {
      console.error('Failed to save ruleset:', err);
      app.alerts.show(
        { type: 'error' },
        app.translator.trans('huoxin-filter-rule-manager.admin.save_error')
      );
      this.loading = false;
      m.redraw();
    }
  }
}
