import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';

export default class FilterRuleWarningModal extends Modal {
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
            <li key={i}>{m.trust(alert.message)}</li>
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
