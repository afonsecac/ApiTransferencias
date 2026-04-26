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
    name: 'app:test-balance-email',
    description: 'Send a test critical balance email',
)]
class TestBalanceEmailCommand extends Command
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
            ->subject('[CRITICAL] Account balance information')
            ->priority(Email::PRIORITY_HIGHEST)
            ->htmlTemplate('emails/balance/balance.comremit.html.twig')
            ->context([
                'balance' => 150.75,
                'currency' => 'EUR',
                'name' => 'Alexander Fonseca',
                'status_es' => 'CRITICO',
                'status_en' => 'CRITICAL',
                'mail' => 'administrador@comremit.com',
                'platform' => 'Comremit',
            ])
            ->text('Critical balance notification');

        $this->mailer->send($mail);
        $output->writeln('<info>Critical balance email sent to alexander.afonsecac@gmail.com</info>');

        return Command::SUCCESS;
    }
}
