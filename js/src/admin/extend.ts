import Extend from 'flarum/common/extenders';
import RulesetManagerPage from './components/RulesetManagerPage';
import Ruleset from './models/Ruleset';
import app from 'flarum/admin/app';

const extenders = [
  new Extend.Store().add('filter-rule-rulesets', Ruleset),
  new Extend.Admin()
    .page(RulesetManagerPage as any)
    .setting(() => ({
      setting: 'huoxin-filter.global_evaluate_title',
      label: String(app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evaluate_title')),
      help: String(app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evaluate_title_help')),
      type: 'boolean',
    }))
    .setting(() => ({
      setting: 'huoxin-filter.global_auto_flag',
      label: String(app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_auto_flag')),
      help: String(app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_auto_flag_help')),
      type: 'boolean',
    }))
    .setting(() => ({
      setting: 'huoxin-filter.global_require_approval',
      label: String(app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_require_approval')),
      help: String(app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_require_approval_help')),
      type: 'boolean',
    }))
    .setting(() => ({
      setting: 'huoxin-filter.global_evasion_active',
      label: String(app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evasion_active')),
      help: String(app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evasion_active_help')),
      type: 'boolean',
    }))
    .setting(() => ({
      setting: 'huoxin-filter.global_evasion_timeout',
      label: String(app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evasion_timeout')),
      help: String(app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evasion_timeout_help')),
      type: 'number',
      min: 0,
    }))
    .setting(() => ({
      setting: 'huoxin-filter.global_evasion_threshold',
      label: String(app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evasion_threshold')),
      help: String(app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evasion_threshold_help')),
      type: 'number',
      min: 1,
    }))
    .setting(() => ({
      setting: 'huoxin-filter.global_evasion_log_keep_days',
      label: String(app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evasion_log_keep_days')),
      help: String(app.translator.trans('huoxin-filter-rule-manager.admin.settings.global_evasion_log_keep_days_help')),
      type: 'number',
      min: 1,
    })),
];

export default extenders;
