<?php

namespace App\Form\Type\Authentication;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;

class LoginType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('_username', EmailType::class)
            ->add('_password', PasswordType::class)
            ->add('_remember_me', CheckboxType::class, [
                'required' => false,
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}