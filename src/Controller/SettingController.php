<?php

namespace App\Controller;

use App\Business\ConfigBusiness;
use App\Form\Type\Setting\UpdateSettingType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: '/setting',
    name: 'app_setting'
)]
class SettingController extends AbstractController
{
    const KEYS = [
        'discord_token',
        'review_warning',
        'rating_warning',
        'partner_id',
        'min_reviews',
        'min_rating',
        'min_sales',
        'minute_interval',
        'keepa_token',
        'amazon_emoji_id',
        'google_emoji_id',
        'aliexpress_emoji_id',
    ];

    public function __invoke(
        ConfigBusiness $configBusiness,
        Request $request
    ): Response
    {
        $values = [];
        foreach (self::KEYS as $key) {
            $values[$key] = $configBusiness->get($key);
        }

        $form = $this->createForm(UpdateSettingType::class, $values)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($form->getData() as $key => $value) {
                $configBusiness->set($key, $value);
            }

            return $this->redirectToRoute('app_setting');
        }

        return $this->render('Page/Setting/update.html.twig', [
            'form' => $form->createView()
        ]);
    }
}