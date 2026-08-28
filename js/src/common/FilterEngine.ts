/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import app from 'flarum/common/app';
import type Mithril from 'mithril';
import extractText from 'flarum/common/utils/extractText';

export interface ASTNode {
  type: string;
  provider?: string;
  ruleType?: string;
  operator?: string;
  value?: unknown;
  left?: ASTNode;
  right?: ASTNode;
  node?: ASTNode;
  targetModifiers?: string[];
  _key?: number;
}

export interface FilterRuleProvider {
  getSupportedTypes(): string[];
  getTypeLabels?(): Record<string, string>;
  getProviderLabel?(): string;
  evaluate?(ruleType: string, content: string, config: Record<string, unknown>): Record<string, string> | null;
  getConfigComponent?(type: string): Mithril.ComponentTypes<unknown, unknown> | null;
}

export interface Ruleset {
  id: string | number;
  name: string;
  interventionType: string;
  displayMode: string;
  message: string;
  scopeType: string;
  postContext?: string;
  scopeTagIds?: (string | number)[];
  evaluateTitle?: boolean;
  evaluateAllRules?: boolean | (() => boolean);
  blockCascade?: boolean;
  compiledAst?: () => ASTNode;
  compiled_ast?: ASTNode;
  displaySettings?: Record<string, unknown>;
}

export interface ActiveAlert {
  ruleset: Ruleset;
  tokens: Record<string, string>;
  message: string;
  displaySettings: Record<string, unknown>;
}

export interface BlockResult {
  interventionType: string;
  displayMode: string;
  message: string;
  tokens: Record<string, string>;
  displaySettings: Record<string, unknown>;
  isBlock: boolean;
}

export interface EngineState {
  activeAlerts: ActiveAlert[];
  blockResults: BlockResult[];
}

export type SubscriberCallback = (state: EngineState) => void;

