import ExtensionPage, { ExtensionPageAttrs } from 'flarum/admin/components/ExtensionPage';
import type Mithril from 'mithril';
import type Model from 'flarum/common/Model';
import type { ASTNode } from '../../common/FilterEngine';
export default class RulesetManagerPage extends ExtensionPage<ExtensionPageAttrs> {
    loading: boolean;
    activeTab: string;
    scopeFilter: string;
    rulesets: Model[];
    providers: Record<string, any>[];
    reordering: boolean;
    toggling: Set<string>;
    registryFilter: string;
    oninit(vnode: Mithril.Vnode<ExtensionPageAttrs, this>): void;
    loadData(): Promise<void>;
    content(): any;
    settingsTab(): Mithril.Children;
    rulesetsTab(): Mithril.Children;
    filteredRulesets(): Model[];
    renderList(list: Model[]): Mithril.Children;
    rulesetRow(ruleset: Model & Record<string, any>, filteredIndex: number, filteredList: Model[]): Mithril.Children;
    countRules(ast: ASTNode | null | undefined): number;
    interventionIcon(intervention: string): "fas fa-exclamation-triangle" | "fas fa-info-circle" | "fas fa-ban";
    registryTab(): Mithril.Children;
    renderProviders(): Mithril.Children;
    renderTemplates(): Mithril.Children;
    renderModes(): Mithril.Children;
    showEditor(ruleset: any): void;
    deleteRuleset(ruleset: any): Promise<void>;
    toggleActive(ruleset: Model, isActive: boolean): Promise<void>;
    /**
     * Move a ruleset within the filtered view by `delta` (-1 / +1).
     *
     * Swaps it with the adjacent ruleset *in the filtered list*, then persists
     * the FULL ruleset order so non-filtered items keep their existing priority
     * positions. This way moving "up" within "Global" still does the right
     * thing even when private/tag rulesets exist with different priorities.
     */
    move(filteredIndex: number, delta: number, filteredList: any[]): Promise<void>;
}
