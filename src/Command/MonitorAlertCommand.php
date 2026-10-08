<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Lo llama monitor.sh (cron del VPS) para avisar de una incidencia por el correo
 * de la propia app. El host no tiene MTA (ni mail/mailx/sendmail), así que el
 * script no puede avisar por sí solo; este comando reutiliza el SMTP ya
 * configurado en MAILER_DSN. Sale con FAILURE si el correo no sale para que el
 * script pueda reintentar en el siguiente ciclo.
 */
#[AsCommand(
    name: 'app:monitor:alert',
    description: 'Envía por correo un aviso del monitor del servidor (lo usa monitor.sh)',
)]
class MonitorAlertCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%app.email.from%')]
        private readonly string $from,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('subject', InputArgument::REQUIRED, 'Asunto')
            ->addArgument('body', InputArgument::REQUIRED, 'Cuerpo en texto plano')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Destinatario');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = (string) $input->getOption('to');

        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $io->error('Indica un destinatario válido con --to.');

            return Command::INVALID;
        }

        $email = (new Email())
            ->from(new Address($this->from))
            ->to($to)
            ->subject((string) $input->getArgument('subject'))
            ->text((string) $input->getArgument('body'));

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $io->error('No se pudo enviar el aviso: ' . $e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Aviso enviado a %s.', $to));

        return Command::SUCCESS;
    }
}
