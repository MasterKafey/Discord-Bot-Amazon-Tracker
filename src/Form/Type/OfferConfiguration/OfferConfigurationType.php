<?php

namespace App\Form\Type\OfferConfiguration;

use App\Entity\OfferConfiguration;
use App\Form\DataTransformer\ArrayToStringTransformer;
use App\Form\Type\ToggleType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OfferConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('domain', ChoiceType::class, [
                'choices' => OfferConfiguration::DOMAINS,
                'empty_data' => OfferConfiguration::DOMAINS['FR'],
            ])
            ->add('isPremium', ToggleType::class, [
                'required' => false,
            ])
            ->add('channelId', TextType::class)
            ->add('minPercentage', IntegerType::class, [
                'attr' => [
                    'min' => 0,
                    'max' => 100,
                ],
            ])
            ->add('maxPercentage', IntegerType::class, [
                'attr' => [
                    'min' => 0,
                    'max' => 100,
                ],
            ])
            ->add('minimumPrice', NumberType::class)
            ->add('weekAverage', ToggleType::class)
            ->add('categories', TextareaType::class, [
                'required' => false
            ])
        ;

        $builder->get('categories')->addModelTransformer(new ArrayToStringTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OfferConfiguration::class,
        ]);
    }
}