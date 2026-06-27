import type Mithril from 'mithril';
/**
 * Admin-side view of the builtin provider. Mirrors the type catalogue exposed
 * by the PHP BuiltinProvider but adds the admin-only `getConfigComponent`
 * hook so the rule builder can render a custom form per type instead of the
 * generic JSON textarea.
 *
 * --- PROVIDER CONTRACT (admin) ---
 * A provider object registered into FilterEngine in the admin bundle may
 * expose any of:
 *
 *   getSupportedTypes(): string[]
 *     - Rule type identifiers this provider handles.
 *
 *   getTypeLabels(): { [type]: string }
 *     - Human label for each type, shown in the rule builder picker.
 *
 *   getConfigComponent(type): Mithril.ComponentTypes<any, any> | null
 *     - When present and non-null, the rule builder mounts this component
 *       to edit the rule's config. The component receives:
 *         attrs.config:   the current config object (POJO)
 *         attrs.type:     the rule type
 *         attrs.onchange: (newConfig: object) => void
 *       Returning null falls back to the JSON textarea.
 */
export default class BuiltinProvider {
    getSupportedTypes(): string[];
    getTypeLabels(): Record<string, string>;
    getConfigComponent(type: string): Mithril.ComponentTypes<any, any> | null;
    /**
     * Tokens this provider's rules expose for use in the ruleset message.
     * Returning [{ name, description }] makes them discoverable in the
     * RulesetEditorModal token-hint panel.
     */
    getProvidedTokens(type: string): {
        name: string;
        description: string;
    }[];
}
