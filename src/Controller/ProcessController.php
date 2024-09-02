<?php

namespace App\Controller;

use App\Business\SupervisorBusiness;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/status')]
class ProcessController extends AbstractController
{
    #[Route(
        path: '/',
        name: 'app_status_list'
    )]
    public function listProcess(
        SupervisorBusiness $supervisorBusiness
    ): Response
    {
        $botProcess = $supervisorBusiness->getProcess('bot');
        $cronProcess = $supervisorBusiness->getProcess('cron');

        return $this->render('Page/Process/list.html.twig', [
            'bot_process' => $botProcess,
            'cron_process' => $cronProcess
        ]);
    }

    #[Route(
        path: '/{process}/restart',
        name: 'app_status_process_restart',
        requirements: [
            'process' => 'bot|cron',
        ]
    )]
    public function restartProcess(
        SupervisorBusiness $supervisorBusiness,
        string             $process
    ): RedirectResponse
    {
        $supervisorBusiness->restartProcess($process);

        return $this->redirectToRoute('app_status_list');
    }
}