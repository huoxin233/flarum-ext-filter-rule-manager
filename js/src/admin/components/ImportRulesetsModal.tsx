/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import app from 'flarum/admin/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';

interface ImportRulesetsModalAttrs extends IInternalModalAttrs {
  onsuccess?: () => void;
}

export default class ImportRulesetsModal extends Modal<ImportRulesetsModalAttrs> {
  jsonPayload: string = '';
  overrideMode: boolean = false;
  overrideConfirmed: boolean = false;
  preservePriority: boolean = false;
  loading: boolean = false;

  className() {
    return 'FilterRuleManager-ImportRulesetsModal Modal--medium';
  }

  title() {
    return app.translator.trans('huoxin-filter-rule-manager.admin.import_rulesets');
  }

  content() {
    return (
      <div className="Modal-body">
        <div className="Form">
          <div className="Form-group">
            <div className="FilterRuleManager-ImportRulesetsModal-header">
              <label>{app.translator.trans('huoxin-filter-rule-manager.admin.import_json_label')}</label>
              <div>
                <input type="file" id="import-json-file" accept=".json" style={{ display: 'none' }} onchange={this.handleFileUpload.bind(this)} />
                <Button className="Button" icon="fas fa-upload" onclick={() => document.getElementById('import-json-file')?.click()}>
                  {app.translator.trans('huoxin-filter-rule-manager.admin.upload_json')}
                </Button>
              </div>
            </div>
            <textarea
              className="FormControl"
              rows={10}
              value={this.jsonPayload}
              oninput={(e: InputEvent) => (this.jsonPayload = (e.target as HTMLTextAreaElement).value)}
              placeholder='[{"name": "My Ruleset", ...}]'
            />
          </div>

          <div className="Form-group">
            <Switch state={this.preservePriority} onchange={(val: boolean) => (this.preservePriority = val)}>
              {app.translator.trans('huoxin-filter-rule-manager.admin.import_preserve_priority')}
            </Switch>
            <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.import_preserve_priority_help')}</div>
          </div>

          <div className="Form-group">
            <Switch state={this.overrideMode} onchange={this.toggleOverride.bind(this)}>
              {app.translator.trans('huoxin-filter-rule-manager.admin.import_override_mode')}
            </Switch>
          </div>

          {this.overrideMode && (
            <div className="Alert Alert--error Form-group">
              <strong style={{ display: 'block', marginBottom: '8px' }}>
                {app.translator.trans('huoxin-filter-rule-manager.admin.import_override_warning')}
              </strong>
              <label className="checkbox">
                <input
                  type="checkbox"
                  checked={this.overrideConfirmed}
                  onchange={(e: Event) => (this.overrideConfirmed = (e.target as HTMLInputElement).checked)}
                />
                {app.translator.trans('huoxin-filter-rule-manager.admin.import_override_confirm')}
              </label>
            </div>
          )}
          <div className="Form-group">
            <Button
              type="submit"
              className="Button Button--primary Button--block"
              loading={this.loading}
              disabled={!this.jsonPayload.trim() || (this.overrideMode && !this.overrideConfirmed)}
            >
              {app.translator.trans('huoxin-filter-rule-manager.admin.import_submit')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  toggleOverride(val: boolean) {
    this.overrideMode = val;
    this.overrideConfirmed = false;
  }

  handleFileUpload(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (evt) => {
      this.jsonPayload = evt.target?.result as string;
      m.redraw();
    };
    reader.readAsText(file);

    // Reset input so the same file can be selected again if needed
    (e.target as HTMLInputElement).value = '';
  }

  onsubmit(e: Event) {
    e.preventDefault();

    let rulesets;
    try {
      rulesets = JSON.parse(this.jsonPayload);
      if (!Array.isArray(rulesets)) {
        throw new Error('Payload must be an array of objects.');
      }
    } catch (err: any) {
      app.alerts.show(
        { type: 'error' },
        String(app.translator.trans('huoxin-filter-rule-manager.admin.import_invalid_json', { error: err.message }))
      );
      return;
    }

    this.loading = true;

    app
      .request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/filter-rule/import-rulesets',
        body: {
          rulesets,
          mode: this.overrideMode ? 'override' : 'append',
          preserve_priority: this.preservePriority,
        },
      })
      .then(() => {
        app.alerts.show({ type: 'success' }, app.translator.trans('huoxin-filter-rule-manager.admin.import_success'));
        this.hide();
        if (this.attrs.onsuccess) this.attrs.onsuccess();
      })
      .catch((err) => {
        this.loading = false;
        m.redraw();
        throw err;
      });
  }
}
