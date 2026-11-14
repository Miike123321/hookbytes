<?php

namespace App\Services;

use Opis\JsonSchema\Validator;
use stdClass;

class JsonSchemaValidationService
{
    public function errors(mixed $payload, array $schema, bool $strict = false): array
    {
        if ($strict) {
            $schema = $this->strictSchema($schema);
        }

        $validator = new Validator(null, 100, false);
        $result = $validator->validate($payload, $this->schemaObject($schema));

        if ($result->isValid()) {
            return [];
        }

        return $this->flattenError($result->error());
    }

    public function assertValidSchema(array $schema, bool $strict = false): void
    {
        if ($strict) {
            $schema = $this->strictSchema($schema);
        }

        (new Validator(null, 100, false))->validate(new stdClass, $this->schemaObject($schema));
    }

    private function schemaObject(array $schema): object
    {
        return json_decode(
            json_encode($schema, JSON_THROW_ON_ERROR),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private function strictSchema(array $schema): array
    {
        $type = $schema['type'] ?? null;
        $isObject = $type === 'object'
            || (is_array($type) && in_array('object', $type, true))
            || array_key_exists('properties', $schema);

        if ($isObject) {
            $schema['additionalProperties'] = false;
        }

        foreach (['properties', 'patternProperties', '$defs', 'definitions'] as $keyword) {
            if (isset($schema[$keyword]) && is_array($schema[$keyword])) {
                foreach ($schema[$keyword] as $name => $child) {
                    if (is_array($child)) {
                        $schema[$keyword][$name] = $this->strictSchema($child);
                    }
                }
            }
        }

        foreach (['items', 'contains', 'not', 'if', 'then', 'else', 'propertyNames'] as $keyword) {
            if (isset($schema[$keyword]) && is_array($schema[$keyword])) {
                $schema[$keyword] = $this->strictSchema($schema[$keyword]);
            }
        }

        foreach (['allOf', 'anyOf', 'oneOf', 'prefixItems'] as $keyword) {
            if (isset($schema[$keyword]) && is_array($schema[$keyword])) {
                foreach ($schema[$keyword] as $index => $child) {
                    if (is_array($child)) {
                        $schema[$keyword][$index] = $this->strictSchema($child);
                    }
                }
            }
        }

        return $schema;
    }

    private function flattenError(?\Opis\JsonSchema\Errors\ValidationError $error): array
    {
        if ($error === null) {
            return [];
        }

        $errors = [];
        if ($error->subErrors() === []) {
            $path = implode('/', array_map(
                static fn ($segment): string => str_replace(['~', '/'], ['~0', '~1'], (string) $segment),
                $error->data()->fullPath()
            ));

            $errors[] = [
                'path' => $path === '' ? '#' : '#/'.$path,
                'keyword' => $error->keyword(),
                'message' => $this->localizedMessage($error->keyword()),
            ];
        }

        foreach ($error->subErrors() as $subError) {
            array_push($errors, ...$this->flattenError($subError));
        }

        return $errors;
    }

    private function localizedMessage(string $keyword): string
    {
        return match ($keyword) {
            'required' => 'Отсутствует обязательное поле.',
            'type' => 'Значение имеет неверный тип.',
            'additionalProperties' => 'Обнаружено неизвестное поле.',
            'enum', 'const' => 'Значение не входит в список допустимых.',
            'format' => 'Значение имеет неверный формат.',
            'minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum' => 'Числовое значение выходит за допустимые границы.',
            'minLength', 'maxLength', 'pattern' => 'Строка не соответствует ограничениям.',
            'minItems', 'maxItems', 'uniqueItems' => 'Список не соответствует ограничениям.',
            default => 'Значение не соответствует JSON Schema.',
        };
    }
}
