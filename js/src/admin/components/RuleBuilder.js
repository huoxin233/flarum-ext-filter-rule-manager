import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Select from 'flarum/common/components/Select';
import Switch from 'flarum/common/components/Switch';
import icon from 'flarum/common/helpers/icon';

/**
 * Stateless rule list editor. Owns no state; `attrs.rules` is the source of
 * truth and every change produces a brand-new `rules` array passed to
 * `attrs.onchange`.
 *
 * Each rule's config block is rendered by the provider when possible:
 *   provider.getConfigComponent(type) => MithrilComponentClass
 *
 * The component receives { config, onchange, type } and is responsible for
 * its own internal state. When a provider doesn't expose a config component
 * (or returns null), the rule falls back to a generic JSON textarea so any
 * provider remains usable without a custom UI.
 */
export default class RuleBuilder extends Component {
  view() {
    const rules = this.attrs.rules || [];
    const providers = this.attrs.providers || [];

    const providerOptions = {};
    providers.forEach((p) => {
      if (p.provider) {
        const transKey = `huoxin-filter-rule-manager.admin.providers.${p.provider}`;
        const translated = app.translator.trans(transKey);
        // If it isn't translated, it returns an array containing the key in Flarum < 1.2, or the key itself in >= 1.2
        const isTranslated = Array.isArray(translated) ? translated[0] !== transKey : translated !== transKey;
        providerOptions[p.provider] = isTranslated ? translated : p.provider;
      }
    });

    return (
      <div className="RuleBuilder">
        {rules.map((rule, index) => {
          const availableTypes = providers.filter((p) => p.provider === rule.provider);
          const typeOptions = availableTypes.reduce((acc, p) => {
            acc[p.type] = p.label || p.type;
            return acc;
          }, {});

          return (
            <div className="RuleBuilder-rule" key={index}>
              <div className="RuleBuilder-rule-header">
                <Select
                  options={providerOptions}
                  value={rule.provider}
                  onchange={(val) => {
                    const firstType = providers.find((p) => p.provider === val);
                    // Resetting type AND config when the provider changes
                    // avoids carrying old fields (e.g. `words`) into a new
                    // type that expects a different schema (e.g. `patterns`).
                    this.updateRule(index, {
                      provider: val,
                      type: firstType ? firstType.type : '',
                      config: {},
                    });
                  }}
                />

                <Select
                  options={typeOptions}
                  value={rule.type}
                  onchange={(val) => this.updateRule(index, { type: val, config: {} })}
                  disabled={!rule.provider}
                />

                <Switch
                  state={rule.negate}
                  onchange={(val) => this.updateRule(index, { negate: val })}
                >
                  Negate (NOT)
                </Switch>

                <Button className="Button" onclick={() => this.removeRule(index)}>
                  {icon('fas fa-times')}
                </Button>
              </div>
              <div className="RuleBuilder-rule-config">
                {this.renderConfig(rule, index)}
              </div>
            </div>
          );
        })}

        <Button className="Button" onclick={() => this.addRule()}>
          {icon('fas fa-plus')} Add Rule
        </Button>
      </div>
    );
  }

  renderConfig(rule, index) {
    const provider = (app.filterRuleManager && typeof app.filterRuleManager.getProvider === 'function')
      ? app.filterRuleManager.getProvider(rule.provider)
      : null;

    const ConfigComponent = (provider && typeof provider.getConfigComponent === 'function')
      ? provider.getConfigComponent(rule.type)
      : null;

    if (ConfigComponent) {
      return (
        <ConfigComponent
          // Re-mount the config component whenever the rule's provider/type
          // changes so it re-initialises from the (now-reset) config.
          key={`${rule.provider}:${rule.type}:${index}`}
          config={rule.config || {}}
          type={rule.type}
          onchange={(newConfig) => this.updateRule(index, { config: newConfig })}
        />
      );
    }

    // Fallback: raw JSON editor for providers without a custom form.
    return (
      <div className="FilterRuleManager-ConfigForm">
        <label>Config (JSON):</label>
        <textarea
          className="FormControl"
          value={JSON.stringify(rule.config || {}, null, 2)}
          onchange={(e) => {
            let parsed;
            try {
              parsed = JSON.parse(e.target.value);
            } catch (err) {
              return;
            }
            this.updateRule(index, { config: parsed });
          }}
          placeholder='{"key": "value"}'
        ></textarea>
      </div>
    );
  }

  updateRule(index, patch) {
    const rules = (this.attrs.rules || []).map((rule, i) =>
      i === index ? { ...rule, ...patch } : rule
    );
    this.emit(rules);
  }

  addRule() {
    const providers = this.attrs.providers || [];
    const first = providers[0];
    const next = [
      ...(this.attrs.rules || []),
      {
        provider: first ? first.provider : '',
        type: first ? first.type : '',
        config: {},
        negate: false,
      },
    ];
    this.emit(next);
  }

  removeRule(index) {
    const next = (this.attrs.rules || []).filter((_, i) => i !== index);
    this.emit(next);
  }

  emit(rules) {
    if (typeof this.attrs.onchange === 'function') {
      this.attrs.onchange(rules);
    }
  }
}
