/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import app from 'flarum/admin/app';
import Model from 'flarum/common/Model';

import filterEngine from '../common/FilterEngine';
import RulesetManagerPage from './components/RulesetManagerPage';
import BuiltinProvider from './providers/BuiltinProvider';
import BuiltinTemplate from '../common/components/BuiltinTemplate';
import BuiltinTemplateSettings from './components/BuiltinTemplateSettings';

class Ruleset extends Model {}
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
  displaySettings: Model.attribute('displaySettings'),
});

export { Ruleset };

app.initializers.add('huoxin/filter-rule-manager', () => {
  app.filterRuleManager = filterEngine;

  filterEngine.registerDisplayMode('banner', 'huoxin-filter-rule-manager.admin.displays.banner');
  filterEngine.registerDisplayMode('header_banner', 'huoxin-filter-rule-manager.admin.displays.header_banner');
  filterEngine.registerDisplayMode('sidebar', 'huoxin-filter-rule-manager.admin.displays.sidebar');
  filterEngine.registerDisplayMode('toast', 'huoxin-filter-rule-manager.admin.displays.toast');
  filterEngine.registerDisplayMode('modal', 'huoxin-filter-rule-manager.admin.displays.modal');

  filterEngine.registerProvider('builtin', new BuiltinProvider() as any);
  filterEngine.registerTemplate('builtin', BuiltinTemplate as any, BuiltinTemplateSettings as any);

  app.store.models['filter-rule-rulesets'] = Ruleset;

  app.extensionData.for('huoxin-filter-rule-manager').registerPage(RulesetManagerPage as any);
});
