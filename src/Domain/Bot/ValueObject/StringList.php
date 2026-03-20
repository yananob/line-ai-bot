<?php declare(strict_types=1);

namespace App\Domain\Bot\ValueObject;

class StringList
{
    /**
     * @param string[] $values
     */
    public function __construct(private array $values)
    {
    }

    public function isEmpty(): bool
    {
        return empty($this->values);
    }

    /**
     * @return string[]
     */
    public function toArray(): array
    {
        return $this->values;
    }

    public function format(): string
    {
        if ($this->isEmpty()) {
            return "";
        }
        return "・" . implode("\n・", $this->values);
    }

    public function merge(self $other): self
    {
        return new self(array_merge($this->values, $other->values));
    }
}
