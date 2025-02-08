<?php

namespace App\MessageHandler;

use App\Message\ForgotPasswordMessage;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ForgotPasswordMessageHandler
{
    public function __construct(private readonly MailerInterface $mailer, private readonly ParameterBagInterface $parameterBag)
    {
    }
    public function __invoke(ForgotPasswordMessage $message)
    {

    }
}