/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import type { FilterRuleProvider } from '../../common/FilterEngine';
import type { AbstractBuiltinRule } from './builtin/AbstractBuiltinRule';
import ContainsWordRule from './builtin/ContainsWordRule';
import RegexRule from './builtin/RegexRule';
import GroupRule from './builtin/GroupRule';
import WordCountRule from './builtin/WordCountRule';
import RuleTriggeredRule from './builtin/RuleTriggeredRule';

/**
 * Forum-side BuiltinProvider — handles real-time evaluation against the
 * composer content.
 */
export default class BuiltinProvider implements FilterRuleProvider {
  protected rules: Record<string, AbstractBuiltinRule> = {};

  constructor() {
    this.registerRule(new ContainsWordRule());
    this.registerRule(new RegexRule());
    this.registerRule(new GroupRule());
    this.registerRule(new WordCountRule());
    this.registerRule(new RuleTriggeredRule());
  }

  registerRule(rule: AbstractBuiltinRule) {
    this.rules[rule.name()] = rule;
  }

  getSupportedTypes(): string[] {
    return Object.keys(this.rules);
  }

  evaluate(type: string, content: string, config: Record<string, unknown>): Record<string, string> | null {
    if (this.rules[type]) {
      return this.rules[type].evaluate(content, config);
    }
    return null;
  }
}
