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

/**
 * @TODO: Remove this in favor of one of the API resource classes that were added.
 *      Or extend an existing API Resource to add this to.
 *      Or use a vanilla RequestHandlerInterface controller.
 *      @link https://docs.flarum.org/2.x/extend/api#endpoints
 */
class ListRulesetsController extends AbstractListController
{
    public $serializer = RulesetSerializer::class;

    protected function data(ServerRequestInterface $request, Document $document): iterable
    {
        RequestUtil::getActor($request)->assertAdmin();

        return Ruleset::ordered()->get();
    }
}
