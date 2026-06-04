<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Api\Controller;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Huoxin\FilterRuleManager\Api\Serializer\RulesetSerializer;
use Huoxin\FilterRuleManager\Model\Ruleset;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ListRulesetsController extends AbstractListController
{
    public $serializer = RulesetSerializer::class;

    protected function data(ServerRequestInterface $request, Document $document): iterable
    {
        RequestUtil::getActor($request)->assertAdmin();

        return Ruleset::with('rules')->ordered()->get();
    }
}
