<?php declare(strict_types=1);

namespace App\Domain\Config;

interface ConfigRepository
{
    /**
     * @return Config[]
     */
    public function findAll(): array;

    public function findById(string $id): ?Config;

    public function save(Config $config): void;

    public function delete(string $id): void;
}
