<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/email')]
class AdminEmailTestController extends AbstractController
{
    public function __construct(private readonly MailerInterface $mailer, private readonly ParameterBagInterface $parameterBag)
    {

    }

    /**
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */
    #[Route('/test')]
    public function index(): JsonResponse
    {
        $mail = (new Email())
            ->from(new Address(
                $this->parameterBag->get('app.email.from'),
                'No Reply'
            ))->to(new Address(
                'alexander.afonsecac@gmail.com',
                'Alexander Fonseca'
            ))
            ->subject('Test')
            ->text('Test')
            ->html('<p>Test</p>');


        $this->mailer->send($mail);
        return $this->json([
            'success' => true,
        ]);
    }
}
