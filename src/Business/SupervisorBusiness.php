<?php

namespace App\Business;

use fXmlRpc\Client;
use fXmlRpc\Transport\PsrTransport;
use GuzzleHttp\Psr7\HttpFactory;
use Supervisor\Exception\Fault\NotRunningException;
use Supervisor\ProcessInterface;
use Supervisor\Supervisor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SupervisorBusiness
{
    private readonly Supervisor $supervisor;

    public function __construct(
        #[Autowire(env: 'APP_SUPERVISORD_URL')]
        string $supervisorUrl
    )
    {
        $this->supervisor = new Supervisor(
            new Client(
                $supervisorUrl,
                new PsrTransport(
                    new HttpFactory(),
                    new \GuzzleHttp\Client([
                            'auth' => [
                                'supervisoruser',
                                'supervisorpass'
                            ]
                        ]
                    )
                )
            )
        );
    }

    public function getProcess(string $name): ProcessInterface
    {
        return $this->supervisor->getProcess($name);
    }

    public function restartProcess(string $name): void
    {
        try {
            $this->supervisor->stopProcess($name);
        } catch (NotRunningException) {}
        $this->supervisor->startProcess($name, false);
    }
}