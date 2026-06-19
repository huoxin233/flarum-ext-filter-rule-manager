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
use Huoxin\FilterRuleManager\Extend\FilterRuleProvider;
use Illuminate\Contracts\Container\Container;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Returns registered backend rule providers and their supported types.
 * Used by the admin rule builder to populate the type picker.
 */
class ListProvidersController implements RequestHandlerInterface
{
    public function __construct(protected Container $container)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        /** @var array<string, \Huoxin\FilterRuleManager\Provider\RuleProviderInterface> $providers */
        $providers = $this->container->make(FilterRuleProvider::REGISTRY_KEY);

        $result = [];
        foreach ($providers as $name => $provider) {
            $supportsTokens = method_exists($provider, 'getProvidedTokens');
            foreach ($provider->getSupportedBackendTypes() as $type) {
                $entry = [
                    'provider' => $name,
                    'type' => $type,
                    'label' => $provider->getBackendTypeLabels()[$type] ?? $type,
                    'scope' => 'backend',
                    'tokens' => $supportsTokens ? $provider->getProvidedTokens($type) : [],
                ];
                $result[] = $entry;
            }
        }

        return new JsonResponse(['data' => $result]);
    }
}
