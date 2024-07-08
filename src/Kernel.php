<?php

namespace App;

use App\DependencyInjection\CompilerPass\DiscordCommandCompilerPass;
use App\DependencyInjection\CompilerPass\DiscordListenerCompilerPass;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new DiscordCommandCompilerPass());
        $container->addCompilerPass(new DiscordListenerCompilerPass());
    }
}
