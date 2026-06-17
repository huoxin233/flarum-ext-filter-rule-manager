/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import 'flarum/forum/ForumApplication';
import 'flarum/admin/AdminApplication';
import type FilterEngine from '../common/FilterEngine';
import type FilterRulePopupDispatcher from '../forum/FilterRulePopupDispatcher';

declare module 'flarum/forum/ForumApplication' {
  export default interface ForumApplication {
    filterRuleManager?: FilterEngine;
    filterRulePopupDispatcher?: FilterRulePopupDispatcher;
    requestErrorCatch(e: unknown): void;
  }
}

declare module 'flarum/admin/AdminApplication' {
  export default interface AdminApplication {
    filterRuleManager?: FilterEngine;
  }
}
