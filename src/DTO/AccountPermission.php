<?php

namespace App\DTO;

use App\Entity\Account;
use App\Entity\Environment;
use Symfony\Component\Serializer\Attribute\Groups;

class AccountPermission
{
    #[Groups(['accounts:read'])]
    private array $accounts;
    #[Groups(['accounts:read'])]
    private array $environments;
    #[Groups(['accounts:read'])]
    private ?array $clients;
    #[Groups(['accounts:read'])]
    private ?Environment $environment;
    #[Groups(['accounts:read'])]
    private ?Account $account = null;

    /**
     * @param array $accounts
     * @param array $environments
     * @param array|null $clients
     * @param \App\Entity\Environment|null $environment
     * @param \App\Entity\Account|null $account
     */
    public function __construct(
        array $accounts,
        array $environments,
        ?array $clients,
        ?Environment $environment,
        ?Account $account
    ) {
        $this->accounts = $accounts;
        $this->environments = $environments;
        $this->clients = $clients;
        $this->environment = $environment;
        $this->account = $account;
    }


    /**
     * @return \App\Entity\Account[]
     */
    public function getAccounts(): array
    {
        return $this->accounts;
    }

    public function setAccounts(array $accounts): void
    {
        $this->accounts = $accounts;
    }

    /**
     * @return \App\Entity\Environment[]
     */
    public function getEnvironments(): array
    {
        return $this->environments;
    }

    public function setEnvironments(array $environments): void
    {
        $this->environments = $environments;
    }

    public function getEnvironment(): ?Environment
    {
        return $this->environment;
    }

    public function setEnvironment(?Environment $environment): void
    {
        $this->environment = $environment;
    }

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(?Account $account): void
    {
        $this->account = $account;
    }

    public function getClients(): ?array
    {
        return $this->clients;
    }

    public function setClients(?array $clients): void
    {
        $this->clients = $clients;
    }


}