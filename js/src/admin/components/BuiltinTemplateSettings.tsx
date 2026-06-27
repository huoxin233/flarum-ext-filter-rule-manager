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
import icon from 'flarum/common/helpers/icon';
import Select from 'flarum/common/components/Select';
import type Mithril from 'mithril';

const COMMON_ICONS = [
  'fas fa-info-circle',
  'fas fa-exclamation-circle',
  'fas fa-exclamation-triangle',
  'fas fa-ban',
  'fas fa-times-circle',
  'fas fa-check-circle',
  'fas fa-shield-alt',
  'fas fa-lock',
  'fas fa-eye-slash',
  'fas fa-bell',
  'fas fa-bullhorn',
  'fas fa-comment-slash',
  'fas fa-user-slash',
  'fas fa-robot',
  'fas fa-gavel',
  'fas fa-hand-paper',
  'fas fa-flag',
  'fas fa-fire',
  'fas fa-bolt',
  'fas fa-eye',
  'fas fa-volume-mute',
  'fas fa-radiation',
];

interface ColorPickerInputAttrs extends ComponentAttrs {
  label: string;
  value: string;
  defaultColor: string;
  onchange: (val: string) => void;
}

class ColorPickerInput extends Component<ColorPickerInputAttrs> {
  view(): Mithril.Children {
    const { label, value, defaultColor, onchange } = this.attrs;
    return (
      <div className="Form-group">
        <label>{label}</label>
        <div className="FilterRuleManager-ColorPickerInput">
          <input
            type="color"
            className={value === 'transparent' ? 'is-transparent' : ''}
            value={value && value !== 'transparent' ? value : defaultColor}
            oninput={(e: Event) => onchange((e.target as HTMLInputElement).value)}
          />
          <div className="FilterRuleManager-ColorPickerInput-input">
            <input
              type="text"
              className="FormControl"
              placeholder={defaultColor}
              value={value || ''}
              oninput={(e: Event) => onchange((e.target as HTMLInputElement).value)}
            />
            <div className="FilterRuleManager-ColorPicker-actions">
              <div className="FilterRuleManager-ColorPicker-action" onclick={() => onchange('transparent')} title="Set transparent">
                {icon('fas fa-eye-slash')}
              </div>
              <div className="FilterRuleManager-ColorPicker-action" onclick={() => onchange('')} title="Clear to default">
                {icon('fas fa-eraser')}
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }
}

export interface BuiltinTemplateSettingsAttrs extends ComponentAttrs {
  displaySetting: (key: string, value?: string) => string;
  interventionType: string;
  displayMode: string;
}

export default class BuiltinTemplateSettings extends Component<BuiltinTemplateSettingsAttrs> {
  showIconPicker: boolean = false;
  titleInput: HTMLInputElement | null = null;

  oninit(vnode: Mithril.Vnode<BuiltinTemplateSettingsAttrs, this>) {
    super.oninit(vnode);
    this.showIconPicker = false;
  }

  getDefaultStylesForIntervention(intervention: string): { icon: string; iconColor: string; textColor: string; backgroundColor: string } {
    if (intervention === 'info') return { icon: 'fas fa-info-circle', iconColor: '#2b7c93', textColor: '#2b7c93', backgroundColor: '#e8f4f8' };
    if (intervention === 'warning')
      return { icon: 'fas fa-exclamation-triangle', iconColor: '#8a6d3b', textColor: '#8a6d3b', backgroundColor: '#fff4e5' };
    if (intervention === 'block') return { icon: 'fas fa-times-circle', iconColor: '#a94442', textColor: '#a94442', backgroundColor: '#fde8e8' };
    return { icon: 'fas fa-info-circle', iconColor: '#000000', textColor: '#000000', backgroundColor: '#ffffff' };
  }

