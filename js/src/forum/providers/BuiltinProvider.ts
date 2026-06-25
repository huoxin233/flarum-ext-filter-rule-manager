/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import app from 'flarum/forum/app';
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
    return ['contains_word', 'regex', 'group', 'word_count'];
  }

  evaluate(type: string, content: string, config: Record<string, unknown>): Record<string, string> | null {
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
              flags = body.substring(last + 1);
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
          matched_string: matchedStrings.join(', '),
        };
      }
    }

    if (type === 'group') {
      const user = app.session.user;
      if (!user) return null;

      const userGroups = user.groups() ? user.groups().map((g: any) => parseInt(String(g.id()), 10)) : [];
      let targetGroups = config.groupIds || [];
      if (!Array.isArray(targetGroups)) targetGroups = [targetGroups];

      const targets = targetGroups.map((id: any) => parseInt(String(id), 10));
      const intersect = userGroups.filter((g) => targets.includes(g));

      if (intersect.length > 0) {
        return { matched_group: intersect.join(', ') };
      }

      return null;
    }

    if (type === 'word_count') {
      let text = String(content || '');

      if (config.exclude_mentions) {
        text = text.replace(/@"?[^"#\n]+"?#(?:p)?\d+/g, '');
        text = text.replace(/@\w+/g, '');
      }

      text = text.replace(/https?:\/\/[^\s]+/gi, '');

      // Match CJK characters
      const cjkRegex = /[\u4E00-\u9FA5\u3040-\u309F\u30A0-\u30FF\uAC00-\uD7AF]/g;
      const cjkMatches = text.match(cjkRegex) || [];
      const cjkCount = cjkMatches.length;

      // Match Latin words
      const latinText = text.replace(cjkRegex, ' ');
      // Simple word split, filtering out empty strings
      const latinWords = latinText.split(/\s+/).filter((w) => w.length > 0);
      const latinCount = latinWords.length;

      const count = cjkCount + latinCount;

      const min = config.min !== undefined && config.min !== '' ? parseInt(String(config.min), 10) : null;
      const max = config.max !== undefined && config.max !== '' ? parseInt(String(config.max), 10) : null;

      if (min !== null && !isNaN(min) && count < min) {
        return { word_count: String(count) };
      }

      if (max !== null && !isNaN(max) && count > max) {
        return { word_count: String(count) };
      }

      return null;
    }

    return null;
  }

  /**
   * Normalise `{ plural: string[] }` into a clean, trimmed, non-empty string array.
   */
  normalizeList(config: Record<string, unknown>, plural: string): string[] {
    const cfg = config || {};
    if (Array.isArray(cfg[plural])) {
      return (cfg[plural] as any[])
        .map((v: unknown) => (typeof v === 'string' ? v : String(v)))
        .map((s: string) => s.trim())
        .filter((s: string) => s.length > 0);
    }
    return [];
  }
}
