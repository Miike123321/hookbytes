<?php

namespace Tests\Unit;

use App\Services\PayloadTransformerService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PayloadTransformerServiceTest extends TestCase
{
    public function test_it_maps_paths_casts_values_and_applies_defaults_in_order(): void
    {
        $service = new PayloadTransformerService;

        $result = $service->transform([
            'order' => ['id' => '42', 'total' => '19.95'],
            'enabled' => 'true',
        ], [
            ['source_path' => '$.order.id', 'target_path' => 'order.id', 'cast_type' => 'int'],
            ['source_path' => 'order.total', 'target_path' => 'amount', 'cast_type' => 'float'],
            ['source_path' => 'enabled', 'target_path' => 'active', 'cast_type' => 'bool'],
            ['source_path' => 'missing.value', 'target_path' => 'currency', 'cast_type' => 'string', 'default_value' => 'USD'],
        ]);

        self::assertSame([
            'order' => ['id' => 42],
            'amount' => 19.95,
            'active' => true,
            'currency' => 'USD',
        ], $result);
    }

    public function test_it_rejects_missing_required_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Не найдено обязательное поле');

        (new PayloadTransformerService)->transform([], [[
            'source_path' => 'order.id',
            'target_path' => 'id',
            'cast_type' => 'int',
            'is_required' => true,
        ]]);
    }
}
