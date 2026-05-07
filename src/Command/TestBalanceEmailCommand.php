<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:test-balance-email',
    description: 'Send a test balance email (CRITICAL or RISK)',
)]
class TestBalanceEmailCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly ParameterBagInterface $parameterBag,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('status', InputArgument::OPTIONAL, 'CRITICAL or RISK', 'CRITICAL');
        $this->addArgument('brand', InputArgument::OPTIONAL, 'comremit or sendmundo', 'comremit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $status = strtoupper($input->getArgument('status'));
        $brand  = strtolower($input->getArgument('brand'));

        $isCritical = $status === 'CRITICAL';

        $mail = (new TemplatedEmail())
            ->from(new Address($this->parameterBag->get('app.email.from'), 'Support Account'))
            ->to(new Address('alexander.afonsecac@gmail.com', 'Alexander Fonseca'))
            ->subject(sprintf('[%s] Account balance information', $status))
            ->priority($isCritical ? Email::PRIORITY_HIGHEST : Email::PRIORITY_HIGH)
            ->htmlTemplate(sprintf('emails/balance/balance.%s.html.twig', $brand))
            ->context([
                'balance'   => $isCritical ? 150.75 : 523.40,
                'currency'  => 'EUR',
                'name'      => 'Alexander Fonseca',
                'status_es' => $isCritical ? 'CRITICO' : 'RIESGO',
                'status_en' => $status,
                'mail'      => $brand === 'comremit' ? 'administrador@comremit.com' : 'support@sendmundo.com',
                'platform'  => 'PROD',
            ])
            ->text(sprintf('%s balance notification', $status));

        $this->mailer->send($mail);
        $output->writeln(sprintf('<info>%s balance email (%s) sent to alexander.afonsecac@gmail.com</info>', $status, $brand));

        return Command::SUCCESS;
    }
}
