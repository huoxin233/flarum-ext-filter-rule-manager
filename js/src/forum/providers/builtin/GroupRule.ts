/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import app from 'flarum/forum/app';
import type Group from 'flarum/common/models/Group';
import { AbstractBuiltinRule } from './AbstractBuiltinRule';

export default class GroupRule extends AbstractBuiltinRule {
  name(): string {
    return 'group';
  }

  evaluate(content: string, config: Record<string, unknown>): Record<string, string> | null {
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
}
