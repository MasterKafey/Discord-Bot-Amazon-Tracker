<?php

namespace App\DependencyInjection\CompilerPass;

use App\Business\ListenerBusiness;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class DiscordListenerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ListenerBusiness::class)) {
            return;
        }

        $commandBusinessDefinition = $container->getDefinition(ListenerBusiness::class);
        $commands = $container->findTaggedServiceIds('app.discord.listener');

        foreach ($commands as $id => $tags) {
            $commandBusinessDefinition->addMethodCall('addListener', [new Reference($id)]);
        }
    }
}