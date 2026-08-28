/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import app from 'flarum/admin/app';
import type Mithril from 'mithril';
import WordsListConfig from '../components/config/WordsListConfig';
import PatternsListConfig from '../components/config/PatternsListConfig';
import GroupListConfig from '../components/config/GroupListConfig';
import WordCountConfig from '../components/config/WordCountConfig';
import RuleTriggeredConfig from '../components/config/RuleTriggeredConfig';

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
  getSupportedTypes(): string[] {
    return ['contains_word', 'regex', 'group', 'word_count', 'rule_triggered'];
  }

  getTypeLabels(): Record<string, string> {
    return {
      contains_word: String(app.translator.trans('huoxin-filter-rule-manager.admin.type_contains_word')),
      regex: String(app.translator.trans('huoxin-filter-rule-manager.admin.type_regex')),
      group: String(app.translator.trans('huoxin-filter-rule-manager.admin.type_group')),
      word_count: String(app.translator.trans('huoxin-filter-rule-manager.admin.type_word_count')),
      rule_triggered: String(app.translator.trans('huoxin-filter-rule-manager.admin.type_rule_triggered') || 'Has triggered rule(s)'),
    };
  }

  getConfigComponent(type: string): Mithril.ComponentTypes<any, any> | null {
    if (type === 'contains_word') return WordsListConfig;
    if (type === 'regex') return PatternsListConfig;
    if (type === 'group') return GroupListConfig;
    if (type === 'word_count') return WordCountConfig;
    if (type === 'rule_triggered') return RuleTriggeredConfig;
    return null;
  }

  /**
   * Tokens this provider's rules expose for use in the ruleset message.
   * Returning [{ name, description }] makes them discoverable in the
   * RulesetEditorModal token-hint panel.
   */
  getProvidedTokens(type: string): { name: string; description: string; universal?: boolean }[] {
    if (type === 'contains_word') {
      return [
        { name: 'matched_word', description: 'huoxin-filter-rule-manager.admin.token_matched_word_desc' },
        { name: 'matched_text', description: 'huoxin-filter-rule-manager.admin.token_matched_text_desc', universal: true },
      ];
    }
    if (type === 'regex') {
      return [
        { name: 'matched_pattern', description: 'huoxin-filter-rule-manager.admin.token_matched_pattern_desc' },
        { name: 'matched_string', description: 'huoxin-filter-rule-manager.admin.token_matched_string_desc' },
        { name: 'matched_text', description: 'huoxin-filter-rule-manager.admin.token_matched_text_desc', universal: true },
      ];
    }
    if (type === 'group') {
      return [{ name: 'matched_group', description: 'huoxin-filter-rule-manager.admin.token_matched_group_desc' }];
    }
    if (type === 'word_count') {
      return [{ name: 'word_count', description: 'huoxin-filter-rule-manager.admin.token_word_count_desc' }];
    }
    return [];
  }
}
