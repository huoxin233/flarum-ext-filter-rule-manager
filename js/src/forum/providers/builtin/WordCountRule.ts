/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import { AbstractBuiltinRule } from './AbstractBuiltinRule';

const CJK_REGEX = /[\u4E00-\u9FA5\u3040-\u309F\u30A0-\u30FF\uAC00-\uD7AF]/g;

export default class WordCountRule extends AbstractBuiltinRule {
  name(): string {
    return 'word_count';
  }

  evaluate(content: string, config: Record<string, unknown>): Record<string, string> | null {
    let text = String(content || '');

    // Match CJK characters
    const cjkMatches = text.match(CJK_REGEX) || [];
    const cjkCount = cjkMatches.length;

    // Match Latin words
    const latinText = text.replace(CJK_REGEX, ' ');
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
}
