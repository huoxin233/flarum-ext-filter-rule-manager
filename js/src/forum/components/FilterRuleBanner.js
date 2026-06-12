import Component from 'flarum/common/Component';
import filterEngine from '../../common/FilterEngine';
import icon from 'flarum/common/helpers/icon';

/**
 * Renders a single class of inline alert. Accepts `attrs.variant`:
 *
 *   - `banner`         → Inline strip injected at .App-composer > .container
 *                        level, ABOVE the composer. Sized to align with the
 *                        editor (see .FilterRuleManager--banner in LESS).
 *   - `header_banner`  → Inline strip rendered inside ComposerBody.headerItems,
 *                        immediately above the textarea.
 *   - `sidebar`        → Vertical floating card pinned to the right.
 *
 * `toast` and `modal` are handled by RuleDispatcher and never reach this
 * component.
 */
export default class FilterRuleBanner extends Component {
  oninit(vnode) {
    super.oninit(vnode);
    this.isSidebarClosed = false;
  }

  view() {
    const variant = this.attrs.variant;
    const items = this._matchingItems(variant);
    if (items.length === 0) return null;

    if (variant === 'sidebar') {
      if (this.isSidebarClosed) return null;
      return (
        <aside className="FilterRuleManager FilterRuleManager--sidebar" aria-label="Composer hints">
          <div className="FilterRuleManager-sidebarTitle">
            {icon('fas fa-shield-alt')}
            <span style="flex: 1;">Composer hints</span>
            <button 
              className="Button Button--icon Button--link" 
              onclick={() => this.isSidebarClosed = true}
              style="padding: 0; min-width: 0; min-height: 0; line-height: 1; color: inherit;"
            >
              {icon('fas fa-times')}
            </button>
          </div>
          {items.map((alert, i) => this._renderItem(alert, i, 'sidebar'))}
        </aside>
      );
    }

    return (
      <div className={`FilterRuleManager FilterRuleManager--${variant}`}>
        <div className="FilterRuleManager-banners">
          {items.map((alert, i) => this._renderItem(alert, i, variant))}
        </div>
      </div>
    );
  }

  _matchingItems(variant) {
    const isMobile = window.innerWidth <= 768;
    if (isMobile && variant !== 'banner') return [];
    
    const active = filterEngine.activeAlerts
      .filter((a) => isMobile ? true : a.ruleset.displayMode === variant)
      .map((a) => ({
        type: a.ruleset.effectType,
        message: a.message,
        key: `rs-${a.ruleset.id}`,
      }));
    const blocks = filterEngine.blockResults
      .filter((a) => isMobile ? true : a.displayMode === variant)
      .map((a, i) => ({
        type: a.effectType,
        message: a.message,
        key: `block-${i}-${a.message}`,
      }));
    return [...active, ...blocks];
  }

  _renderItem(alert, i, variant) {
    const iconName = alert.type === 'block'   ? 'fas fa-times-circle'
                   : alert.type === 'warning' ? 'fas fa-exclamation-triangle'
                   :                            'fas fa-info-circle';

    return (
      <div
        key={alert.key || i}
        className={`FilterRuleManager-item FilterRuleManager-item--${variant} FilterRuleManager-item--${alert.type}`}
      >
        <span className="FilterRuleManager-item-icon">{icon(iconName)}</span>
        <span className="FilterRuleManager-item-message">{m.trust(alert.message)}</span>
      </div>
    );
  }
}
