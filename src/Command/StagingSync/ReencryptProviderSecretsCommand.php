<?php

namespace App\Command\StagingSync;

use App\Repository\SysConfigRepository;
use App\Service\SysConfigCipher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Paso de scripts/staging-sync-dispatch.sh: las credenciales de proveedor
 * (sys_config bajo provider.%) llegan en el dump cifradas con la
 * SYS_CONFIG_ENCRYPTION_KEY de prod, que esta instancia no puede descifrar
 * con la suya. Decision explicita (2026-08-04, no la de borrarlas): se
 * descifran con la clave de prod y se re-cifran con la clave local para que
 * queden funcionales en staging por ahora — pendiente un mecanismo mejor.
 *
 * La clave de prod llega SOLO por la variable de entorno PROD_SYS_CONFIG_KEY
 * de esta invocacion puntual (ver staging-sync-dispatch.sh, que la recibe
 * por stdin de la conexion SSH restringida, nunca a disco) y no se persiste
 * en ningun sitio mas alla de este proceso.
 */
#[AsCommand(
    name: 'app:staging-sync:reencrypt-provider-secrets',
    description: 'Re-cifra sys_config provider.% (copiadas del dump de prod) con la SYS_CONFIG_ENCRYPTION_KEY local',
)]
class ReencryptProviderSecretsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SysConfigRepository $sysConfigRepo,
        #[Autowire('%env(string:default::SYS_CONFIG_ENCRYPTION_KEY)%')]
        private readonly string $localKey = '',
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $prodKey = (string) getenv('PROD_SYS_CONFIG_KEY');
        if ($prodKey === '') {
            $io->error('PROD_SYS_CONFIG_KEY no esta definida en el entorno de esta invocacion.');

            return Command::INVALID;
        }

        if ($this->localKey === '') {
            $io->error('SYS_CONFIG_ENCRYPTION_KEY no esta configurada en esta instancia.');

            return Command::INVALID;
        }

        $count = 0;
        foreach ($this->sysConfigRepo->findBy(['isEncrypted' => true]) as $row) {
            if (!str_starts_with((string) $row->getPropertyName(), 'provider.')) {
                continue;
            }

            $plain = SysConfigCipher::decrypt((string) $row->getPropertyValue(), $prodKey);
            $row->setPropertyValue(SysConfigCipher::encrypt($plain, $this->localKey));
            $count++;
        }

        $this->em->flush();
        $this->sysConfigRepo->invalidateCache();

        $io->success("Re-encriptadas {$count} credenciales de proveedor con la clave local.");

        return Command::SUCCESS;
    }
}
