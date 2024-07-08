<?php

namespace App\DependencyInjection\CompilerPass;

use App\Business\CommandBusiness;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class DiscordCommandCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(CommandBusiness::class)) {
            return;
        }

        $commandBusinessDefinition = $container->getDefinition(CommandBusiness::class);
        $commands = $container->findTaggedServiceIds('app.discord.command');

        foreach ($commands as $id => $tags) {
            $commandBusinessDefinition->addMethodCall('addCommand', [new Reference($id)]);
        }
    }
}