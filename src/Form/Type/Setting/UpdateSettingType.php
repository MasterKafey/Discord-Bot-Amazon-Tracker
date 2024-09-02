<?php

namespace App\Form\Type\Setting;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class UpdateSettingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('partner_id', TextType::class, [
                'required' => false
            ])
            ->add('discord_token', TextType::class)
            ->add('review_warning', IntegerType::class, [
                'attr' => [
                    'min' => 0,
                ]
            ])
            ->add('rating_warning', NumberType::class, [
                'attr' => [
                    'min' => 0,
                ]
            ])
            ->add('min_reviews', IntegerType::class, [
                'attr' => [
                    'min' => 0,
                ]
            ])
            ->add('min_rating', NumberType::class, [
                'attr' => [
                    'min' => 0,
                ]
            ])
            ->add('min_sales', IntegerType::class, [
                'attr' => [
                    'min' => 0,
                ]
            ])
            ->add('minute_interval', IntegerType::class, [
                'attr' => [
                    'min' => 1,
                ]
            ])
            ->add('keepa_token', TextType::class)
            ->add('google_emoji_id', TextType::class, [
                'required' => false,
            ])
            ->add('amazon_emoji_id', TextType::class, [
                'required' => false,
            ])
            ->add('aliexpress_emoji_id', TextType::class, [
                'required' => false,
            ]);
    }
}