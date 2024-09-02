<?php

namespace App\Controller;

use App\Entity\OfferConfiguration;
use App\Form\Type\ConfirmType;
use App\Form\Type\OfferConfiguration\OfferConfigurationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: '/offer-configuration',
)]
class OfferConfigurationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    )
    {

    }

    #[Route(
        path: '/create',
        name: 'app_offer_configuration_create'
    )]
    public function create(
        Request $request
    ): Response
    {
        $offerConfiguration = new OfferConfiguration();
        $form = $this->createForm(OfferConfigurationType::class, $offerConfiguration)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($offerConfiguration);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_offer_configuration_list');
        }

        return $this->render('Page/OfferConfiguration/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route(
        path: '/',
        name: 'app_offer_configuration_list',
    )]
    public function list(): Response
    {
        $offerConfigurations = $this->entityManager->getRepository(OfferConfiguration::class)->findAll();

        return $this->render('Page/OfferConfiguration/list.html.twig', [
            'offer_configurations' => $offerConfigurations,
        ]);
    }

    #[Route(
        path: '/update/{id}',
        name: 'app_offer_configuration_update',
        requirements: [
            'id' => '\d+'
        ]
    )]
    public function update(OfferConfiguration $offerConfiguration, Request $request): Response
    {
        $form = $this->createForm(OfferConfigurationType::class, $offerConfiguration)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            return $this->redirectToRoute('app_offer_configuration_list');
        }

        return $this->render('Page/OfferConfiguration/update.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route(
        path: '/delete/{id}',
        name: 'app_offer_configuration_delete',
        requirements: [
            'id' => '\d+'
        ]
    )]
    public function delete(Request $request, OfferConfiguration $offerConfiguration): Response
    {
        $form = $this->createForm(ConfirmType::class)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->remove($offerConfiguration);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_offer_configuration_list');
        }

        return $this->render('Page/OfferConfiguration/delete.html.twig', [
            'form' => $form->createView()
        ]);
    }
}