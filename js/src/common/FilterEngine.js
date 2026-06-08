/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * FilterEngine — shared between forum and admin bundles.
 *
 * The forum bundle uses the full surface (polling, scope evaluation, etc).
 * The admin bundle only uses provider registration (`registerProvider`) and
 * `getRegisteredFrontendTypes()` so that the rule builder can list types
 * contributed by frontend-only provider extensions.
 *
 * `app` and `m` are accessed via the global scope rather than imported,
 * because `flarum/forum/app` is not resolvable from the admin bundle.
 */

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

class FilterEngine {
  constructor() {
    this.rulesets = [];
    this.providers = {};
    this.activeAlerts = [];
    this.blockResults = [];
    this.intervalId = null;
    this.hasAlerts = false;
    this._lastContent = null;
    this._subscribers = [];
  }

  /**
   * Subscribe to alert-state changes (active/block result mutations).
   * Returns an unsubscribe function. Used by RuleDispatcher to drive
   * toast and modal display modes from a single source of truth.
   */
  subscribe(callback) {
    if (typeof callback !== 'function') return () => {};
    this._subscribers.push(callback);
    return () => {
      this._subscribers = this._subscribers.filter((cb) => cb !== callback);
    };
  }

  _notify() {
    for (const cb of this._subscribers) {
      try {
        cb({ activeAlerts: this.activeAlerts, blockResults: this.blockResults });
      } catch (e) {
        console.error('[filter-rule-manager] subscriber threw', e);
      }
    }
  }

  /**
   * Register a rule provider. Methods consulted:
   *
   *   Forum bundle:
   *     - getSupportedTypes(): string[]
   *     - evaluate(type, content, config): tokens | null
   *
   *   Admin bundle:
   *     - getSupportedTypes(): string[]
   *     - getTypeLabels(): { [type]: string }            (optional)
   *     - getConfigComponent(type): MithrilComponentClass | null   (optional)
   *
   * If a provider doesn't supply `getConfigComponent` (or it returns null),
   * RuleBuilder renders the generic JSON textarea for that rule.
   *
   * The same provider name may be registered in both bundles — each bundle
   * has its own engine instance, so the forum-side object can implement
   * `evaluate` while the admin-side object implements the UI hooks.
   */
  registerProvider(name, provider) {
    this.providers[name] = provider;
  }

  /**
   * Look up a registered provider by name. Returns null if unknown.
   * Used by the admin RuleBuilder to find a provider's `getConfigComponent`.
   */
  getProvider(name) {
    return this.providers[name] || null;
  }

  getRegisteredFrontendTypes() {
    const types = [];
    for (const [name, provider] of Object.entries(this.providers)) {
      if (typeof provider.getSupportedTypes !== 'function') continue;
      const supported = provider.getSupportedTypes();
      const labels = typeof provider.getTypeLabels === 'function' ? provider.getTypeLabels() : {};
      supported.forEach((type) => {
        types.push({ provider: name, type, label: labels[type] || type });
      });
    }
    return types;
  }

  loadRulesets(rulesets) {
    this.rulesets = rulesets || [];
  }

  start() {
    if (this.intervalId) return;
    setTimeout(() => this.evaluate(), 0);
    this.intervalId = setInterval(() => this.evaluate(), 300);
  }

  stop() {
    if (this.intervalId) {
      clearInterval(this.intervalId);
      this.intervalId = null;
    }
    this.activeAlerts = [];
    this.blockResults = [];
    this.hasAlerts = false;
    this._lastContent = null;
    this._notify();
  }

  setBlockResults(filterRules) {
    this.blockResults = (filterRules || []).map((alert) => ({
      effectType: alert.effect_type,
      displayMode: alert.display_mode,
      message: alert.message, // already interpolated server-side
      tokens: alert.tokens || {},
      isBlock: true,
    }));
    this.hasAlerts = this.activeAlerts.length > 0 || this.blockResults.length > 0;
    this._notify();
    if (typeof m !== 'undefined') m.redraw();
  }

  clearBlockResults() {
    this.blockResults = [];
    this.hasAlerts = this.activeAlerts.length > 0;
    this._notify();
    if (typeof m !== 'undefined') m.redraw();
  }

  evaluate() {
    const application = (typeof window !== 'undefined' && window.app) || null;
    const composer = application && application.composer;
    if (!composer || typeof composer.isVisible !== 'function' || !composer.isVisible()) return;

    const content = (composer.fields && composer.fields.content && composer.fields.content()) || '';
    const title = (composer.fields && composer.fields.title && composer.fields.title()) || '';

    // Short-circuit when content and title have not changed since the last tick.
    const stateKey = `${title}\n\n${content}`;
    if (stateKey === this._lastStateKey) return;
    this._lastStateKey = stateKey;

    const activeAlerts = [];

    for (const ruleset of this.rulesets) {
      if (!this.scopeMatches(ruleset, composer, application)) continue;

      let targetContent = content;
      if (ruleset.evaluateTitle !== false && title) {
        targetContent = title + '\n\n' + content;
      }

      const tokens = this.evaluateRuleset(ruleset, targetContent);
      if (tokens !== null) {
        activeAlerts.push({
          ruleset,
          tokens,
          message: this.interpolate(ruleset.message, tokens),
        });
        if (ruleset.blockCascade) break;
      }
    }

    const changed = this.alertsChanged(this.activeAlerts, activeAlerts);
    this.activeAlerts = activeAlerts;
    this.hasAlerts = this.activeAlerts.length > 0 || this.blockResults.length > 0;

    if (changed) {
      this._notify();
      if (typeof m !== 'undefined') m.redraw();
    }
  }

