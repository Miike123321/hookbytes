<?php

namespace Tests\Unit;

use App\Services\JsonSchemaValidationService;
use PHPUnit\Framework\TestCase;

class JsonSchemaValidationServiceTest extends TestCase
{
    public function test_it_validates_payload_and_returns_structured_russian_errors(): void
    {
        $service = new JsonSchemaValidationService;
        $schema = [
            'type' => 'object',
            'properties' => [
                'order' => [
                    'type' => 'object',
                    'properties' => ['id' => ['type' => 'integer']],
                    'required' => ['id'],
                ],
            ],
            'required' => ['order'],
        ];

        self::assertSame([], $service->errors((object) ['order' => (object) ['id' => 123]], $schema));

        $errors = $service->errors(
            (object) ['order' => (object) ['id' => 'не число', 'extra' => true]],
            $schema,
            true
        );

        self::assertNotEmpty($errors);
        self::assertContains('additionalProperties', array_column($errors, 'keyword'));
        self::assertContains('type', array_column($errors, 'keyword'));
        self::assertStringContainsString('поле', implode(' ', array_column($errors, 'message')));
    }
}
