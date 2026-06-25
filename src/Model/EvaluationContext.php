<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Model;

use Flarum\Post\Post;
use Flarum\User\User;

class EvaluationContext
{
    public function __construct(
        public string $content,
        public ?User $actor = null,
        public ?Post $post = null
    ) {
    }
}
