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
    name: 'app:test-account-activation-email',
    description: 'Send a test account activation email (comremit or sendmundo)',
)]
class TestAccountActivationEmailCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly ParameterBagInterface $parameterBag,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('brand', InputArgument::OPTIONAL, 'comremit or sendmundo', 'comremit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $brand = strtolower($input->getArgument('brand'));
        $baseUrl = $this->parameterBag->get('app.dashboard.url.' . $brand);
        $activationUrl = $baseUrl . '/activate?email=' . urlencode('alexander.afonsecac@gmail.com');

        $mail = (new TemplatedEmail())
            ->from(new Address($this->parameterBag->get('app.email.from'), 'Support Account'))
            ->to(new Address('alexander.afonsecac@gmail.com', 'Alexander Fonseca'))
            ->subject(sprintf('Activa tu cuenta / Activate your account - %s', ucfirst($brand)))
            ->priority(Email::PRIORITY_HIGH)
            ->htmlTemplate(sprintf('emails/account/activation.%s.html.twig', $brand))
            ->context([
                'code'          => 'A7X3K9',
                'activationUrl' => $activationUrl,
                'firstName'     => 'Alexander',
                'email'         => 'alexander.afonsecac@gmail.com',
            ])
            ->text('Your activation code is: A7X3K9. Activate here: ' . $activationUrl);

        $this->mailer->send($mail);
        $output->writeln(sprintf('<info>Account activation email (%s) sent to alexander.afonsecac@gmail.com</info>', $brand));

        return Command::SUCCESS;
    }
}
