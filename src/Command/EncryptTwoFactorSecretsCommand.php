<?php

namespace App\Command;

use App\Entity\User;
use App\Security\SecretCipher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:2fa:encrypt-secrets',
    description: 'Cifra en reposo los secretos TOTP que todavía están guardados en claro.',
)]
class EncryptTwoFactorSecretsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SecretCipher           $cipher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Muestra qué se cifraría sin escribir nada.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        /** @var User[] $users */
        $users = $this->em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.twoFactorSecret IS NOT NULL')
            ->getQuery()
            ->getResult();

        $pendientes = 0;

        foreach ($users as $user) {
            $stored = $user->getTwoFactorSecret();

            if ($stored === null || $this->cipher->isEncrypted($stored)) {
                continue;
            }

            $pendientes++;

            if (!$dryRun) {
                $user->setTwoFactorSecret($this->cipher->encrypt($stored));
            }
        }

        if ($pendientes === 0) {
            $io->success('No hay secretos en claro: todos están ya cifrados.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->note(sprintf('%d secreto(s) se cifrarían. Vuelve a ejecutar sin --dry-run.', $pendientes));

            return Command::SUCCESS;
        }

        $this->em->flush();
        $io->success(sprintf('%d secreto(s) cifrados.', $pendientes));

        return Command::SUCCESS;
    }
}
