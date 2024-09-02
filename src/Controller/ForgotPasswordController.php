<?php

namespace App\Controller;

use App\Business\TokenBusiness;
use App\Business\UserBusiness;
use App\Entity\Token;
use App\Entity\TokenType;
use App\Entity\User;
use App\Form\Model\ForgotPassword\RequestModel;
use App\Form\Type\ForgotPassword\RequestType;
use App\Form\Type\ForgotPassword\SubmitType;
use App\Mailer\Mail;
use App\Mailer\Mailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/forgot-password')]
class ForgotPasswordController extends AbstractController
{
    #[Route('/request', 'app_forgot_password_request')]
    public function request(
        Request                $request,
        TokenBusiness          $tokenBusiness,
        EntityManagerInterface $entityManager,
        Mailer                 $mailer,
    ): Response
    {
        $model = new RequestModel();
        $form = $this->createForm(RequestType::class, $model);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $model->getEmail()]);
            $token = $tokenBusiness->createNewToken(TokenType::ForgotPassword, $user);
            $entityManager->persist($token);
            $entityManager->flush();

            $mailer->send(
                (new Mail())
                    ->setTo($user->getEmail())
                    ->setTwigTemplate('Mail/ForgotPassword/request.html.twig')
                    ->setContext([
                        'token' => $token->getValue(),
                    ])
            );

            return $this->render('Page/ForgotPassword/request.html.twig', [
                'form' => $form->createView(),
                'submitted' => true,
            ]);
        }

        return $this->render('Page/ForgotPassword/request.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/submit/{value}', 'app_forgot_password_submit')]
    public function submit(
        Request $request,
        EntityManagerInterface $entityManager,
        string $value,
        UserBusiness $userBusiness,
        Security $security
    ): Response
    {
        $token = $entityManager->getRepository(Token::class)->findValidToken($value);

        if (null === $token) {
            throw new NotFoundHttpException();
        }
        $user = $token->getUser();
        $form = $this->createForm(SubmitType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userBusiness->hashPassword($user);
            $entityManager->remove($token);
            $entityManager->persist($user);
            $entityManager->flush();
            $security->login($user, 'remember_me');

            return $this->redirectToRoute('app_home_index');
        }

        return $this->render('Page/ForgotPassword/submit.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}