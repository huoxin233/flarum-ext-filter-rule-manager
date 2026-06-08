import app from 'flarum/admin/app';
import Model from 'flarum/common/Model';

import filterEngine from '../common/FilterEngine';
import RulesetManagerPage from './components/RulesetManagerPage';
import BuiltinProvider from './providers/BuiltinProvider';

class Ruleset extends Model {}
Object.assign(Ruleset.prototype, {
  name: Model.attribute('name'),
  priority: Model.attribute('priority'),
  ruleOperator: Model.attribute('ruleOperator'),
  effectType: Model.attribute('effectType'),
  displayMode: Model.attribute('displayMode'),
  message: Model.attribute('message'),
  flagMessage: Model.attribute('flagMessage'),
  evaluateAllRules: Model.attribute('evaluateAllRules'),
  blockCascade: Model.attribute('blockCascade'),
  isActive: Model.attribute('isActive'),
  autoFlag: Model.attribute('autoFlag'),
  requireApproval: Model.attribute('requireApproval'),
  scopeType: Model.attribute('scopeType'),
  scopeTagIds: Model.attribute('scopeTagIds'),
  // Rules are inlined as a JSON attribute (array of POJOs) — see
  // RulesetSerializer. They are NOT separate JSON:API resources.
  rules: Model.attribute('rules'),
});

export { Ruleset };

app.initializers.add('huoxin/filter-rule-manager', () => {
  // Expose the engine in admin too so the rule builder can list frontend-only
  // rule types registered by provider extensions (e.g. bbcode-intellisense).
  // Each bundle gets its own engine instance — provider extensions must register
  // their type metadata in BOTH their forum and admin entry points if they want
  // those types to appear in the admin rule builder.
  app.filterRuleManager = filterEngine;

  // Register the admin-side builtin provider so RuleBuilder can mount its
  // custom config components (WordsListConfig / PatternsListConfig) instead
  // of the generic JSON fallback.
  filterEngine.registerProvider('builtin', new BuiltinProvider());

  app.store.models['filter-rule-rulesets'] = Ruleset;

  app.extensionData
    .for('huoxin-filter-rule-manager')
    .registerPage(RulesetManagerPage);
});
