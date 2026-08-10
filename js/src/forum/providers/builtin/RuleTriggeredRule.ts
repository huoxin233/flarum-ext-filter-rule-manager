/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import { AbstractBuiltinRule } from './AbstractBuiltinRule';
import filterEngine from '../../../common/FilterEngine';

export default class RuleTriggeredRule extends AbstractBuiltinRule {
  name(): string {
    return 'rule_triggered';
  }

  evaluate(content: string, config: Record<string, unknown>): Record<string, string> | null {
    const matchRuleId = config.match_rule_id !== undefined ? String(config.match_rule_id) : '';
    const matchNameRaw = typeof config.match_name === 'string' ? config.match_name : '';
    const matchNameLower = matchNameRaw.toLowerCase();
    const matchScope = config.match_scope as string;
    const matchInterv = config.match_intervention as string;
    const matchDisplay = config.match_display as string;

    let nameRegex: RegExp | null = null;
    if (!matchRuleId && matchNameRaw && matchNameRaw.startsWith('/') && matchNameRaw.lastIndexOf('/') > 0) {
      try {
        const lastSlash = matchNameRaw.lastIndexOf('/');
        const pattern = matchNameRaw.substring(1, lastSlash);
        const flags = matchNameRaw.substring(lastSlash + 1);
        nameRegex = new RegExp(pattern, flags);
      } catch (e) {
        // Fallback to substring match if regex is invalid
      }
    }

    // Active Alerts have full ruleset info
    const alertsToCheck = filterEngine.currentRunAlerts || filterEngine.activeAlerts;
    const triggeredAlert = alertsToCheck.find((alert: any) => {
      const ruleset = alert.ruleset;
      if (matchRuleId && String(ruleset.id) !== matchRuleId) return false;

      if (!matchRuleId && matchNameRaw && ruleset.name) {
        const nameStr = String(ruleset.name);
        let matched = false;

        if (nameRegex) {
          matched = nameRegex.test(nameStr);
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
    const triggeredBlock = filterEngine.blockResults.find((block: any) => {
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
}
