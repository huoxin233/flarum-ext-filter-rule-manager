/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import Model from 'flarum/common/Model';

export default class Ruleset extends Model {}

Object.assign(Ruleset.prototype, {
  name: Model.attribute('name'),
  priority: Model.attribute('priority'),
  expression: Model.attribute('expression'),
  compiledAst: Model.attribute('compiledAst'),
  interventionType: Model.attribute('interventionType'),
  displayMode: Model.attribute('displayMode'),
  message: Model.attribute('message'),
  flagMessage: Model.attribute('flagMessage'),
  evaluateAllRules: Model.attribute('evaluateAllRules'),
  evaluateTitle: Model.attribute('evaluateTitle'),
  evasionActive: Model.attribute('evasionActive'),
  evasionTimeout: Model.attribute('evasionTimeout'),
  evasionThreshold: Model.attribute('evasionThreshold'),
  blockCascade: Model.attribute('blockCascade'),
  isActive: Model.attribute('isActive'),
  autoFlag: Model.attribute('autoFlag'),
  requireApproval: Model.attribute('requireApproval'),
  scopeType: Model.attribute('scopeType'),
  scopeTagIds: Model.attribute('scopeTagIds'),
  bypassGroupIds: Model.attribute('bypassGroupIds'),
  displaySettings: Model.attribute('displaySettings'),
});
