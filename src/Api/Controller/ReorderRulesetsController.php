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

use Flarum\Http\RequestUtil;
use Huoxin\FilterRuleManager\Model\Ruleset;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Accepts an ordered list of ruleset IDs and updates each priority accordingly.
 *
 * Request body: { "data": { "ids": [3, 1, 5, 2] } }
 * Priorities are set 0, 10, 20, 30 ... (multiples of 10 for easy insertion).
 */
class ReorderRulesetsController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $body = $request->getParsedBody();
        $ids = $body['data']['ids'] ?? [];

        if (! empty($ids)) {
            Ruleset::getConnection()->transaction(function () use ($ids) {
                foreach ($ids as $index => $id) {
                    Ruleset::where('id', $id)->update(['priority' => $index * 10]);
                }
            });
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
