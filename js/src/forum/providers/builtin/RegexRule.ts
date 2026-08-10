/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import { AbstractBuiltinRule } from './AbstractBuiltinRule';

export default class RegexRule extends AbstractBuiltinRule {
  name(): string {
    return 'regex';
  }

  evaluate(content: string, config: Record<string, unknown>): Record<string, string> | null {
    const scanAll = config.scan_all || false;
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

    return null;
  }
}
