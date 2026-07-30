<?php

declare(strict_types=1);

namespace App\DTOs;

final class TerrenoImportValidationErrors
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    public function add(string $field, string $message): void
    {
        $this->errors[$field] ??= [];
        $this->errors[$field][] = $message;
    }

    /** @param array<string, array<string>> $errors */
    public function merge(array $errors): void
    {
        foreach ($errors as $field => $messages) {
            foreach (array_values($messages) as $message) {
                $this->add($field, $message);
            }
        }
    }

    public function isEmpty(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string, list<string>> */
    public function all(): array
    {
        return $this->errors;
    }
}
