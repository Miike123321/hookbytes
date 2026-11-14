<?php

namespace App\Services;

use Illuminate\Support\Str;
use InvalidArgumentException;
use stdClass;

class PayloadTransformerService
{
    public function transform(array $payload, array $rules): array
    {
        $result = [];
        $missing = new stdClass;

        foreach ($rules as $rule) {
            $source = $this->normalizePath((string) ($rule['source_path'] ?? ''));
            $target = $this->normalizePath((string) ($rule['target_path'] ?? ''));

            if ($source === '' || $target === '') {
                throw new InvalidArgumentException('Путь источника и назначения не может быть пустым.');
            }

            $value = data_get($payload, $source, $missing);
            if ($value === $missing || $value === null) {
                if (($rule['default_value'] ?? null) !== null) {
                    $value = $rule['default_value'];
                } elseif (! empty($rule['is_required'])) {
                    throw new InvalidArgumentException("Не найдено обязательное поле «{$source}».");
                } else {
                    continue;
                }
            }

            data_set($result, $target, $this->cast($value, (string) ($rule['cast_type'] ?? 'raw')));
        }

        return $result;
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'string' => is_scalar($value) ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR),
            'int' => filter_var($value, FILTER_VALIDATE_INT) !== false
                ? (int) $value
                : throw new InvalidArgumentException('Невозможно привести значение к целому числу.'),
            'float' => is_numeric($value)
                ? (float) $value
                : throw new InvalidArgumentException('Невозможно привести значение к числу.'),
            'bool' => $this->toBoolean($value),
            'array' => $this->toArray($value),
            'raw' => $value,
            default => throw new InvalidArgumentException('Неизвестный тип приведения значения.'),
        };
    }

    private function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $result = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($result === null) {
            throw new InvalidArgumentException('Невозможно привести значение к логическому типу.');
        }

        return $result;
    }

    private function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new InvalidArgumentException('Значение не является JSON-массивом или объектом.');
    }

    private function normalizePath(string $path): string
    {
        return Str::startsWith($path, '$.') ? substr($path, 2) : ltrim($path, '$');
    }
}
