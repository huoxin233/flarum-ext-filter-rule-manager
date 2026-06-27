/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import app from 'flarum/admin/app';
import { IFormModalAttrs } from 'flarum/common/components/FormModal';
import Form from 'flarum/common/components/Form';
import FormModal from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import Select from 'flarum/common/components/Select';
import GroupBadge from 'flarum/common/components/GroupBadge';
import type { ASTNode } from '../../common/FilterEngine';
import Stream from 'flarum/common/utils/Stream';
import Icon from 'flarum/common/components/Icon';
import type Mithril from 'mithril';
import type Model from 'flarum/common/Model';

import RuleBuilder from './RuleBuilder';
import filterEngine from '../../common/FilterEngine';

import InlineTagSelector from './InlineTagSelector';
import { parseExpression } from '../utils/ExpressionParser';

export interface RulesetEditorModalAttrs extends IFormModalAttrs {
  ruleset?: Model & Record<string, any>;
  providers: Record<string, unknown>[];
  onsave?: () => void;
}

/**
 * Sectioned editor for a ruleset:
 *
 *   1. General   — name, priority, active toggle
 *   2. Scope    — global/normal/private/tag (+ tag IDs when applicable)
 *   3. Display  — intervention, display mode, message + token hint chips + preview
 *   4. Rules    — operator + RuleBuilder
 *
 * Token hints below the message textarea are discovered from each configured
 * rule's provider. Frontend-only providers expose them via
 * `provider.getProvidedTokens(type)`; backend providers ship them through the
 * `/filter-rule-providers` endpoint, attached to each (provider, type) row
 * in `this.providers`. Clicking a chip inserts `{{token}}` at the textarea
 * cursor.
 */
export default class RulesetEditorModal extends FormModal<RulesetEditorModalAttrs> {
  static readonly isDismissibleViaBackdropClick = false;
  static readonly isDismissibleViaEscKey = false;

  ruleset?: Model & Record<string, any>;
  providers!: Record<string, unknown>[];
  loading: boolean = false;
  messageTextarea: HTMLTextAreaElement | null = null;
  flagMessageTextarea: HTMLTextAreaElement | null = null;
  showIconPicker: boolean = false;
  tagsLoading: boolean = false;
  showingDiscardConfirmation: boolean = false;
  availableTags: Model[] = [];
  closeIconPickerHandler: ((e: MouseEvent) => void) | null = null;

  name!: Stream<string>;
  expression!: Stream<string>;
  interventionType!: Stream<string>;
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
  bypassGroupIds!: Stream<number[]>;
  displaySettings!: Stream<Record<string, unknown>>;

  oninit(vnode: Mithril.Vnode<RulesetEditorModalAttrs, this>) {
    super.oninit(vnode);

    this.ruleset = this.attrs.ruleset;
    this.providers = this.attrs.providers;
    this.loading = false;
    this.messageTextarea = null;
    this.flagMessageTextarea = null;
    this.showIconPicker = false;
    this.tagsLoading = false;
    this.showingDiscardConfirmation = false;
    this.availableTags = [];
    if (app.initializers.has('flarum-tags')) {
      this.tagsLoading = true;
      app.store
        .find('tags', { include: 'parent' })
        .then((tags: any) => {
          this.availableTags = tags || [];
          this.tagsLoading = false;
          m.redraw();
        })
        .catch(() => {
          this.tagsLoading = false;
          m.redraw();
        });
    }

    this.name = Stream(this.ruleset ? this.ruleset.name() : '');
    this.expression = Stream(this.ruleset ? this.ruleset.expression() : '');
    this.interventionType = Stream(this.ruleset ? this.ruleset.interventionType() : 'info');
    this.displayMode = Stream(this.ruleset ? this.ruleset.displayMode() : 'none');
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
    this.bypassGroupIds = Stream(this.ruleset ? this.ruleset.bypassGroupIds() || [] : []);
    this.displaySettings = Stream(this.ruleset ? Object.assign({}, this.ruleset.displaySettings() || {}) : {});
  }

