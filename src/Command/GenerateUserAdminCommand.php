<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Service\UserService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:generate_user_admin', description: 'Hello PhpStorm')]
class GenerateUserAdminCommand extends Command
{
    public function __construct(
        private readonly UserService $userService,
    )
    {
        parent::__construct();
    }

    public function configure(): void
    {
        $this->addOption('execute-configure-option',null, InputOption::VALUE_NONE, 'Execute admin user');
        $this->addArgument('firstName', InputOption::VALUE_REQUIRED, 'Name of user');
        $this->addArgument('lastName', InputOption::VALUE_REQUIRED, 'Lastname of user');
        $this->addArgument('email', InputOption::VALUE_REQUIRED, 'Email');
        $this->addArgument('password', InputOption::VALUE_REQUIRED, 'Password');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->note('Execute configure products');
        try {
            $user = new User();
            $user->setEmail($input->getArgument('email'));
            $user->setPassword($input->getArgument('password'));
            $user->setFirstName($input->getArgument('firstName'));
            $user->setLastName($input->getArgument('lastName'));
            $user->setActive(true);
            $user->setIsCheckValidation(true);
            $user->setRoles(['ROLE_SUPER_ADMIN']);
            $this->userService->createUser($user);
            $io->success('User admin created');
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
}
