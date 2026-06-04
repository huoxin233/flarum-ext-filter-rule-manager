import WordsListConfig from '../components/config/WordsListConfig';
import PatternsListConfig from '../components/config/PatternsListConfig';

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
 *   getConfigComponent(type): MithrilComponentClass | null
 *     - When present and non-null, the rule builder mounts this component
 *       to edit the rule's config. The component receives:
 *         attrs.config:   the current config object (POJO)
 *         attrs.type:     the rule type
 *         attrs.onchange: (newConfig: object) => void
 *       Returning null falls back to the JSON textarea.
 */
export default class BuiltinProvider {
  getSupportedTypes() {
    return ['contains_word', 'regex'];
  }

  getTypeLabels() {
    return {
      contains_word: app.translator.trans('huoxin-filter-rule-manager.admin.type_contains_word'),
      regex: app.translator.trans('huoxin-filter-rule-manager.admin.type_regex'),
    };
  }

  getConfigComponent(type) {
    if (type === 'contains_word') return WordsListConfig;
    if (type === 'regex') return PatternsListConfig;
    return null;
  }

  /**
   * Tokens this provider's rules expose for use in the ruleset message.
   * Returning [{ name, description }] makes them discoverable in the
   * RulesetEditorModal token-hint panel.
   */
  getProvidedTokens(type) {
    if (type === 'contains_word') {
      return [
        { name: 'matched_word', description: 'The first listed word that was found in the post content.' },
      ];
    }
    if (type === 'regex') {
      return [
        { name: 'matched_pattern', description: 'The first listed regex pattern that matched.' },
        { name: 'matched_string',  description: 'The substring of the post content that matched.' },
      ];
    }
    return [];
  }
}
