<?php declare(strict_types=1);

namespace MyApp\Domain\Bot\Trigger;

interface Trigger
{
    public function getId(): ?string;
    public function setId(string $id): void;
    public function getEvent(): string;
    public function getRequest(): string;
    public function toArray(): array;
}
