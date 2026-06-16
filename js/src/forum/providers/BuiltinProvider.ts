/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import type { FilterRuleProvider } from '../../common/FilterEngine';

/**
 * Forum-side BuiltinProvider — handles real-time evaluation against the
 * composer content. Supports two rule types:
 *
 *   contains_word  — config: { words: string[] }
 *   regex          — config: { patterns: string[] }
 *
 * For each type, the rule triggers if ANY of the listed entries matches.
 * The first match's value is exposed as a token for message interpolation.
 */
export default class BuiltinProvider implements FilterRuleProvider {
  getSupportedTypes(): string[] {
    return ['contains_word', 'regex'];
  }

  evaluate(type: string, content: string, config: any): Record<string, string> | null {
    const scanAll = config.scan_all || false;
    if (type === 'contains_word') {
      const words = this.normalizeList(config, 'words');
      if (words.length === 0) return null;
      const lowered = String(content).toLowerCase();
      const matches: string[] = [];
      for (const w of words) {
        if (lowered.includes(w.toLowerCase())) {
          matches.push(w);
          if (!scanAll) break;
        }
      }
      return matches.length > 0 ? { matched_word: matches.join(', ') } : null;
    }

    if (type === 'regex') {
      const patterns = this.normalizeList(config, 'patterns');
      if (patterns.length === 0) return null;
      const matchedPatterns: string[] = [];
      const matchedStrings: string[] = [];
      for (const pattern of patterns) {
        try {
          let body = pattern;
          let flags = 'i';
          if (body.startsWith('/')) {
            const last = body.lastIndexOf('/');
            if (last > 0) {
              flags = body.substring(last + 1) || 'i';
              body = body.substring(1, last);
            }
          }
          const re = new RegExp(body, flags);
          const match = String(content).match(re);
          if (match) {
            matchedPatterns.push(pattern);
            matchedStrings.push(match[0]);
            if (!scanAll) break;
          }
        } catch (e) {
          console.warn('[FilterRuleManager] invalid regex in BuiltinProvider:', pattern, e);
        }
      }
      if (matchedPatterns.length > 0) {
        return {
          matched_pattern: matchedPatterns.join(', '),
          matched_string: matchedStrings.join(', ')
        };
      }
    }

    return null;
  }

  /**
   * Normalise `{ plural: string[] }` into a clean, trimmed, non-empty string array.
   */
  normalizeList(config: any, plural: string): string[] {
    const cfg = config || {};
    if (Array.isArray(cfg[plural])) {
      return cfg[plural]
        .map((v: any) => (typeof v === 'string' ? v : String(v)))
        .map((s: string) => s.trim())
        .filter((s: string) => s.length > 0);
    }
    return [];
  }
}