  evaluateRuleset(ruleset, content) {
    if (!ruleset.rules || ruleset.rules.length === 0) return null;

    // Explicit sort by sortOrder so token merge order is deterministic
    // regardless of how the payload was serialised.
    const rules = [...ruleset.rules].sort((a, b) => (a.sortOrder || 0) - (b.sortOrder || 0));
    const results = [];
    const op = ruleset.ruleOperator;
    const evaluateAllRules = ruleset.evaluateAllRules || false;

    for (const rule of rules) {
      const r = this.evaluateRule(rule, content, ruleset);
      results.push(r);

      if (!evaluateAllRules) {
        if (op === 'OR' && r !== null) break;
        if (op === 'AND' && r === null) break;
      }
    }

    const triggered = op === 'AND'
      ? results.every((r) => r !== null)
      : results.some((r) => r !== null);

    if (!triggered) return null;

    let merged = {};
    for (const r of results) {
      if (r !== null) {
        for (const [key, val] of Object.entries(r)) {
          if (typeof merged[key] === 'string' && typeof val === 'string') {
            const existing = merged[key].split(',').map((s) => s.trim());
            const added = val.split(',').map((s) => s.trim());
            merged[key] = Array.from(new Set([...existing, ...added])).join(', ');
          } else {
            merged[key] = val;
          }
        }
      }
    }
    return merged;
  }

  evaluateRule(rule, content, ruleset = null) {
    const provider = this.providers[rule.provider];
    if (!provider || typeof provider.evaluate !== 'function') return null;
    if (!provider.getSupportedTypes().includes(rule.type)) return null;

    let result = null;
    try {
      const config = rule.config || {};
      result = provider.evaluate(rule.type, content, config);
    } catch (e) {
      console.error(`[filter-rule-manager] rule ${rule.provider}/${rule.type} threw`, e);
      return null;
    }

    if (rule.negate) {
      return result === null ? {} : null;
    }
    return result;
  }

  scopeMatches(ruleset, composer, application) {
    let isPrivate = false;
    let tagIds = [];

    let discussion = null;
    if (composer.body && composer.body.attrs) {
      if (composer.body.attrs.post) {
        discussion = composer.body.attrs.post.discussion();
      } else if (composer.body.attrs.discussion) {
        discussion = composer.body.attrs.discussion;
      }
    }

    if (discussion) {
      // Replying or Editing
      const recipientUsers = discussion.recipientUsers && discussion.recipientUsers();
      const recipientGroups = discussion.recipientGroups && discussion.recipientGroups();
      const isPrivateAttr = (discussion.isPrivate && discussion.isPrivate()) ||
                            (discussion.isPrivateDiscussion && discussion.isPrivateDiscussion()) ||
                            discussion.attribute('isPrivate') ||
                            discussion.attribute('is_private');
      
      isPrivate = !!isPrivateAttr || (recipientUsers && recipientUsers.length > 0) || (recipientGroups && recipientGroups.length > 0);
      tagIds = (discussion.tags && discussion.tags())
        ? discussion.tags().map((t) => t.id())
        : [];
    } else {
      // New Discussion
      const resolveField = (val) => typeof val === 'function' ? val() : val;
      
      let recipientUsers = resolveField(composer.fields.recipientUsers) || [];
      let recipientGroups = resolveField(composer.fields.recipientGroups) || [];
      
      // Byobu stores recipients in composer.fields.recipients as an ItemList
      const recipientsField = resolveField(composer.fields.recipients);
      const recipientsArray = recipientsField && typeof recipientsField.toArray === 'function' ? recipientsField.toArray() : (Array.isArray(recipientsField) ? recipientsField : []);
      
      const fieldsIsPrivate = resolveField(composer.fields.isPrivate);
      
      const hasRecipients = recipientUsers.length > 0 || recipientGroups.length > 0 || recipientsArray.length > 0;
      isPrivate = !!fieldsIsPrivate || hasRecipients;
      
      tagIds = resolveField(composer.fields.tags)
        ? resolveField(composer.fields.tags).map((t) => t.id())
        : [];
    }

    switch (ruleset.scopeType) {
      case 'global':       return true;
      case 'normal_post':  return !isPrivate;
      case 'private_post': return isPrivate;
      case 'tag':
        if (!ruleset.scopeTagIds || ruleset.scopeTagIds.length === 0) return false;
        return tagIds.some((id) => ruleset.scopeTagIds.includes(id));
      default:
        return false;
    }
  }

  /**
   * Interpolate {{token}} placeholders.
   *
   * Token VALUES are HTML-escaped because they often come from user-controlled
   * post content (e.g. the matched regex substring). The template itself is
   * admin-authored and therefore trusted, so callers can safely render the
   * result with `m.trust(...)` for admin-supplied formatting like <br>.
   */
  interpolate(template, tokens) {
    if (!template) return '';
    const strTemplate = Array.isArray(template) ? template.join('') : String(template);
    return strTemplate.replace(/\{\{(\w+)\}\}/g, (match, key) => {
      if (!tokens || !Object.prototype.hasOwnProperty.call(tokens, key)) return match;
      return escapeHtml(tokens[key]);
    });
  }

  alertsChanged(oldAlerts, newAlerts) {
    if (oldAlerts.length !== newAlerts.length) return true;
    for (let i = 0; i < oldAlerts.length; i++) {
      if (oldAlerts[i].ruleset.id !== newAlerts[i].ruleset.id) return true;
      // Comparing the rendered message catches token-value changes too.
      if (oldAlerts[i].message !== newAlerts[i].message) return true;
    }
    return false;
  }
}

export default new FilterEngine();
