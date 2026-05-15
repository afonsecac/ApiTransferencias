<?php

namespace App\Repository;

use App\Entity\SysConfig;
use App\Service\SysConfigCipher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @extends ServiceEntityRepository<SysConfig>
 *
 * @method SysConfig|null find($id, $lockMode = null, $lockVersion = null)
 * @method SysConfig|null findOneBy(array $criteria, array $orderBy = null)
 * @method SysConfig[]    findAll()
 * @method SysConfig[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SysConfigRepository extends ServiceEntityRepository
{
    private const CACHE_TAG = 'sys_config';

    public function __construct(
        ManagerRegistry $registry,
        #[Autowire(service: 'cache.sys_config')]
        private readonly TagAwareCacheInterface $cache,
        #[Autowire('%env(string:default::SYS_CONFIG_ENCRYPTION_KEY)%')]
        private readonly string $encryptionKey = '',
    ) {
        parent::__construct($registry, SysConfig::class);
    }

    /**
     * Devuelve el propertyValue de una configuración, usando caché.
     * Si el valor está cifrado, lo descifra antes de devolver (y cachea el valor descifrado).
     * Para lecturas de escritura (update de la entidad), usar findOneBy() directamente.
     */
    public function findCachedValue(string $propertyName, bool $mustBeActive = false): ?string
    {
        $key = 'sc_' . ($mustBeActive ? 'a_' : '') . md5($propertyName);

        return $this->cache->get($key, function (ItemInterface $item) use ($propertyName, $mustBeActive) {
            $item->tag([self::CACHE_TAG]);
            $criteria = ['propertyName' => $propertyName];
            if ($mustBeActive) {
                $criteria['isActive'] = true;
            }
            $config = $this->findOneBy($criteria);
            if ($config === null) {
                return null;
            }
            $value = $config->getPropertyValue();
            if ($config->isEncrypted() && $value !== null && $this->encryptionKey !== '') {
                $value = SysConfigCipher::decrypt($value, $this->encryptionKey);
            }
            return $value;
        });
    }

    public function invalidateCache(): void
    {
        $this->cache->invalidateTags([self::CACHE_TAG]);
    }
}
