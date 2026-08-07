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
import type Group from 'flarum/common/models/Group';
import filterEngine from '../../common/FilterEngine';

/**
 * Forum-side BuiltinProvider — handles real-time evaluation against the
 * composer content. Supports four rule types:
 *
 *   contains_word  — config: { words: string[] }
 *   regex          — config: { patterns: string[] }
 *   group          — config: { groupIds: string[] | number[] }
 *   word_count     — config: { min?: number, max?: number }
 *
 * For contains_word and regex, the rule triggers if ANY of the listed entries matches.
 * The matched value is exposed as a token for message interpolation.
 */
export default class BuiltinProvider implements FilterRuleProvider {
  getSupportedTypes(): string[] {
    return ['contains_word', 'regex', 'group', 'word_count', 'rule_triggered'];
  }

  evaluate(type: string, content: string, config: Record<string, unknown>): Record<string, string> | null {
    if (type === 'rule_triggered') {
      const matchRuleId = config.match_rule_id !== undefined ? String(config.match_rule_id) : '';
      const matchNameRaw = typeof config.match_name === 'string' ? config.match_name : '';
      const matchNameLower = matchNameRaw.toLowerCase();
      const matchScope = config.match_scope as string;
      const matchInterv = config.match_intervention as string;
      const matchDisplay = config.match_display as string;

      // Active Alerts have full ruleset info
      const alertsToCheck = filterEngine.currentRunAlerts || filterEngine.activeAlerts;
      const triggeredAlert = alertsToCheck.find((alert: any) => {
        const ruleset = alert.ruleset;
        if (matchRuleId && String(ruleset.id) !== matchRuleId) return false;

        if (!matchRuleId && matchNameRaw && ruleset.name) {
          const nameStr = String(ruleset.name);
          let matched = false;
          if (matchNameRaw.startsWith('/') && matchNameRaw.lastIndexOf('/') > 0) {
            try {
              const lastSlash = matchNameRaw.lastIndexOf('/');
              const pattern = matchNameRaw.substring(1, lastSlash);
              const flags = matchNameRaw.substring(lastSlash + 1);
              const rx = new RegExp(pattern, flags);
              matched = rx.test(nameStr);
            } catch (e) {
              matched = nameStr.toLowerCase().includes(matchNameLower);
            }
          } else {
            matched = nameStr.toLowerCase().includes(matchNameLower);
          }
          if (!matched) return false;
        }
        if (!matchRuleId && matchScope && ruleset.scopeType !== matchScope) return false;
        if (!matchRuleId && matchInterv && ruleset.interventionType !== matchInterv) return false;
        if (!matchRuleId && matchDisplay && ruleset.displayMode !== matchDisplay) return false;
        return true;
      });

      if (triggeredAlert) return {};

      // Block Results lack full ruleset, but we can check intervention and display mode
      const triggeredBlock = filterEngine.blockResults.find((block) => {
        // Blocks don't store Name or Scope currently natively via Flarum PHP payload.
        if (matchRuleId) return false; // Cannot match specific rule ID for blocks currently
        if (matchNameRaw) return false;
        if (matchScope) return false;
        if (matchInterv && block.interventionType !== matchInterv) return false;
        if (matchDisplay && block.displayMode !== matchDisplay) return false;
        return true;
      });

      if (triggeredBlock) return {};

      return null;
    }

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

      const groups = user.groups();
      const userGroups = groups ? groups.filter((g): g is Group => g != null).map((g) => parseInt(String(g.id()), 10)) : [];

      let targetGroups = config.groupIds || [];
      if (!Array.isArray(targetGroups)) targetGroups = [targetGroups];

      const targets = (targetGroups as unknown[]).map((id) => parseInt(String(id), 10));
      const intersect = userGroups.filter((g: number) => targets.includes(g));

      if (intersect.length > 0) {
        return { matched_group: intersect.join(', ') };
      }

      return null;
    }

    if (type === 'word_count') {
      let text = String(content || '');

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
