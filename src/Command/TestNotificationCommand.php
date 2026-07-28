<?php

declare(strict_types=1);

namespace App\Command;

use App\DTO\NotificationDraft;
use App\Entity\User;
use App\Enums\NotificationLevelEnum;
use App\Enums\NotificationTypeEnum;
use App\Service\NotificationCenterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test-notification',
    description: 'Emite una notificación in-app de prueba a un usuario, por email',
)]
class TestNotificationCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationCenterService $notificationCenter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email del usuario destinatario');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (string) $input->getArgument('email');
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        if ($user === null) {
            $output->writeln(sprintf('<error>No existe ningún usuario con el email %s</error>', $email));

            return Command::FAILURE;
        }

        $notification = $this->notificationCenter->notifyUser($user, new NotificationDraft(
            type: NotificationTypeEnum::CUSTOM,
            title: 'Notificación de prueba',
            level: NotificationLevelEnum::INFO,
            body: 'Esta es una notificación de prueba emitida por app:test-notification.',
            link: '/dashboards/finance',
        ));

        if ($notification === null) {
            $output->writeln('<error>No se pudo emitir la notificación (revisa los logs).</error>');

            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Notificación #%d emitida a %s</info>', $notification->getId(), $email));

        return Command::SUCCESS;
    }
}
