/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import app from 'flarum/common/app';

app.initializers.add('huoxin/filter-rule-manager', () => {
  console.log('[huoxin/filter-rule-manager] Hello, forum and admin!');
});
