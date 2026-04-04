<?php declare(strict_types=1);

namespace App\Domain\Config;

class Config
{
    public function __construct(
        private string $id,
        private array $data
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }
}
