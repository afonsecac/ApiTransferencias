<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Persistence;

use App\Market\Catalogo\Domain\Category;
use App\Market\Catalogo\Domain\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineCategoryRepository implements CategoryRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function all(): array
    {
        /** @var list<Category> $categories */
        $categories = $this->em->getRepository(Category::class)->findBy([], ['id' => 'ASC']);

        return $categories;
    }

    public function findById(int $id): ?Category
    {
        return $this->em->find(Category::class, $id);
    }

    public function findBySlug(string $slug): ?Category
    {
        return $this->em->getRepository(Category::class)->findOneBy(['slug' => $slug]);
    }

    public function save(Category $category): void
    {
        $this->em->persist($category);
        $this->em->flush();
    }
}
