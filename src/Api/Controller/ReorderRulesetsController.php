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
use Flarum\Foundation\ValidationException;
use Huoxin\FilterRuleManager\Model\Ruleset;
use Illuminate\Database\ConnectionInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
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
    public function __construct(protected ConnectionInterface $db, protected TranslatorInterface $translator)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $body = $request->getParsedBody();
        $ids = $body['data']['ids'] ?? [];

        $ids = array_values(array_filter($ids, fn ($id) => is_numeric($id) && (int) $id > 0));

        if (! empty($ids)) {
            $existingIds = Ruleset::whereIn('id', $ids)->pluck('id')->all();
            $missingIds = array_diff($ids, $existingIds);

            if (! empty($missingIds)) {
                $trans = $this->translator->trans('huoxin-filter-rule-manager.admin.validation.invalid_ruleset_ids');
                throw new ValidationException([
                    'ids' => is_array($trans) ? $trans[0] : $trans,
                ]);
            }

            $values = array_map(fn ($index, $id) => ['id' => $id, 'priority' => $index * 10], array_keys($ids), $ids);

            $this->db->transaction(function () use ($values) {
                Ruleset::upsert($values, ['id'], ['priority']);
            });
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
