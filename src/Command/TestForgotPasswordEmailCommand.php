<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:test-forgot-password-email',
    description: 'Send a test forgot password email',
)]
class TestForgotPasswordEmailCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly ParameterBagInterface $parameterBag,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mail = (new TemplatedEmail())
            ->from(new Address($this->parameterBag->get('app.email.from'), 'Support Account'))
            ->to(new Address('alexander.afonsecac@gmail.com', 'Alexander Fonseca'))
            ->subject('Password Reset - Comremit')
            ->priority(Email::PRIORITY_HIGH)
            ->htmlTemplate('emails/password/forgot-password.comremit.html.twig')
            ->context([
                'code' => 'A7X3K9',
            ])
            ->text('Your password reset code is: A7X3K9');

        $this->mailer->send($mail);
        $output->writeln('<info>Forgot password email sent to alexander.afonsecac@gmail.com</info>');

        return Command::SUCCESS;
    }
}
