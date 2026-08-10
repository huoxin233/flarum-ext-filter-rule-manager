/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import app from 'flarum/admin/app';

import filterEngine from '../common/FilterEngine';
import BuiltinProvider from './providers/BuiltinProvider';
import BuiltinTemplate from '../common/components/BuiltinTemplate';
import BuiltinTemplateSettings from './components/BuiltinTemplateSettings';
import stripMentions from '../common/modifiers/stripMentions';
import stripUploadTags from '../common/modifiers/stripUploadTags';
import stripImages from '../common/modifiers/stripImages';
import stripUrls from '../common/modifiers/stripUrls';

export { default as extend } from './extend';

app.initializers.add('huoxin/filter-rule-manager', () => {
  app.filterRuleManager = filterEngine;

  filterEngine.registerDisplayMode('none', 'huoxin-filter-rule-manager.admin.displays.none');
  filterEngine.registerDisplayMode('banner', 'huoxin-filter-rule-manager.admin.displays.banner');
  filterEngine.registerDisplayMode('header_banner', 'huoxin-filter-rule-manager.admin.displays.header_banner');
  filterEngine.registerDisplayMode('sidebar', 'huoxin-filter-rule-manager.admin.displays.sidebar');
  filterEngine.registerDisplayMode('toast', 'huoxin-filter-rule-manager.admin.displays.toast');
  filterEngine.registerDisplayMode('modal', 'huoxin-filter-rule-manager.admin.displays.modal');

  filterEngine.registerGlobalToken('rule_name', 'huoxin-filter-rule-manager.admin.token_rule_name_desc');

  filterEngine.registerProvider('builtin', new BuiltinProvider() as any);
  filterEngine.registerTemplate('builtin', BuiltinTemplate as any, BuiltinTemplateSettings as any);

  filterEngine.registerModifier('strip_mentions', stripMentions);
  filterEngine.registerModifier('strip_upload_tags', stripUploadTags);
  filterEngine.registerModifier('strip_images', stripImages);
  filterEngine.registerModifier('strip_urls', stripUrls);

  /*
    // Temporarily removed until Flarum natively supports a "Nobody" permission.
    // Currently, Flarum Admins inherently bypass all permissions.
    .registerPermission(
      {
        icon: 'fas fa-shield-alt',
        label: app.translator.trans('huoxin-filter-rule-manager.admin.permissions.bypass_all_rules'),
        permission: 'huoxin-filter-rule-manager.bypassAllRules',
      },
      'moderate'
    );
    */
});
