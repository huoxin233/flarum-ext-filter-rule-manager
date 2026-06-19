import type Mithril from 'mithril';
export interface ASTNode {
    type: string;
    provider?: string;
    ruleType?: string;
    operator?: string;
    value?: unknown;
    left?: ASTNode;
    right?: ASTNode;
    node?: ASTNode;
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
    interventionType: string;
    displayMode: string;
    message: string;
    scopeType: string;
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
export declare class FilterEngine {
    rulesets: Ruleset[];
    providers: Record<string, FilterRuleProvider>;
    templates: Record<string, {
        component: Mithril.ComponentTypes<unknown, unknown>;
        settingsComponent: Mithril.ComponentTypes<unknown, unknown> | null;
    }>;
    displayModes: Record<string, string>;
    activeAlerts: ActiveAlert[];
    blockResults: BlockResult[];
    intervalId: number | null;
    hasAlerts: boolean;
    private _lastStateKey;
    private _subscribers;
    /**
     * Subscribe to alert-state changes (active/block result mutations).
     * Returns an unsubscribe function. Used by FilterRulePopupDispatcher to drive
     * toast and modal display modes from a single source of truth.
     */
    subscribe(callback: SubscriberCallback): () => void;
    private _notify;
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
    registerProvider(name: string, provider: FilterRuleProvider): void;
    /**
     * Look up a registered provider by name. Returns null if unknown.
     * Used by the admin RuleBuilder to find a provider's `getConfigComponent`.
     */
    getProvider(name: string): FilterRuleProvider | null;
    /**
     * Register a display template component.
     */
    registerTemplate(name: string, component: Mithril.ComponentTypes<unknown, unknown>, settingsComponent?: Mithril.ComponentTypes<unknown, unknown> | null): void;
    /**
     * Get a registered display template component.
     */
    getTemplate(name: string): Mithril.ComponentTypes<unknown, unknown> | null;
    /**
     * Get a registered display template settings component.
     */
    getTemplateSettingsComponent(name: string): Mithril.ComponentTypes<unknown, unknown> | null;
    /**
     * Register a display mode placement option.
     * @param {string} key - The unique identifier for the mode
     * @param {string} translationKey - The translation key for the UI label
     */
    registerDisplayMode(key: string, translationKey: string): void;
    /**
     * Get all registered display modes.
     */
    getDisplayModes(): Record<string, string>;
    getRegisteredFrontendTypes(): {
        provider: string;
        providerLabel: string;
        type: string;
        label: string;
    }[];
    loadRulesets(rulesets: Ruleset[]): void;
    start(): void;
    stop(): void;
    setBlockResults(filterRules: Record<string, unknown>[]): void;
    clearBlockResults(): void;
    evaluate(): void;
    evaluateRuleset(ruleset: Ruleset, content: string): Record<string, string> | null;
    evaluateAST(node: ASTNode | null | undefined, content: string, ruleset: Ruleset): Record<string, string> | null;
    mergeResults(results: Record<string, string>[]): Record<string, string>;
    evaluateRuleNode(node: ASTNode, content: string): Record<string, string> | null;
    scopeMatches(ruleset: Ruleset, composer: any, application: any): boolean;
    /**
     * Interpolate {{token}} placeholders.
     *
     * Token VALUES are HTML-escaped because they often come from user-controlled
     * post content (e.g. the matched regex substring). The template itself is
     * admin-authored and therefore trusted, so callers can safely render the
     * result with `m.trust(...)` for admin-supplied formatting like <br>.
     */
    interpolate(template: string | string[], tokens: Record<string, string> | undefined): string;
    alertsChanged(oldAlerts: ActiveAlert[], newAlerts: ActiveAlert[]): boolean;
}
declare const _default: FilterEngine;
export default _default;
