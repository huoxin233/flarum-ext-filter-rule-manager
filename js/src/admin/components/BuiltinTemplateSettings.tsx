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
  'fas fa-info-circle', 'fas fa-exclamation-circle', 'fas fa-exclamation-triangle',
  'fas fa-ban', 'fas fa-times-circle', 'fas fa-check-circle',
  'fas fa-shield-alt', 'fas fa-lock', 'fas fa-eye-slash', 'fas fa-bell',
  'fas fa-bullhorn', 'fas fa-comment-slash', 'fas fa-user-slash', 'fas fa-robot',
  'fas fa-gavel', 'fas fa-hand-paper', 'fas fa-flag', 'fas fa-fire', 'fas fa-bolt',
  'fas fa-eye', 'fas fa-volume-mute', 'fas fa-radiation'
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
        <div className="ColorPickerInput">
          <input 
            type="color"
            className={value === 'transparent' ? 'is-transparent' : ''}
            value={value && value !== 'transparent' ? value : defaultColor} 
            oninput={(e: any) => onchange(e.target.value)} 
          />
          <div className="ColorPickerInput-input">
            <input 
              type="text"
              className="FormControl" 
              placeholder={defaultColor} 
              value={value || ''} 
              oninput={(e: any) => onchange(e.target.value)} 
            />
            <div className="ColorPicker-actions">
              <div 
                className="ColorPicker-action"
                onclick={() => onchange('transparent')}
                title="Set transparent"
              >
                {icon('fas fa-eye-slash')}
              </div>
              <div 
                className="ColorPicker-action"
                onclick={() => onchange('')}
                title="Clear to default"
              >
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
  displaySetting: (key: string, value?: any) => any;
  effectType: string;
  displayMode: string;
}

export default class BuiltinTemplateSettings extends Component<BuiltinTemplateSettingsAttrs> {
  showIconPicker: boolean = false;

  oninit(vnode: Mithril.Vnode<BuiltinTemplateSettingsAttrs, this>) {
    super.oninit(vnode);
    this.showIconPicker = false;
  }

  getDefaultStylesForEffect(effect: string): { icon: string; iconColor: string; textColor: string; backgroundColor: string } {
    if (effect === 'info') return { icon: 'fas fa-info-circle', iconColor: '#2b7c93', textColor: '#2b7c93', backgroundColor: '#e8f4f8' };
    if (effect === 'warning') return { icon: 'fas fa-exclamation-triangle', iconColor: '#8a6d3b', textColor: '#8a6d3b', backgroundColor: '#fff4e5' };
    if (effect === 'block') return { icon: 'fas fa-times-circle', iconColor: '#a94442', textColor: '#a94442', backgroundColor: '#fde8e8' };
    return { icon: 'fas fa-info-circle', iconColor: '#000000', textColor: '#000000', backgroundColor: '#ffffff' };
  }

  view(): Mithril.Children {
    const { displaySetting, effectType, displayMode } = this.attrs;
    const isToast = displayMode === 'toast';
    const defaultStyles = this.getDefaultStylesForEffect(effectType);

    return (
      <div className="RulesetEditor-customStyles">
        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_custom_icon')}</label>
          <div className="IconPickerInput">
            <div 
              className="IconPicker-preview" 
              onclick={() => this.showIconPicker = !this.showIconPicker}
            >
              {icon(displaySetting('icon') || defaultStyles.icon)}
            </div>
            <div className="IconPickerInput-input">
              <input 
                className="FormControl" 
                placeholder={defaultStyles.icon} 
                value={displaySetting('icon')} 
                oninput={(e: any) => displaySetting('icon', e.target.value)} 
              />
              <div className="IconPicker-actions">
                <div 
                  className="IconPicker-action"
                  onclick={() => displaySetting('icon', '')}
                  title="Clear to default"
                >
                  {icon('fas fa-eraser')}
                </div>
              </div>
            </div>
            
            {this.showIconPicker && (
                <div className="IconPicker-dropdown">
                  <div className="IconPicker-icons">
                    <button 
                      type="button"
                      className="Button IconPicker-btn IconPicker-btn--none" 
                      onclick={() => {
                        displaySetting('icon', 'none');
                        this.showIconPicker = false;
                      }}
                      title="No Icon"
                    >
                      {icon('fas fa-ban')} {String(app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_no_icon')) || 'None'}
                    </button>
                    {COMMON_ICONS.map(iconClass => (
                      <button 
                        type="button"
                        className="Button Button--icon IconPicker-btn" 
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
        {!isToast ? [
            <ColorPickerInput 
              label={String(app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_custom_icon_color'))}
              value={displaySetting('iconColor')}
              defaultColor={defaultStyles.iconColor}
              onchange={(val) => displaySetting('iconColor', val)}
            />,
            <ColorPickerInput 
              label={String(app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_custom_text_color'))}
              value={displaySetting('textColor')}
              defaultColor={defaultStyles.textColor}
              onchange={(val) => displaySetting('textColor', val)}
            />,
            <ColorPickerInput 
              label={String(app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_custom_bg_color'))}
              value={displaySetting('backgroundColor')}
              defaultColor={defaultStyles.backgroundColor}
              onchange={(val) => displaySetting('backgroundColor', val)}
            />
        ] : (
          <div className="Form-group">
            <label>{app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_toast_theme')}</label>
            <Select
              options={{
                '': String(app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_toast_theme_auto')),
                'success': String(app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_toast_theme_success')),
                'error': String(app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_toast_theme_error')),
                'warning': String(app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_toast_theme_warning')),
              }}
              value={displaySetting('toastTheme') || ''}
              onchange={(val: string) => displaySetting('toastTheme', val)}
            />
          </div>
        )}
      </div>
    );
  }
}