function escapeHtml(str: string): string {
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

export class FilterEngine {
  public rulesets: Ruleset[] = [];
  public providers: Record<string, FilterRuleProvider> = {};
  public modifiers: Record<string, (content: string, context?: any) => string> = {};
  public templates: Record<
    string,
    { component: Mithril.ComponentTypes<unknown, unknown>; settingsComponent: Mithril.ComponentTypes<unknown, unknown> | null }
  > = {};
  public displayModes: Record<string, string> = {};
  public activeAlerts: ActiveAlert[] = [];
  public currentRunAlerts?: ActiveAlert[];
  public blockResults: BlockResult[] = [];
  public globalTokens: Record<string, string> = {};
  public intervalId: number | null = null;
  public hasAlerts: boolean = false;

  private _lastStateKey: string | null = null;
  private _subscribers: SubscriberCallback[] = [];

  /**
   * Subscribe to alert-state changes (active/block result mutations).
   * Returns an unsubscribe function. Used by FilterRulePopupDispatcher to drive
   * toast and modal display modes from a single source of truth.
   */
  subscribe(callback: SubscriberCallback): () => void {
    if (typeof callback !== 'function') return () => {};
    this._subscribers.push(callback);
    return () => {
      this._subscribers = this._subscribers.filter((cb) => cb !== callback);
    };
  }

  private _notify(): void {
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
  registerProvider(name: string, provider: FilterRuleProvider): void {
    this.providers[name] = provider;
  }

  /**
   * Register a frontend modifier function.
   */
  registerModifier(name: string, modifier: (content: string, context?: any) => string): void {
    this.modifiers[name] = modifier;
  }

  /**
   * Register a global context token to be displayed in the Registry and Token Hint UI.
   */
  registerGlobalToken(name: string, descriptionTranslationKey: string): void {
    this.globalTokens[name] = descriptionTranslationKey;
  }

  /**
   * Look up a registered provider by name. Returns null if unknown.
   * Used by the admin RuleBuilder to find a provider's `getConfigComponent`.
   */
  getProvider(name: string): FilterRuleProvider | null {
    return this.providers[name] || null;
  }

  /**
   * Register a display template component.
   */
  registerTemplate(
    name: string,
    component: Mithril.ComponentTypes<unknown, unknown>,
    settingsComponent: Mithril.ComponentTypes<unknown, unknown> | null = null
  ): void {
    this.templates[name] = {
      component: component as any,
      settingsComponent: settingsComponent as any,
    };
  }

  /**
   * Get a registered display template component.
   */
  getTemplate(name: string): Mithril.ComponentTypes<unknown, unknown> | null {
    return this.templates[name] ? this.templates[name].component : null;
  }

  /**
   * Get a registered display template settings component.
   */
  getTemplateSettingsComponent(name: string): Mithril.ComponentTypes<unknown, unknown> | null {
    return this.templates[name] ? this.templates[name].settingsComponent : null;
  }

  /**
   * Register a display mode placement option.
   * @param {string} key - The unique identifier for the mode
   * @param {string} translationKey - The translation key for the UI label
   */
  registerDisplayMode(key: string, translationKey: string): void {
    this.displayModes[key] = translationKey;
  }

  /**
   * Get all registered display modes.
   */
  getDisplayModes(): Record<string, string> {
    return this.displayModes;
  }

  getRegisteredFrontendTypes(): { provider: string; providerLabel: string; type: string; label: string }[] {
    const types: { provider: string; providerLabel: string; type: string; label: string }[] = [];

    // `app` is imported natively.

    for (const [name, provider] of Object.entries(this.providers)) {
      if (typeof provider.getSupportedTypes !== 'function') continue;
      const supported = provider.getSupportedTypes();
      const labels = typeof provider.getTypeLabels === 'function' ? provider.getTypeLabels() : {};

      let providerLabel = name;
      if (typeof provider.getProviderLabel === 'function') {
        providerLabel = provider.getProviderLabel();
      } else if (app && app.translator) {
        const transKey = `huoxin-filter-rule-manager.admin.providers.${name}`;
        const translated = app.translator.trans(transKey);
        const isTranslated = Array.isArray(translated) ? translated[0] !== transKey : translated !== transKey;
        if (isTranslated) providerLabel = String(Array.isArray(translated) ? translated[0] : translated);
      }

      supported.forEach((type) => {
        types.push({ provider: name, providerLabel, type, label: labels[type] || type });
      });
    }
    return types;
  }

  loadRulesets(rulesets: Ruleset[]): void {
    this.rulesets = rulesets || [];
  }

  start(): void {
    if (this.intervalId) return;
    setTimeout(() => this.evaluate(), 0);
    this.intervalId = window.setInterval(() => this.evaluate(), 300);
  }

  stop(): void {
    if (this.intervalId) {
      window.clearInterval(this.intervalId);
      this.intervalId = null;
    }
    this.activeAlerts = [];
    this.blockResults = [];
    this.hasAlerts = false;
    this._lastStateKey = null;
    this._notify();
  }

  setBlockResults(filterRules: Record<string, unknown>[]): void {
    this.blockResults = (filterRules || []).map((alert) => {
      const tokens = (alert.tokens as Record<string, string>) || {};
      let displaySettings = (alert.display_settings as Record<string, unknown>) || {};

      if (typeof displaySettings.title === 'string') {
        displaySettings = { ...displaySettings, title: this.interpolate(displaySettings.title, tokens) };
      }

      return {
        interventionType: alert.intervention_type as string,
        displayMode: alert.display_mode as string,
        message: alert.message as string,
        tokens,
        displaySettings,
        isBlock: true,
      };
    });
    this.hasAlerts = this.activeAlerts.length > 0 || this.blockResults.length > 0;
    this._notify();
    if (typeof m !== 'undefined') m.redraw();
  }

  clearBlockResults(): void {
    this.blockResults = [];
    this.hasAlerts = this.activeAlerts.length > 0;
    this._notify();
    if (typeof m !== 'undefined') m.redraw();
  }

  evaluate(): void {
    const application = app;
    const composer = application && (application as any).composer;
    if (!composer || typeof composer.isVisible !== 'function' || !composer.isVisible()) return;

    const content = (composer.fields && composer.fields.content && composer.fields.content()) || '';
    const title = (composer.fields && composer.fields.title && composer.fields.title()) || '';

    const context = { composer, application };

    const stateKey = `${title}\n\n${content}`;
    if (stateKey === this._lastStateKey) return;
    this._lastStateKey = stateKey;
    const activeAlerts: ActiveAlert[] = [];
    this.currentRunAlerts = activeAlerts;

    for (const ruleset of this.rulesets) {
      if (!this.scopeMatches(ruleset, composer, application)) continue;

      let targetContent = content;
      if (ruleset.evaluateTitle !== false && title) {
        targetContent = title + '\n\n' + content;
      }

      const rawTokens = this.evaluateRuleset(ruleset, targetContent, context);
      if (rawTokens !== null) {
        // Inject built-in global tokens that are not generated by the AST engine
        const tokens = { ...rawTokens, rule_name: String(ruleset.name || '') };
        let displaySettings = ruleset.displaySettings || {};

        if (typeof displaySettings.title === 'string') {
          displaySettings = { ...displaySettings, title: this.interpolate(displaySettings.title, tokens) };
        }

        activeAlerts.push({
          ruleset,
          tokens,
          message: this.interpolate(ruleset.message, tokens),
          displaySettings,
        });
        if (ruleset.blockCascade) break;
      }
    }

    const changed = this.alertsChanged(this.activeAlerts, activeAlerts);
    this.activeAlerts = activeAlerts;
    this.currentRunAlerts = undefined;
    this.hasAlerts = this.activeAlerts.length > 0 || this.blockResults.length > 0;

    if (changed) {
      this._notify();
      if (typeof m !== 'undefined') m.redraw();
    }
  }

  evaluateRuleset(ruleset: Ruleset, content: string, context: any = {}): Record<string, string> | null {
    const ast = typeof ruleset.compiledAst === 'function' ? ruleset.compiledAst() : ruleset.compiled_ast;
    if (!ast) return null;

    const modifiedContentCache: Record<string, string> = {};
    return this.evaluateAST(ast, content, ruleset, modifiedContentCache, context);
  }

  evaluateAST(
    node: ASTNode | null | undefined,
    content: string,
    ruleset: Ruleset,
    modifiedContentCache: Record<string, string>,
    context: any = {}
  ): Record<string, string> | null {
    if (!node) return null;

    if (node.type === 'logical') {
      const left = this.evaluateAST(node.left, content, ruleset, modifiedContentCache, context);

      if (node.operator === 'OR') {
        const evaluateAll = typeof ruleset.evaluateAllRules === 'function' ? ruleset.evaluateAllRules() : ruleset.evaluateAllRules;
        if (left !== null && !evaluateAll) return left;

        const right = this.evaluateAST(node.right, content, ruleset, modifiedContentCache, context);
        if (left !== null && right !== null) return this.mergeResults([left, right]);
        return left !== null ? left : right;
      }

      if (node.operator === 'AND') {
        if (left === null) return null;
        const right = this.evaluateAST(node.right, content, ruleset, modifiedContentCache, context);
        if (right === null) return null;
        return this.mergeResults([left, right]);
      }
    }

    if (node.type === 'not') {
      const result = this.evaluateAST(node.node, content, ruleset, modifiedContentCache, context);
      return result === null ? {} : null;
    }

    if (node.type === 'rule') {
      return this.evaluateRuleNode(node, content, modifiedContentCache, context);
    }

    return null;
  }

  mergeResults(results: Record<string, string>[]): Record<string, string> {
    let merged: Record<string, string> = {};
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

  evaluateRuleNode(node: ASTNode, content: string, modifiedContentCache: Record<string, string>, context: any = {}): Record<string, string> | null {
    if (!node.provider) return null;
    const provider = this.providers[node.provider];
    if (!provider || typeof provider.evaluate !== 'function') return null;
    if (!provider.getSupportedTypes().includes(node.ruleType as string)) return null;

    let isObject = typeof node.value === 'object' && node.value !== null && !Array.isArray(node.value);

    let targetContent = content;
    let modifierKeys: string[] = node.targetModifiers && Array.isArray(node.targetModifiers) ? node.targetModifiers : [];

    let result = null;
    try {
      if (modifierKeys.length > 0) {
        let currentCacheKey = '';
        modifierKeys.forEach((modifierKey) => {
          currentCacheKey = currentCacheKey === '' ? modifierKey : `${currentCacheKey},${modifierKey}`;

          if (modifiedContentCache[currentCacheKey] !== undefined) {
            targetContent = modifiedContentCache[currentCacheKey];
          } else {
            if (this.modifiers[modifierKey]) {
              targetContent = this.modifiers[modifierKey](targetContent, context);
            }
            modifiedContentCache[currentCacheKey] = targetContent;
          }
        });
      }

      let config: any = isObject ? { ...(node.value as object), operator: node.operator } : { operator: node.operator, value: node.value };

      result = provider.evaluate(node.ruleType as string, targetContent, config);
    } catch (e) {
      console.error(`[filter-rule-manager] rule ${String(node.provider)}/${String(node.ruleType)} threw`, e);
      return null;
    }

    return result;
  }

  scopeMatches(ruleset: Ruleset, composer: any, application: any): boolean {
    if (!composer) return false;
    let isPrivate = false;
    let tagIds: (string | number)[] = [];

    let discussion = null;
    if ((composer.body as any) && (composer.body as any).attrs) {
      if ((composer.body as any).attrs.post) {
        discussion = (composer.body as any).attrs.post.discussion();
      } else if ((composer.body as any).attrs.discussion) {
        discussion = (composer.body as any).attrs.discussion;
      }
    }
    const safeComposer = composer as any;

    if (discussion) {
      const recipientUsers = discussion.recipientUsers && discussion.recipientUsers();
      const recipientGroups = discussion.recipientGroups && discussion.recipientGroups();
      const isPrivateAttr =
        (discussion.isPrivate && discussion.isPrivate()) ||
        (discussion.isPrivateDiscussion && discussion.isPrivateDiscussion()) ||
        discussion.attribute('isPrivate') ||
        discussion.attribute('is_private');

      isPrivate = !!isPrivateAttr || (recipientUsers && recipientUsers.length > 0) || (recipientGroups && recipientGroups.length > 0);
      tagIds = discussion.tags && discussion.tags() ? discussion.tags().map((t: { id: () => string | number }) => t.id()) : [];
    } else {
      const resolveField = (val: unknown) => (typeof val === 'function' ? val() : val);

      let recipientUsers = resolveField(safeComposer.fields?.recipientUsers) || [];
      let recipientGroups = resolveField(safeComposer.fields?.recipientGroups) || [];

      const recipientsField = resolveField(safeComposer.fields?.recipients) as Record<string, unknown> | unknown[];
      const recipientsArray =
        recipientsField && typeof (recipientsField as any).toArray === 'function'
          ? (recipientsField as any).toArray()
          : Array.isArray(recipientsField)
          ? recipientsField
          : [];

      const fieldsIsPrivate = resolveField(safeComposer.fields?.isPrivate);

      const hasRecipients = (recipientUsers as unknown[]).length > 0 || (recipientGroups as unknown[]).length > 0 || recipientsArray.length > 0;
      isPrivate = !!fieldsIsPrivate || hasRecipients;

      tagIds = resolveField(safeComposer.fields?.tags)
        ? (resolveField(safeComposer.fields?.tags) as unknown[]).map((t) => (t as { id: () => string | number }).id())
        : [];
    }

    const postContext = ruleset.postContext || 'all';
    if (postContext !== 'all') {
      const resolveField = (val: unknown) => (typeof val === 'function' ? val() : val);
      const hasTitleField = typeof safeComposer?.fields?.title !== 'undefined' && resolveField(safeComposer.fields?.title) !== null;
      const isPostEditFirst =
        safeComposer?.body?.attrs?.post && typeof safeComposer.body.attrs.post.number === 'function' && safeComposer.body.attrs.post.number() === 1;

      const isDiscussionStart = (hasTitleField && !discussion) || !!isPostEditFirst;

      if (postContext === 'discussion_start' && !isDiscussionStart) {
        return false;
      }
      if (postContext === 'reply' && isDiscussionStart) {
        return false;
      }
    }

    switch (ruleset.scopeType) {
      case 'global':
        return true;
      case 'normal_post':
        return !isPrivate;
      case 'private_post':
        return isPrivate;
      case 'tag':
        if (!ruleset.scopeTagIds || ruleset.scopeTagIds.length === 0) return false;
        const targetTags = ruleset.scopeTagIds.map(String);
        return tagIds.map(String).some((id) => targetTags.includes(id));
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
  interpolate(template: string | string[], tokens: Record<string, string> | undefined): string {
    if (!template) return '';
    let strTemplate = Array.isArray(template) ? template.join('') : String(template);

    // If the template strictly matches a Flarum translation key format (e.g. namespace.key)
    // we translate it first and inject the tokens natively into the translator.
    if (/^[a-zA-Z0-9\-_]+(?:\.[a-zA-Z0-9\-_]+)+$/.test(strTemplate) && typeof app !== 'undefined' && app.translator && app.translator.trans) {
      try {
        const translated = app.translator.trans(strTemplate, tokens || {});
        if (typeof translated === 'string' && translated !== strTemplate && translated !== '') {
          strTemplate = translated;
        } else if (Array.isArray(translated) && translated.length > 0) {
          const textOnly = extractText(translated);
          if (textOnly && textOnly !== strTemplate) {
            strTemplate = textOnly;
          }
        }
      } catch (e) {
        // Fallback to original string on ICU parse error
      }
    }

    // 1. Process positive conditional blocks: {{#token}}...{{/token}}
    if (strTemplate.includes('{{#')) {
      while (strTemplate.includes('{{#')) {
        const prev = strTemplate;
        strTemplate = strTemplate.replace(/\{\{#(\w+)\}\}([\s\S]*?)\{\{\/\1\}\}/g, (_, key, content) => {
          const hasValue =
            tokens && Object.prototype.hasOwnProperty.call(tokens, key) && tokens[key] !== '' && tokens[key] !== null && tokens[key] !== undefined;
          return hasValue ? content : '';
        });
        if (strTemplate === prev) break;
      }
    }

    // 2. Process inverted conditional blocks: {{^token}}...{{/token}}
    if (strTemplate.includes('{{^')) {
      while (strTemplate.includes('{{^')) {
        const prev = strTemplate;
        strTemplate = strTemplate.replace(/\{\{\^(\w+)\}\}([\s\S]*?)\{\{\/\1\}\}/g, (_, key, content) => {
          const isEmpty =
            !tokens || !Object.prototype.hasOwnProperty.call(tokens, key) || tokens[key] === '' || tokens[key] === null || tokens[key] === undefined;
          return isEmpty ? content : '';
        });
        if (strTemplate === prev) break;
      }
    }

    // 3. Process variable interpolation: {{token}}
    return strTemplate.replace(/\{\{(\w+)\}\}/g, (match, key) => {
      if (!tokens || !Object.prototype.hasOwnProperty.call(tokens, key)) return match;
      return escapeHtml(tokens[key]);
    });
  }

  alertsChanged(oldAlerts: ActiveAlert[], newAlerts: ActiveAlert[]): boolean {
    if (oldAlerts.length !== newAlerts.length) return true;
    for (let i = 0; i < oldAlerts.length; i++) {
      if (oldAlerts[i].ruleset.id !== newAlerts[i].ruleset.id) return true;
      if (oldAlerts[i].message !== newAlerts[i].message) return true;
    }
    return false;
  }
}

const filterEngine = new FilterEngine();
export default filterEngine;
