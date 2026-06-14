import app from 'flarum/common/app';
import Component from 'flarum/common/Component';
import icon from 'flarum/common/helpers/icon';

export default class BuiltinTemplate extends Component {
  view() {
    const { alert, variant } = this.attrs;
    const settings = alert.displaySettings || {};

    let iconName = settings.icon;
    if (!iconName) {
      iconName = alert.type === 'block'   ? 'fas fa-times-circle'
               : alert.type === 'warning' ? 'fas fa-exclamation-triangle'
               :                            'fas fa-info-circle';
    }

    const style = {};
    if (settings.textColor) style.color = settings.textColor;
    if (settings.backgroundColor) style.backgroundColor = settings.backgroundColor;

    const iconStyle = {};
    if (settings.iconColor) iconStyle.color = settings.iconColor;

    return (
      <div
        className={`FilterRuleManager-item FilterRuleManager-item--${variant} FilterRuleManager-item--${alert.type}`}
        style={style}
      >
        {iconName !== 'none' && (
          <span className="FilterRuleManager-item-icon" style={iconStyle}>{icon(iconName)}</span>
        )}
        <div className="FilterRuleManager-item-content">
          {settings.title && (
            <strong className="FilterRuleManager-item-title" style={{ display: 'block', marginBottom: '2px' }}>
              {app.translator.trans(settings.title)}
            </strong>
          )}
          <span className="FilterRuleManager-item-message">{m.trust(alert.message)}</span>
        </div>
      </div>
    );
  }
}
