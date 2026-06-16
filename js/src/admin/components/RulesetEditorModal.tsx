/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import app from 'flarum/admin/app';
import Modal, { ModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import Select from 'flarum/common/components/Select';
import Stream from 'flarum/common/utils/Stream';
import icon from 'flarum/common/helpers/icon';
import type Mithril from 'mithril';

import RuleBuilder from './RuleBuilder';
import filterEngine from '../../common/FilterEngine';

import InlineTagSelector from './InlineTagSelector';
import { parseExpression } from '../utils/ExpressionParser';

export interface RulesetEditorModalAttrs extends ModalAttrs {
  ruleset?: any;
  providers: any[];
  onsave?: () => void;
}

/**
 * Sectioned editor for a ruleset:
 *
 *   1. General   — name, priority, active toggle
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
export default class RulesetEditorModal extends Modal<RulesetEditorModalAttrs> {
  ruleset: any;
  providers!: any[];
  loading: boolean = false;
  messageTextarea: HTMLTextAreaElement | null = null;
  flagMessageTextarea: HTMLTextAreaElement | null = null;
  showIconPicker: boolean = false;
  tagsLoading: boolean = false;
  availableTags: any[] = [];
  closeIconPickerHandler: any;

  name!: Stream<string>;
  expression!: Stream<string>;
  effectType!: Stream<string>;
  displayMode!: Stream<string>;
  message!: Stream<string>;
  flagMessage!: Stream<string>;
  evaluateAllRules!: Stream<boolean>;
  evaluateTitle!: Stream<boolean | null>;
  evasionActive!: Stream<boolean | null>;
  evasionTimeout!: Stream<number | null>;
  evasionThreshold!: Stream<number | null>;
  blockCascade!: Stream<boolean>;
  isActive!: Stream<boolean>;
  autoFlag!: Stream<boolean | null>;
  requireApproval!: Stream<boolean | null>;
  scopeType!: Stream<string>;
  scopeTagIds!: Stream<number[]>;
  displaySettings!: Stream<any>;

  oninit(vnode: Mithril.Vnode<RulesetEditorModalAttrs, this>) {
    super.oninit(vnode);

    this.ruleset = this.attrs.ruleset;
    this.providers = this.attrs.providers;
    this.loading = false;
    this.messageTextarea = null;
    this.flagMessageTextarea = null;
    this.showIconPicker = false;
    this.tagsLoading = false;
    this.availableTags = [];
    if (app.initializers.has('flarum-tags')) {
      this.tagsLoading = true;
      app.store
        .find('tags', { include: 'parent' })
        .then((tags: any) => {
          this.availableTags = tags || [];
          this.tagsLoading = false;
          (window as any).m.redraw();
        })
        .catch(() => {
          this.tagsLoading = false;
          (window as any).m.redraw();
        });
    }

    this.name = Stream(this.ruleset ? this.ruleset.name() : '');
    this.expression = Stream(this.ruleset ? this.ruleset.expression() : '');
    this.effectType = Stream(this.ruleset ? this.ruleset.effectType() : 'info');
    this.displayMode = Stream(this.ruleset ? this.ruleset.displayMode() : 'banner');
    this.message = Stream(this.ruleset ? this.ruleset.message() : '');
    this.flagMessage = Stream(this.ruleset ? this.ruleset.flagMessage() : '');
    this.evaluateAllRules = Stream(this.ruleset ? this.ruleset.evaluateAllRules() : false);
    this.evaluateTitle = Stream(this.ruleset ? this.ruleset.evaluateTitle() : null);
    this.evasionActive = Stream(this.ruleset ? this.ruleset.evasionActive() : null);
    this.evasionTimeout = Stream(this.ruleset ? this.ruleset.evasionTimeout() : null);
    this.evasionThreshold = Stream(this.ruleset ? this.ruleset.evasionThreshold() : null);
    this.blockCascade = Stream(this.ruleset ? this.ruleset.blockCascade() : false);
    this.isActive = Stream(this.ruleset ? this.ruleset.isActive() : true);
    this.autoFlag = Stream(this.ruleset ? this.ruleset.autoFlag() : null);
    this.requireApproval = Stream(this.ruleset ? this.ruleset.requireApproval() : null);
    this.scopeType = Stream(this.ruleset ? this.ruleset.scopeType() : 'global');
    this.scopeTagIds = Stream(this.ruleset ? this.ruleset.scopeTagIds() : []);
    this.displaySettings = Stream(this.ruleset ? Object.assign({}, this.ruleset.displaySettings() || {}) : {});
  }

  oncreate(vnode: Mithril.VnodeDOM<RulesetEditorModalAttrs, this>) {
    super.oncreate(vnode);
    this.closeIconPickerHandler = (e: MouseEvent) => {
      if (this.showIconPicker && !(e.target as Element).closest('.IconPickerInput')) {
        this.showIconPicker = false;
        (window as any).m.redraw();
      }
    };
    document.addEventListener('click', this.closeIconPickerHandler);
  }

  onremove(vnode: Mithril.VnodeDOM<RulesetEditorModalAttrs, this>) {
    if (this.closeIconPickerHandler) {
      document.removeEventListener('click', this.closeIconPickerHandler);
    }
    super.onremove(vnode);
  }

  className() {
    return 'RulesetEditorModal Modal--large';
  }

  title() {
    return this.ruleset
      ? app.translator.trans('huoxin-filter-rule-manager.admin.edit_ruleset')
      : app.translator.trans('huoxin-filter-rule-manager.admin.add_ruleset');
  }

  content(): Mithril.Children {
    return (
      <div className="Modal-body">
        <div className="Form">
          {this.generalSection()}
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

  nullableBooleanSelect(labelKey: string, helpKey: string, stream: Stream<boolean | null>): Mithril.Children {
    const val = stream();
    return (
      <div className="Form-group">
        <label>{app.translator.trans(labelKey)}</label>

        <div className="RulesetEditor-segmentedControl">
          <button type="button" className={`segmented-option ${val === null ? 'active' : ''}`} onclick={() => stream(null)}>
            {icon('fas fa-globe')} {app.translator.trans('huoxin-filter-rule-manager.admin.inherit_global_default')}
          </button>
          <button type="button" className={`segmented-option ${val === true ? 'active-enabled' : ''}`} onclick={() => stream(true)}>
            {icon('fas fa-check')} {app.translator.trans('huoxin-filter-rule-manager.admin.force_enabled')}
          </button>
          <button type="button" className={`segmented-option ${val === false ? 'active-disabled' : ''}`} onclick={() => stream(false)}>
            {icon('fas fa-times')} {app.translator.trans('huoxin-filter-rule-manager.admin.force_disabled')}
          </button>
        </div>

        <div className="helpText">{app.translator.trans(helpKey)}</div>
      </div>
    );
  }

  generalSection(): Mithril.Children {
    return (
      <div className="RulesetEditor-section">
        <div className="RulesetEditor-section-header RulesetEditor-section-header--with-toggle">
          <div className="RulesetEditor-section-header-title">
            <i className="fas fa-info-circle"></i>
            <h4>{app.translator.trans('huoxin-filter-rule-manager.admin.section_general')}</h4>
          </div>
          <Switch state={this.isActive()} onchange={(v: boolean) => this.isActive(v)}>
            {app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_is_active')}
          </Switch>
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_name')}</label>
          <input
            className="FormControl"
            value={this.name()}
            oninput={(e: any) => this.name(e.target.value)}
            placeholder={String(app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_name_placeholder'))}
            required
          />
        </div>

        <div className="Form-group">
          <Switch state={this.blockCascade()} onchange={(v: boolean) => this.blockCascade(v)}>
            {app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_block_cascade')}
          </Switch>
          <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_block_cascade_help')}</div>
        </div>

        <div className="Form-group">
          <Switch state={this.evaluateAllRules()} onchange={(v: boolean) => this.evaluateAllRules(v)}>
            {app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_evaluate_all_rules')}
          </Switch>
          <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_evaluate_all_rules_help')}</div>
        </div>
      </div>
    );
  }

  scopeSection(): Mithril.Children {
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
            onchange={(v: string) => this.scopeType(v)}
          />
        </div>

        {this.scopeType() === 'tag' && (
          <div className="Form-group">
            <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_scope_tags')}</label>
            {app.initializers.has('flarum-tags') ? (
              (() => {
                const getTagsLabel = (window as any).flarum.core.compat['tags/common/helpers/tagsLabel'];
                const selectedTags = this.availableTags.filter((t) => (this.scopeTagIds() || []).includes(parseInt(t.id(), 10)));

                return (
                  <div className="RulesetEditor-tagsSelection">
                    {selectedTags.length > 0 && getTagsLabel && (
                      <div className="RulesetEditor-tagsSelection-labels" style={{ marginBottom: '10px' }}>
                        {getTagsLabel(selectedTags)}
                      </div>
                    )}
                    <InlineTagSelector tags={this.availableTags} selectedIds={this.scopeTagIds} />
                  </div>
                );
              })()
            ) : (
              <span className="helpText">Flarum Tags extension is required.</span>
            )}
            <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_scope_tags_help')}</div>
          </div>
        )}
      </div>
    );
  }

  displaySetting(key: string, val?: any): any {
    let settings = this.displaySettings() || {};
    if (typeof val !== 'undefined') {
      const newSettings = Object.assign({}, settings);
      newSettings[key] = val;
      this.displaySettings(newSettings);
      return val;
    }
    return settings[key];
  }

  displaySection(): Mithril.Children {
    const effect = this.effectType();
    const displayMode = this.displayMode();
    const tokens = this.availableTokens();
    const templateName = this.displaySetting('template') || 'builtin';
    const templates = (app as any).filterRuleManager.getTemplates();
    const SettingsComponent = (app as any).filterRuleManager.getTemplateSettingsComponent(templateName);

    return (
      <div className="RulesetEditor-section">
        <div className="RulesetEditor-section-header">
          <i className="fas fa-eye"></i>
          <h4>{app.translator.trans('huoxin-filter-rule-manager.admin.section_display')}</h4>
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_effect_type')}</label>
          <div className="RulesetEditor-effectSelector">
            {['info', 'warning', 'block', 'silent'].map((value) => (
              <button
                type="button"
                key={value}
                className={`RulesetEditor-effectOption RulesetEditor-effectOption--${value} ${effect === value ? 'active' : ''}`}
                onclick={() => {
                  this.effectType(value);
                  if (value === 'silent' && !this.message()) {
                    this.message('Silent Rule');
                  }
                }}
              >
                {icon(this.effectIcon(value))}
                <span>{app.translator.trans(`huoxin-filter-rule-manager.admin.effects.${value}`)}</span>
              </button>
            ))}
          </div>
          <div className="helpText">{app.translator.trans(`huoxin-filter-rule-manager.admin.effects.${effect}_help`)}</div>
        </div>

        {effect !== 'silent' && (
          <div>
            <div className="Form-group">
              <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_message')}</label>
              <textarea
                className="FormControl"
                oncreate={(vnode) => {
                  this.messageTextarea = vnode.dom as HTMLTextAreaElement;
                }}
                onremove={() => {
                  this.messageTextarea = null;
                }}
                value={this.message()}
                oninput={(e: any) => this.message(e.target.value)}
                placeholder={String(app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_message_placeholder'))}
                rows={2}
                required
              ></textarea>
              <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.message_help')}</div>
              {this.tokenChipsBlock(tokens, 'message')}
            </div>

            <hr className="RulesetEditor-divider" />

            <div className="Form-group">
              <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_display_mode')}</label>
              <Select
                options={
                  (app as any).filterRuleManager && typeof (app as any).filterRuleManager.getDisplayModes === 'function'
                    ? Object.entries((app as any).filterRuleManager.getDisplayModes()).reduce((acc: any, [key, translationKey]: [string, any]) => {
                        acc[key] = String(app.translator.trans(translationKey));
                        return acc;
                      }, {})
                    : { banner: String(app.translator.trans('huoxin-filter-rule-manager.admin.displays.banner')) }
                }
                value={displayMode}
                onchange={(v: string) => this.displayMode(v)}
              />
              <div className="helpText">{app.translator.trans(`huoxin-filter-rule-manager.admin.displays.${displayMode}_help`)}</div>
            </div>

            <div className="Form-group">
              <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_custom_title')}</label>
              <input
                type="text"
                className="FormControl"
                placeholder={String(app.translator.trans(`huoxin-filter-rule-manager.forum.modal_title_${effect}`))}
                value={this.displaySetting('title') || ''}
                oninput={(e: any) => this.displaySetting('title', e.target.value)}
              />
              <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_custom_title_help')}</div>
            </div>

            <div className="Form-group">
              <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_display_template')}</label>
              <Select
                options={
                  (app as any).filterRuleManager && typeof (app as any).filterRuleManager.getTemplates === 'function'
                    ? Object.keys((app as any).filterRuleManager.getTemplates()).reduce((acc: any, k) => {
                        const transKey = `huoxin-filter-rule-manager.admin.templates.${k}`;
                        const translated = String(app.translator.trans(transKey));
                        acc[k] = translated !== transKey && translated ? translated : k;
                        return acc;
                      }, {})
                    : { builtin: String(app.translator.trans('huoxin-filter-rule-manager.admin.templates.builtin')) || 'Built-in' }
                }
                value={this.displaySetting('template') || 'builtin'}
                onchange={(val: string) => this.displaySetting('template', val)}
              />
            </div>

            {SettingsComponent && <SettingsComponent displaySetting={this.displaySetting.bind(this)} effectType={effect} displayMode={displayMode} />}

            <hr className="RulesetEditor-divider" />

            <div className="Form-group">
              <label>{app.translator.trans('huoxin-filter-rule-manager.admin.preview')}</label>
              {this.previewBlock(effect, this.message() || String(app.translator.trans('huoxin-filter-rule-manager.admin.preview_placeholder')))}
            </div>
          </div>
        )}
      </div>
    );
  }

  moderationSection(): Mithril.Children {
    return (
      <div className="RulesetEditor-section">
        <div className="RulesetEditor-section-header">
          <i className="fas fa-shield-alt"></i>
          <h4>{app.translator.trans('huoxin-filter-rule-manager.admin.section_moderation')}</h4>
        </div>

        {this.nullableBooleanSelect(
          'huoxin-filter-rule-manager.admin.ruleset_auto_flag',
          'huoxin-filter-rule-manager.admin.ruleset_auto_flag_help',
          this.autoFlag
        )}

        {this.nullableBooleanSelect(
          'huoxin-filter-rule-manager.admin.ruleset_require_approval',
          'huoxin-filter-rule-manager.admin.ruleset_require_approval_help',
          this.requireApproval
        )}

        {this.requireApproval() === true && this.autoFlag() === false ? (
          <div className="Alert Alert--warning RulesetEditor-warningAlert">
            <p>
              <i className="fas fa-exclamation-circle"></i>{' '}
              {app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_approval_without_flag_warning')}
            </p>
          </div>
        ) : null}

        <hr className="RulesetEditor-divider" />

        {this.nullableBooleanSelect(
          'huoxin-filter-rule-manager.admin.ruleset_evasion_active',
          'huoxin-filter-rule-manager.admin.ruleset_evasion_active_help',
          this.evasionActive
        )}

        {this.evasionActive() === true ? (
          <div className="Form-group">
            <div className="RulesetEditor-inline-inputs">
              <div className="RulesetEditor-inline-input">
                <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_evasion_timeout')}</label>
                <input
                  className="FormControl"
                  type="number"
                  min="0"
                  step="1"
                  value={this.evasionTimeout() === null ? '' : this.evasionTimeout()!}
                  oninput={(e: any) => {
                    const val = e.target.value;
                    this.evasionTimeout(val === '' ? null : Math.max(0, parseInt(val, 10)) || 0);
                  }}
                  placeholder={String(app.translator.trans('huoxin-filter-rule-manager.admin.inherit_global_default'))}
                />
                <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_evasion_timeout_help')}</div>
              </div>
              <div className="RulesetEditor-inline-input">
                <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_evasion_threshold')}</label>
                <input
                  className="FormControl"
                  type="number"
                  min="1"
                  step="1"
                  value={this.evasionThreshold() === null ? '' : this.evasionThreshold()!}
                  oninput={(e: any) => {
                    const val = e.target.value;
                    this.evasionThreshold(val === '' ? null : Math.max(1, parseInt(val, 10)) || 1);
                  }}
                  placeholder={String(app.translator.trans('huoxin-filter-rule-manager.admin.inherit_global_default'))}
                />
                <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_evasion_threshold_help')}</div>
              </div>
            </div>
          </div>
        ) : null}

        <hr className="RulesetEditor-divider" />

        {this.autoFlag() !== false || this.requireApproval() !== false || this.evasionActive() !== false ? (
          <div className="Form-group">
            <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_flag_message')}</label>
            <textarea
              className="FormControl"
              oncreate={(vnode) => {
                this.flagMessageTextarea = vnode.dom as HTMLTextAreaElement;
              }}
              onremove={() => {
                this.flagMessageTextarea = null;
              }}
              value={this.flagMessage()}
              oninput={(e: any) => this.flagMessage(e.target.value)}
              placeholder={String(app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_flag_message_placeholder'))}
              rows={2}
            ></textarea>
            <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_flag_message_help')}</div>
            {this.tokenChipsBlock(this.availableTokens(), 'flagMessage')}
          </div>
        ) : null}
      </div>
    );
  }

  rulesSection(): Mithril.Children {
    return (
      <div className="RulesetEditor-section">
        <div className="RulesetEditor-section-header">
          <i className="fas fa-sliders-h"></i>
          <h4>{app.translator.trans('huoxin-filter-rule-manager.admin.section_rules')}</h4>
        </div>

        <RuleBuilder expression={this.expression()} onchange={(v: string) => this.expression(v)} providers={this.providers} />

        <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.rule_regex_warning')}</div>
      </div>
    );
  }

  availableTokens() {
    const seen = new Set();
    const out: any[] = [];

    const push = (token: any, source: string) => {
      if (!token || !token.name || seen.has(token.name)) return;
      seen.add(token.name);
      out.push({
        name: token.name,
        description: token.description || '',
        source,
      });
    };

    let ast = null;
    try {
      ast = parseExpression(this.expression() || '');
    } catch (e) {
      // Ignore parse errors, user might be typing
    }

    const activeRules: any[] = [];
    const traverse = (node: any) => {
      if (!node) return;
      if (node.type === 'rule' && node.provider && node.ruleType) {
        activeRules.push(node);
      } else if (node.type === 'logical') {
        traverse(node.left);
        traverse(node.right);
      } else if (node.type === 'not') {
        traverse(node.node);
      }
    };
    traverse(ast);

    for (const rule of activeRules) {
      const fp =
        (app as any).filterRuleManager && typeof (app as any).filterRuleManager.getProvider === 'function'
          ? (app as any).filterRuleManager.getProvider(rule.provider)
          : null;
      if (fp && typeof fp.getProvidedTokens === 'function') {
        const ftokens = fp.getProvidedTokens(rule.ruleType) || [];
        ftokens.forEach((t: any) => push(t, `${rule.provider}/${rule.ruleType}`));
        continue;
      }

      const meta = (this.providers || []).find((p) => p.provider === rule.provider && p.type === rule.ruleType);
      const tokens = meta && Array.isArray(meta.tokens) ? meta.tokens : [];
      tokens.forEach((t) => push(t, `${rule.provider}/${rule.ruleType}`));
    }

    return out;
  }

  tokenChipsBlock(tokens: any[], targetField: string): Mithril.Children {
    if (!tokens || tokens.length === 0) {
      return <div className="TokenHints TokenHints--empty">{app.translator.trans('huoxin-filter-rule-manager.admin.tokens_none')}</div>;
    }

    return (
      <div className="TokenHints">
        <div className="TokenHints-label">{app.translator.trans('huoxin-filter-rule-manager.admin.tokens_available')}</div>
        <div className="TokenHints-list">
          {tokens.map((t) => (
            <button
              type="button"
              className="TokenHints-chip"
              key={t.name}
              title={t.description || t.name}
              onclick={() => this.insertToken(t.name, targetField)}
            >
              <code>{`{{${t.name}}}`}</code>
              {t.description && <span className="TokenHints-chip-desc">{t.description}</span>}
            </button>
          ))}
        </div>
      </div>
    );
  }

  insertToken(name: string, targetField: string) {
    const insertion = `{{${name}}}`;
    const stream = targetField === 'flagMessage' ? this.flagMessage : this.message;
    const ref = targetField === 'flagMessage' ? this.flagMessageTextarea : this.messageTextarea;

    const current = stream() || '';

    if (!ref) {
      stream(current + insertion);
      return;
    }

    const start = ref.selectionStart != null ? ref.selectionStart : current.length;
    const end = ref.selectionEnd != null ? ref.selectionEnd : current.length;
    const next = current.substring(0, start) + insertion + current.substring(end);

    stream(next);
    ref.value = next;
    ref.focus();
    const cursor = start + insertion.length;
    try {
      ref.setSelectionRange(cursor, cursor);
    } catch (e) {
      /* ignore */
    }
  }

  previewBlock(effect: string, message: string): Mithril.Children {
    const settings = this.displaySettings() || {};
    const templateName = settings.template || 'builtin';

    let TemplateComponent = filterEngine.getTemplate(templateName) || filterEngine.getTemplate('builtin');

    const sampleTokens: any = {};
    const available = this.availableTokens() || [];
    available.forEach((t) => {
      sampleTokens[t.name] = `[${t.name}]`;
    });

    if (available.length === 0) {
      sampleTokens['matched_word'] = '[matched_word]';
      sampleTokens['matched_pattern'] = '[matched_pattern]';
      sampleTokens['matched_string'] = '[matched_string]';
    }

    const rendered = filterEngine.interpolate(message, sampleTokens);

    const dummyAlert = {
      type: effect,
      message: rendered,
      displaySettings: settings,
      key: 'preview-alert',
    };

    if (!TemplateComponent) {
      return <div>Loading template...</div>;
    }

    return (
      <div className="FilterRuleManager-preview">
        <TemplateComponent alert={dummyAlert} variant={this.displayMode()} />
      </div>
    );
  }

  validationBlock(): Mithril.Children {
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

  actionsBlock(): Mithril.Children {
    return (
      <div className="Form-group RulesetEditor-actions">
        <Button className="Button Button--primary" loading={this.loading} disabled={!this.canSave()} onclick={() => this.save()}>
          {app.translator.trans('huoxin-filter-rule-manager.admin.save')}
        </Button>
        <Button className="Button" onclick={() => this.hide()}>
          {app.translator.trans('huoxin-filter-rule-manager.admin.cancel')}
        </Button>
      </div>
    );
  }

  effectIcon(effect: string) {
    if (effect === 'block') return 'fas fa-ban';
    if (effect === 'warning') return 'fas fa-exclamation-triangle';
    if (effect === 'silent') return 'fas fa-user-secret';
    return 'fas fa-info-circle';
  }

  canSave() {
    if (this.loading) return false;
    if (!this.name() || !this.name().trim()) return false;
    if (this.scopeType() === 'tag' && (!this.scopeTagIds() || this.scopeTagIds().length === 0)) return false;
    if (!this.expression() || !this.expression().trim()) return false;
    return true;
  }

  validationError() {
    if (!this.name() || !this.name().trim()) {
      return app.translator.trans('huoxin-filter-rule-manager.admin.validation.name_required');
    }
    if (this.scopeType() === 'tag' && (!this.scopeTagIds() || this.scopeTagIds().length === 0)) {
      return app.translator.trans('huoxin-filter-rule-manager.admin.validation.tags_required');
    }
    if (!this.expression() || !this.expression().trim()) {
      return app.translator.trans('huoxin-filter-rule-manager.admin.validation.expression_required');
    }
    return null;
  }

  onsubmit(e: any) {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    this.save();
  }

  async save() {
    if (!this.canSave()) return;

    this.loading = true;
    (window as any).m.redraw();

    const data = {
      name: this.name(),
      expression: this.expression(),
      effectType: this.effectType(),
      displayMode: this.displayMode(),
      message: this.message(),
      flagMessage: this.flagMessage(),
      evaluateAllRules: this.evaluateAllRules(),
      evaluateTitle: this.evaluateTitle(),
      evasionActive: this.evasionActive(),
      evasionTimeout: this.evasionTimeout(),
      evasionThreshold: this.evasionThreshold(),
      blockCascade: this.blockCascade(),
      isActive: this.isActive(),
      autoFlag: this.autoFlag(),
      requireApproval: this.requireApproval(),
      scopeType: this.scopeType(),
      scopeTagIds: this.scopeTagIds(),
      displaySettings: this.displaySettings(),
    };

    try {
      if (this.ruleset) {
        await this.ruleset.save(data);
      } else {
        await app.store.createRecord('filter-rule-rulesets').save(data);
      }

      app.alerts.show({ type: 'success' }, app.translator.trans('huoxin-filter-rule-manager.admin.save_success'));

      if (typeof this.attrs.onsave === 'function') this.attrs.onsave();
      this.hide();
    } catch (err) {
      console.error('Failed to save ruleset:', err);
      app.alerts.show({ type: 'error' }, app.translator.trans('huoxin-filter-rule-manager.admin.save_error'));
      this.loading = false;
      (window as any).m.redraw();
    }
  }
}
