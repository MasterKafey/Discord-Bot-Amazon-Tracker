<?php

namespace App\Mailer;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class Mailer
{
    public function __construct(
        private readonly Environment           $environment,
        private readonly MailerInterface       $mailer,
        private readonly ParameterBagInterface $parameterBag,
    )
    {

    }

    public function send(Mail $mail): void
    {
        $context = $mail->getContext();
        $template = $this->environment->load($mail->getTwigTemplate());
        $html = $template->renderBlock('html', $context);
        $text = $template->renderBlock('text', $context);
        $subject = $template->renderBlock('subject', $context);

        $email = (new Email())
            ->from($mail->getFrom() ?? $this->parameterBag->get('mailer_default_from'))
            ->to($mail->getTo())
            ->subject($subject)
            ->text($text)
            ->html($html);

        $this->mailer->send($email);
    }
}