  oncreate(vnode: Mithril.VnodeDOM<RulesetEditorModalAttrs, this>) {
    super.oncreate(vnode);
    this.closeIconPickerHandler = (e: MouseEvent) => {
      if (this.showIconPicker && !(e.target as Element).closest('.IconPickerInput')) {
        this.showIconPicker = false;
        m.redraw();
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
    return 'FilterRuleManager-RulesetEditorModal Modal--large';
  }

  title() {
    return this.ruleset
      ? app.translator.trans('huoxin-filter-rule-manager.admin.edit_ruleset')
      : app.translator.trans('huoxin-filter-rule-manager.admin.add_ruleset');
  }

  content(): Mithril.Children {
    if (this.showingDiscardConfirmation) {
      return (
        <div className="Modal-body">
          <Form>
            <div className="FilterRuleManager-RulesetEditor-discardConfirmation">
              <i className="fas fa-exclamation-triangle FilterRuleManager-RulesetEditor-discardIcon"></i>
              <h3 className="FilterRuleManager-RulesetEditor-discardTitle">
                {app.translator.trans('huoxin-filter-rule-manager.admin.unsaved_changes_title')}
              </h3>
              <p className="helpText FilterRuleManager-RulesetEditor-discardMessage">
                {app.translator.trans('huoxin-filter-rule-manager.admin.unsaved_changes_message')}
              </p>
              <div className="Form-group FilterRuleManager-RulesetEditor-discardActions">
                <Button
                  className="Button Button--danger"
                  onclick={() => {
                    this.showingDiscardConfirmation = false;
                    super.hide();
                  }}
                >
                  {app.translator.trans('huoxin-filter-rule-manager.admin.discard_changes')}
                </Button>
                <Button
                  className="Button FilterRuleManager-RulesetEditor-discardCancel"
                  onclick={() => {
                    this.showingDiscardConfirmation = false;
                    m.redraw();
                  }}
                >
                  {app.translator.trans('huoxin-filter-rule-manager.admin.keep_editing')}
                </Button>
              </div>
            </div>
          </Form>
        </div>
      );
    }

    return (
      <div className="Modal-body">
        <Form>
          {this.generalSection()}
          {this.scopeSection()}
          {this.displaySection()}
          {this.moderationSection()}
          {this.rulesSection()}
          {this.validationBlock()}
          {this.actionsBlock()}
        </Form>
      </div>
    );
  }

  nullableBooleanSelect(labelKey: string, helpKey: string, stream: Stream<boolean | null>): Mithril.Children {
    const val = stream();
    return (
      <div className="Form-group">
        <label>{app.translator.trans(labelKey)}</label>
        <div className="FilterRuleManager-RulesetEditor-segmentedControl">
          <button type="button" className={`FilterRuleManager-segmented-option ${val === null ? 'active' : ''}`} onclick={() => stream(null)}>
            <Icon name="fas fa-globe" /> {app.translator.trans('huoxin-filter-rule-manager.admin.inherit_global_default')}
          </button>
          <button type="button" className={`FilterRuleManager-segmented-option ${val === true ? 'active-enabled' : ''}`} onclick={() => stream(true)}>
            <Icon name="fas fa-check" /> {app.translator.trans('huoxin-filter-rule-manager.admin.force_enabled')}
          </button>
          <button
            type="button"
            className={`FilterRuleManager-segmented-option ${val === false ? 'active-disabled' : ''}`}
            onclick={() => stream(false)}
          >
            <Icon name="fas fa-times" /> {app.translator.trans('huoxin-filter-rule-manager.admin.force_disabled')}
          </button>
        </div>
        <div className="helpText">{app.translator.trans(helpKey)}</div>
      </div>
    );
  }

  generalSection(): Mithril.Children {
    return (
      <div className="FilterRuleManager-RulesetEditor-section">
        <div className="FilterRuleManager-RulesetEditor-section-header FilterRuleManager-RulesetEditor-section-header--with-toggle">
          <div className="FilterRuleManager-RulesetEditor-section-header-title">
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
            oninput={(e: Event) => this.name((e.target as HTMLInputElement).value)}
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
      <div className="FilterRuleManager-RulesetEditor-section">
        <div className="FilterRuleManager-RulesetEditor-section-header">
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
                const getTagsLabel = (window as Record<string, any>).flarum.core.compat['tags/common/helpers/tagsLabel'];
                const selectedTags = this.availableTags.filter((t) => (this.scopeTagIds() || []).includes(parseInt(String(t.id()), 10)));

                return (
                  <div className="FilterRuleManager-RulesetEditor-tagsSelection">
                    {selectedTags.length > 0 && getTagsLabel && (
                      <div className="FilterRuleManager-RulesetEditor-tagsSelection-labels" style={{ marginBottom: '10px' }}>
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

        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_bypass_groups')}</label>
          <div className="FilterRuleManager-RulesetEditor-groupSelection">
            {app.store.all('groups').map((group: any) => {
              const id = parseInt(String(group.id()), 10);
              const isActive = (this.bypassGroupIds() || []).includes(id);
              return (
                <label className={`FilterRuleManager-RulesetEditor-groupOption ${isActive ? 'active' : ''}`} key={id}>
                  <input
                    type="checkbox"
                    checked={isActive}
                    onchange={(e: Event) => {
                      const checked = (e.target as HTMLInputElement).checked;
                      let current = this.bypassGroupIds() || [];
                      if (checked) {
                        current.push(id);
                      } else {
                        current = current.filter((g: number) => g !== id);
                      }
                      this.bypassGroupIds(current);
                    }}
                  />
                  <div className="FilterRuleManager-RulesetEditor-groupOption-content">
                    <GroupBadge group={group} label="" />
                    <span className="FilterRuleManager-RulesetEditor-groupOption-name">{String(group.namePlural() || group.name())}</span>
                  </div>
                </label>
              );
            })}
          </div>
          <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_bypass_groups_help')}</div>
        </div>
      </div>
    );
  }

  displaySetting(key: string, val?: unknown): unknown {
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
    const intervention = this.interventionType();
    const displayMode = this.displayMode();
    const tokens = this.availableTokens();
    const templateName = this.displaySetting('template') || 'builtin';
    const SettingsComponent = app.filterRuleManager.getTemplateSettingsComponent(templateName);

    return (
      <div className="FilterRuleManager-RulesetEditor-section">
        <div className="FilterRuleManager-RulesetEditor-section-header">
          <i className="fas fa-eye"></i>
          <h4>{app.translator.trans('huoxin-filter-rule-manager.admin.section_display')}</h4>
        </div>
        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_intervention_type')}</label>
          <div className="FilterRuleManager-RulesetEditor-interventionSelector">
            {['info', 'warning', 'block', 'silent'].map((value) => (
              <button
                type="button"
                key={value}
                className={`FilterRuleManager-RulesetEditor-interventionOption FilterRuleManager-RulesetEditor-interventionOption--${value} ${
                  intervention === value ? 'active' : ''
                }`}
                onclick={() => {
                  this.interventionType(value);
                  if (value === 'silent' && !this.message()) {
                    this.message('Silent Rule');
                  }
                }}
              >
                <Icon name={this.interventionIcon(value)} />
                <span>{app.translator.trans(`huoxin-filter-rule-manager.admin.interventions.${value}`)}</span>
              </button>
            ))}
          </div>
          <div className="helpText">{app.translator.trans(`huoxin-filter-rule-manager.admin.interventions.${intervention}_help`)}</div>
          {(intervention === 'info' || intervention === 'warning') && (
            <div className="Alert Alert--warning FilterRuleManager-RulesetEditor-warningAlert">
              <p>
                <i className="fas fa-exclamation-circle"></i> {app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_exposed_warning_text')}
              </p>
            </div>
          )}
        </div>
        <hr className="FilterRuleManager-RulesetEditor-divider" />
        {intervention !== 'silent' && (
          <div>
            <div className="Form-group">
              <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_display_mode')}</label>
              <Select
                options={
                  app.filterRuleManager && typeof app.filterRuleManager.getDisplayModes === 'function'
                    ? Object.entries(app.filterRuleManager.getDisplayModes()).reduce(
                        (acc: Record<string, string>, [key, translationKey]: [string, unknown]) => {
                          acc[key] = String(app.translator.trans(translationKey as string));
                          return acc;
                        },
                        {}
                      )
                    : { none: String(app.translator.trans('huoxin-filter-rule-manager.admin.displays.none')) }
                }
                value={displayMode}
                onchange={(v: string) => this.displayMode(v)}
              />
              <div className="helpText">{app.translator.trans(`huoxin-filter-rule-manager.admin.displays.${displayMode}_help`)}</div>
            </div>

            <div className="Form-group">
              <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_display_template')}</label>
              <Select
                options={
                  app.filterRuleManager && app.filterRuleManager.templates
                    ? Object.keys(app.filterRuleManager.templates).reduce((acc: Record<string, string>, k) => {
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

            {SettingsComponent && (
              <SettingsComponent
                displaySetting={this.displaySetting.bind(this)}
                interventionType={intervention}
                displayMode={displayMode}
                tokens={tokens}
                tokenChipsBlock={this.tokenChipsBlock.bind(this)}
              />
            )}

            <hr className="FilterRuleManager-RulesetEditor-divider" />

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
                oninput={(e: Event) => this.message((e.target as HTMLTextAreaElement).value)}
                placeholder={String(app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_message_placeholder'))}
                rows={2}
                required
              ></textarea>
              <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.message_help')}</div>
              {this.tokenChipsBlock(tokens, 'message')}
            </div>

            <hr className="FilterRuleManager-RulesetEditor-divider" />

            <div className="Form-group">
              <label>{app.translator.trans('huoxin-filter-rule-manager.admin.preview')}</label>
              {this.previewBlock(
                intervention,
                this.message() || String(app.translator.trans('huoxin-filter-rule-manager.admin.preview_placeholder'))
              )}
            </div>
          </div>
        )}
      </div>
    );
  }

  moderationSection(): Mithril.Children {
    return (
      <div className="FilterRuleManager-RulesetEditor-section">
        <div className="FilterRuleManager-RulesetEditor-section-header">
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
          <div className="Alert Alert--warning FilterRuleManager-RulesetEditor-warningAlert">
            <p>
              <i className="fas fa-exclamation-circle"></i>{' '}
              {app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_approval_without_flag_warning')}
            </p>
          </div>
        ) : null}

        <hr className="FilterRuleManager-RulesetEditor-divider" />

        {this.nullableBooleanSelect(
          'huoxin-filter-rule-manager.admin.ruleset_evasion_active',
          'huoxin-filter-rule-manager.admin.ruleset_evasion_active_help',
          this.evasionActive
        )}

        {this.evasionActive() === true ? (
          <div className="Form-group">
            <div className="FilterRuleManager-RulesetEditor-inline-inputs">
              <div className="FilterRuleManager-RulesetEditor-inline-input">
                <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_evasion_timeout')}</label>
                <input
                  className="FormControl"
                  type="number"
                  min="0"
                  step="1"
                  value={this.evasionTimeout() === null ? '' : this.evasionTimeout()!}
                  oninput={(e: Event) => {
                    const val = (e.target as HTMLInputElement).value;
                    this.evasionTimeout(val === '' ? null : Math.max(0, parseInt(val, 10)) || 0);
                  }}
                  placeholder={String(app.translator.trans('huoxin-filter-rule-manager.admin.inherit_global_default'))}
                />
                <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_evasion_timeout_help')}</div>
              </div>
              <div className="FilterRuleManager-RulesetEditor-inline-input">
                <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_evasion_threshold')}</label>
                <input
                  className="FormControl"
                  type="number"
                  min="1"
                  step="1"
                  value={this.evasionThreshold() === null ? '' : this.evasionThreshold()!}
                  oninput={(e: Event) => {
                    const val = (e.target as HTMLInputElement).value;
                    this.evasionThreshold(val === '' ? null : Math.max(1, parseInt(val, 10)) || 1);
                  }}
                  placeholder={String(app.translator.trans('huoxin-filter-rule-manager.admin.inherit_global_default'))}
                />
                <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_evasion_threshold_help')}</div>
              </div>
            </div>
          </div>
        ) : null}

        <hr className="FilterRuleManager-RulesetEditor-divider" />

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
              oninput={(e: Event) => this.flagMessage((e.target as HTMLTextAreaElement).value)}
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
      <div className="FilterRuleManager-RulesetEditor-section">
        <div className="FilterRuleManager-RulesetEditor-section-header">
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
    const out: Record<string, unknown>[] = [];

    const push = (token: Record<string, unknown>, source: string) => {
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

    const activeRules: ASTNode[] = [];
    const traverse = (node: ASTNode | null | undefined) => {
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
        app.filterRuleManager && typeof app.filterRuleManager.getProvider === 'function' ? app.filterRuleManager.getProvider(rule.provider) : null;
      if (fp && typeof fp.getProvidedTokens === 'function') {
        const ftokens = fp.getProvidedTokens(rule.ruleType) || [];
        ftokens.forEach((t: Record<string, unknown>) => push(t, `${rule.provider}/${rule.ruleType}`));
        continue;
      }

      const meta = (this.providers || []).find((p) => p.provider === rule.provider && p.type === rule.ruleType);
      const tokens = meta && Array.isArray(meta.tokens) ? meta.tokens : [];
      tokens.forEach((t: Record<string, unknown>) => push(t, `${rule.provider}/${rule.ruleType}`));
    }

    return out;
  }

  tokenChipsBlock(tokens: Record<string, unknown>[], targetField: string | ((name: string) => void)): Mithril.Children {
    if (!tokens || tokens.length === 0) {
      return (
        <div className="FilterRuleManager-TokenHints FilterRuleManager-TokenHints--empty">
          {app.translator.trans('huoxin-filter-rule-manager.admin.tokens_none')}
        </div>
      );
    }

    return (
      <div className="FilterRuleManager-TokenHints">
        <div className="FilterRuleManager-TokenHints-label">{app.translator.trans('huoxin-filter-rule-manager.admin.tokens_available')}</div>
        <div className="FilterRuleManager-TokenHints-list">
          {tokens.map((t) => (
            <button
              type="button"
              className="FilterRuleManager-TokenHints-chip"
              key={t.name as string}
              title={String(t.description || t.name)}
              onclick={() => this.insertToken(t.name as string, targetField)}
            >
              <code>{`{{${t.name}}}`}</code>
              {t.description && <span className="FilterRuleManager-TokenHints-chip-desc">{t.description}</span>}
            </button>
          ))}
        </div>
      </div>
    );
  }

  insertToken(name: string, targetField: string | ((name: string) => void)) {
    if (typeof targetField === 'function') {
      targetField(name);
      return;
    }

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

  previewBlock(intervention: string, message: string): Mithril.Children {
    const settings = this.displaySettings() || {};
    const templateName = settings.template || 'builtin';

    let TemplateComponent = filterEngine.getTemplate(templateName) || filterEngine.getTemplate('builtin');

    const sampleTokens: Record<string, string> = {};
    const available = this.availableTokens() || [];
    available.forEach((t) => {
      sampleTokens[t.name as string] = `[${t.name}]`;
    });

    if (available.length === 0) {
      sampleTokens['matched_word'] = '[matched_word]';
      sampleTokens['matched_pattern'] = '[matched_pattern]';
      sampleTokens['matched_string'] = '[matched_string]';
    }

    const renderedMessage = filterEngine.interpolate(message, sampleTokens);

    const previewSettings = { ...settings };
    if (typeof previewSettings.title === 'string') {
      previewSettings.title = filterEngine.interpolate(previewSettings.title, sampleTokens);
    }

    const dummyAlert = {
      type: intervention,
      message: renderedMessage,
      displaySettings: previewSettings,
      key: 'preview-alert',
    };

    if (!TemplateComponent) {
      return <div>Loading template...</div>;
    }

    const TemplateComp = TemplateComponent as any;
    return (
      <div className="FilterRuleManager-preview">
        <TemplateComp alert={dummyAlert} variant={this.displayMode()} />
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
      <div className="Form-group FilterRuleManager-RulesetEditor-actions">
        <Button className="Button Button--primary" loading={this.loading} disabled={!this.canSave()} onclick={() => this.save()}>
          {app.translator.trans('huoxin-filter-rule-manager.admin.save')}
        </Button>
        <Button className="Button" onclick={() => this.hide()}>
          {app.translator.trans('huoxin-filter-rule-manager.admin.cancel')}
        </Button>
      </div>
    );
  }

  interventionIcon(intervention: string) {
    if (intervention === 'block') return 'fas fa-ban';
    if (intervention === 'warning') return 'fas fa-exclamation-triangle';
    if (intervention === 'silent') return 'fas fa-user-secret';
    return 'fas fa-info-circle';
  }

  isDirty(): boolean {
    if (this.loading) return false;

    const r = this.ruleset;
    if (!r) {
      return !!(this.name() || this.expression() || this.message() || this.flagMessage());
    }

    return (
      this.name() !== r.name() ||
      this.expression() !== r.expression() ||
      this.interventionType() !== r.interventionType() ||
      this.displayMode() !== r.displayMode() ||
      this.message() !== r.message() ||
      this.flagMessage() !== r.flagMessage() ||
      this.evaluateAllRules() !== r.evaluateAllRules() ||
      this.evaluateTitle() !== r.evaluateTitle() ||
      this.evasionActive() !== r.evasionActive() ||
      this.evasionTimeout() !== r.evasionTimeout() ||
      this.evasionThreshold() !== r.evasionThreshold() ||
      this.blockCascade() !== r.blockCascade() ||
      this.isActive() !== r.isActive() ||
      this.autoFlag() !== r.autoFlag() ||
      this.requireApproval() !== r.requireApproval() ||
      this.scopeType() !== r.scopeType() ||
      JSON.stringify(this.scopeTagIds() || []) !== JSON.stringify(r.scopeTagIds() || []) ||
      JSON.stringify(this.bypassGroupIds() || []) !== JSON.stringify(r.bypassGroupIds() || []) ||
      JSON.stringify(this.displaySettings() || {}) !== JSON.stringify(r.displaySettings() || {})
    );
  }

  hide() {
    if (this.showingDiscardConfirmation) {
      this.showingDiscardConfirmation = false;
      m.redraw();
      return;
    }

    if (this.isDirty()) {
      this.showingDiscardConfirmation = true;
      m.redraw();
      return;
    }

    super.hide();
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

  onsubmit(e: Event) {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    this.save();
  }

  async save() {
    if (!this.canSave()) return;

    this.loading = true;
    m.redraw();

    const data = {
      name: this.name(),
      expression: this.expression(),
      interventionType: this.interventionType(),
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
      bypassGroupIds: this.bypassGroupIds(),
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
      m.redraw();
    }
  }
}
