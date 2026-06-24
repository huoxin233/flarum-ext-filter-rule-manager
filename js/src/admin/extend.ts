import Extend from 'flarum/common/extenders';
import RulesetManagerPage from './components/RulesetManagerPage';
import Ruleset from './models/Ruleset';

export default [new Extend.Store().add('filter-rule-rulesets', Ruleset), new Extend.Admin().page(RulesetManagerPage as any)];