  view(): Mithril.Children {
    const { displaySetting, interventionType, displayMode, tokens, tokenChipsBlock } = this.attrs as any;
    const isToast = displayMode === 'toast';
    const defaultStyles = this.getDefaultStylesForIntervention(interventionType);

    return (
      <div className="FilterRuleManager-RulesetEditor-customStyles">
        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_custom_title') || 'Alert Title (Optional)'}</label>
          <input
            className="FormControl"
            value={displaySetting('title') || ''}
            oncreate={(vnode) => {
              this.titleInput = vnode.dom as HTMLInputElement;
            }}
            onupdate={(vnode) => {
              this.titleInput = vnode.dom as HTMLInputElement;
            }}
            onremove={() => {
              this.titleInput = null;
            }}
            oninput={(e: Event) => displaySetting('title', (e.target as HTMLInputElement).value)}
            placeholder={String(app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_custom_title_placeholder') || 'e.g., Warning!')}
          />
          <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_custom_title_help')}</div>
          {tokenChipsBlock &&
            tokenChipsBlock(tokens, (name: string) => {
              const insertion = `{{${name}}}`;
              const current = displaySetting('title') || '';

              if (!this.titleInput) {
                displaySetting('title', current + insertion);
                return;
              }

              const start = this.titleInput.selectionStart != null ? this.titleInput.selectionStart : current.length;
              const end = this.titleInput.selectionEnd != null ? this.titleInput.selectionEnd : current.length;
              const next = current.substring(0, start) + insertion + current.substring(end);

              displaySetting('title', next);
              this.titleInput.value = next;
              this.titleInput.focus();

              const cursor = start + insertion.length;
              try {
                this.titleInput.setSelectionRange(cursor, cursor);
              } catch (e) {}
            })}
        </div>
        <div className="FilterRuleManager-RulesetEditor-customStyles-row">
          <div className="Form-group">
            <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_custom_icon')}</label>
            <div className="FilterRuleManager-IconPickerInput">
              <div className="FilterRuleManager-IconPicker-preview" onclick={() => (this.showIconPicker = !this.showIconPicker)}>
                {icon(displaySetting('icon') || defaultStyles.icon)}
              </div>
              <div className="FilterRuleManager-IconPickerInput-input">
                <input
                  className="FormControl"
                  placeholder={defaultStyles.icon}
                  value={displaySetting('icon')}
                  oninput={(e: Event) => displaySetting('icon', (e.target as HTMLInputElement).value)}
                />
                <div className="FilterRuleManager-IconPicker-actions">
                  <div className="FilterRuleManager-IconPicker-action" onclick={() => displaySetting('icon', '')} title="Clear to default">
                    {icon('fas fa-eraser')}
                  </div>
                </div>
              </div>

              {this.showIconPicker && (
                <div className="FilterRuleManager-IconPicker-dropdown">
                  <div className="FilterRuleManager-IconPicker-icons">
                    <button
                      type="button"
                      className="Button FilterRuleManager-IconPicker-btn FilterRuleManager-IconPicker-btn--none"
                      onclick={() => {
                        displaySetting('icon', 'none');
                        this.showIconPicker = false;
                      }}
                      title="No Icon"
                    >
                      {icon('fas fa-ban')} {String(app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_no_icon')) || 'None'}
                    </button>
                    {COMMON_ICONS.map((iconClass) => (
                      <button
                        type="button"
                        className="Button Button--icon FilterRuleManager-IconPicker-btn"
                        onclick={() => {
                          displaySetting('icon', iconClass);
                          this.showIconPicker = false;
                        }}
                        title={iconClass}
                      >
                        {icon(iconClass)}
                      </button>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </div>
          {!isToast ? (
            [
              <ColorPickerInput
                label={String(app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_custom_icon_color'))}
                value={displaySetting('iconColor')}
                defaultColor={defaultStyles.iconColor}
                onchange={(val: string) => displaySetting('iconColor', val)}
              />,
              <ColorPickerInput
                label={String(app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_custom_text_color'))}
                value={displaySetting('textColor')}
                defaultColor={defaultStyles.textColor}
                onchange={(val: string) => displaySetting('textColor', val)}
              />,
              <ColorPickerInput
                label={String(app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_custom_bg_color'))}
                value={displaySetting('backgroundColor')}
                defaultColor={defaultStyles.backgroundColor}
                onchange={(val: string) => displaySetting('backgroundColor', val)}
              />,
            ]
          ) : (
            <div className="Form-group">
              <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_toast_theme')}</label>
              <Select
                options={{
                  '': String(app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_toast_theme_auto')),
                  success: String(app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_toast_theme_success')),
                  error: String(app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_toast_theme_error')),
                  warning: String(app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_toast_theme_warning')),
                }}
                value={displaySetting('toastTheme') || ''}
                onchange={(val: string) => displaySetting('toastTheme', val)}
              />
            </div>
          )}
        </div>
      </div>
    );
  }
}
