/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import { AbstractBuiltinRule } from './AbstractBuiltinRule';

export default class ContainsWordRule extends AbstractBuiltinRule {
  name(): string {
    return 'contains_word';
  }

  evaluate(content: string, config: Record<string, unknown>): Record<string, string> | null {
    const scanAll = config.scan_all || false;
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

    if (matches.length > 0) {
      const matched = matches.join(', ');
      return {
        matched_word: matched,
        matched_text: matched,
      };
    }

    return null;
  }
}
