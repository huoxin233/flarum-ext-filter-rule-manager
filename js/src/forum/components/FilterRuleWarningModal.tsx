/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import app from 'flarum/forum/app';
import Modal, { ModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';

export interface FilterRuleWarningModalAttrs extends ModalAttrs {
  alerts: any[];
  onconfirm: () => void;
  oncancel: () => void;
}

export default class FilterRuleWarningModal extends Modal<FilterRuleWarningModalAttrs> {
  className() {
    return 'FilterRuleWarningModal Modal--small';
  }

  title() {
    return app.translator.trans('huoxin-filter-rule-manager.forum.warning_modal_title');
  }

  content() {
    const alerts = this.attrs.alerts || [];

    return (
      <div className="Modal-body">
        <p>{app.translator.trans('huoxin-filter-rule-manager.forum.warning_modal_text')}</p>
        <ul className="FilterRuleWarningModal-list">
          {alerts.map((alert, i) => (
            <li key={i}>{(window as any).m.trust(alert.message)}</li>
          ))}
        </ul>
        <div className="Form-group">
          <Button className="Button Button--primary" onclick={() => this.attrs.onconfirm()}>
            {app.translator.trans('huoxin-filter-rule-manager.forum.warning_modal_continue')}
          </Button>
          <Button className="Button" onclick={() => this.attrs.oncancel()}>
            {app.translator.trans('huoxin-filter-rule-manager.forum.warning_modal_cancel')}
          </Button>
        </div>
      </div>
    );
  }
}

