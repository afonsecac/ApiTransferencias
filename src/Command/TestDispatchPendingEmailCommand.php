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
    name: 'app:test-dispatch-pending-email',
    description: 'Envía un correo de prueba del resumen de despacho de pendientes',
)]
class TestDispatchPendingEmailCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly ParameterBagInterface $parameterBag,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $from = $this->parameterBag->get('app.email.from');

        $triggeredBy = 'afonsecac1984@gmail.com';

        $mail = (new TemplatedEmail())
            ->from(new Address($from, 'Sistema — Comremit'))
            ->to(new Address($triggeredBy))
            ->cc(
                new Address('alexander.afonsecac@gmail.com', 'A. Fonseca'),
                new Address('aportela7@gmail.com', 'A. Portela'),
            )
            ->priority(Email::PRIORITY_HIGH)
            ->subject('[Dispatch] 3 mensaje(s) encolado(s) al API de comunicaciones — TEST')
            ->htmlTemplate('emails/communications/dispatch-pending.html.twig')
            ->context([
                'recharges'      => 2,
                'packages'       => 1,
                'total'          => 3,
                'dispatchedAt'   => (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM),
                'triggeredBy'    => $triggeredBy,
                'transactionIds' => ['TXN-001-TEST', 'TXN-002-TEST', 'TXN-003-TEST'],
            ]);

        $this->mailer->send($mail);
        $output->writeln(sprintf('<info>Correo de prueba de dispatch enviado a %s (CC: alexander.afonsecac@gmail.com, aportela7@gmail.com)</info>', $triggeredBy));

        return Command::SUCCESS;
    }
}
