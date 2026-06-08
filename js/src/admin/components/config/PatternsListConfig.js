import Component from 'flarum/common/Component';
import Stream from 'flarum/common/utils/Stream';
import Switch from 'flarum/common/components/Switch';

/**
 * Config UI for the builtin `regex` rule type.
 *
 * Rule config shape: { patterns: string[] }
 * Backward compatible with the legacy single-value shape { pattern: string }.
 *
 * Patterns may use bare regex syntax (`foo.*bar`) or PCRE-delimited form
 * (`/foo.*bar/i`) — the evaluator detects which.
 */
export default class PatternsListConfig extends Component {
  oninit(vnode) {
    super.oninit(vnode);

    const cfg = this.attrs.config || {};
    const initial = Array.isArray(cfg.patterns)
      ? cfg.patterns
      : (typeof cfg.pattern === 'string' && cfg.pattern !== '' ? [cfg.pattern] : []);

    this.text = Stream(initial.join('\n'));
    this.scanAll = Stream(cfg.scan_all || false);
  }

  view() {
    return (
      <div className="FilterRuleManager-ConfigForm">
        <label>{app.translator.trans('huoxin-filter-rule-manager.admin.config_patterns_label')}</label>
        <textarea
          className="FormControl FilterRuleManager-LinesInput"
          rows="5"
          value={this.text()}
          oninput={(e) => this.handleInput(e.target.value)}
          placeholder={app.translator.trans('huoxin-filter-rule-manager.admin.config_patterns_placeholder')}
        ></textarea>
        <div className="helpText">
          {app.translator.trans('huoxin-filter-rule-manager.admin.config_patterns_help')}
        </div>
        <div className="Form-group">
          <Switch
            state={this.scanAll()}
            onchange={(val) => {
              this.scanAll(val);
              this.attrs.onchange({ ...(this.attrs.config || {}), scan_all: val });
            }}
          >
            {app.translator.trans('huoxin-filter-rule-manager.admin.rule_scan_all')}
          </Switch>
          <div className="helpText">
            {app.translator.trans('huoxin-filter-rule-manager.admin.rule_scan_all_help')}
          </div>
        </div>
      </div>
    );
  }

  handleInput(raw) {
    this.text(raw);
    const patterns = raw
      .split(/\r?\n/)
      .map((s) => s.trim())
      .filter((s) => s.length > 0);
    this.attrs.onchange({ ...(this.attrs.config || {}), patterns });
  }
}
