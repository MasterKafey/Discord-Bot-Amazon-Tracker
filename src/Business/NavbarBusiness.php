<?php

namespace App\Business;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

readonly class NavbarBusiness
{
    public function __construct(
        private RequestStack    $requestStack,
        private RouterInterface $router,
        private Environment     $environment,
    )
    {

    }

    private
    function getItemConfiguration(): array
    {
        return [
            [
                'template' => 'Widget/Icon/health.html.twig',
                'text' => 'Statut',
                'path' => [
                    'route' => 'app_status_list',
                ],
                'active_routes' => [
                    'app_status_list',
                ]
            ],
            [
                'template' => 'Widget/Icon/limit_products.html.twig',
                'text' => "Configuration d'offres",
                'path' => [
                    'route' => 'app_offer_configuration_list'
                ],
                'active_routes' => [
                    'app_offer_configuration_list',
                    'app_offer_configuration_create',
                    'app_offer_configuration_update',
                    'app_offer_configuration_delete',
                ]
            ],
            [
                'template' => 'Widget/Icon/settings.html.twig',
                'text' => 'Paramètres',
                'path' => [
                    'route' => 'app_setting'
                ],
                'active_routes' => [
                    'app_setting',
                ]
            ],
        ];
    }

    public function getItems(): array
    {
        $currentRoute = $this->requestStack->getCurrentRequest()->attributes->get('_route');

        return array_map(function ($item) use ($currentRoute) {
            return [
                'content' => $this->environment->render($item['template']) . $item['text'],
                'url' => isset($item['path']['route']) ? $this->router->generate($item['path']['route']) : '#',
                'is_active' => in_array($currentRoute, $item['active_routes'] ?? [])
            ];
        }, $this->getItemConfiguration());

    }
}