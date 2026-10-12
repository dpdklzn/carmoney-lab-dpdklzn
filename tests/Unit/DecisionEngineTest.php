<?php

declare(strict_types=1);

namespace CarMoneyLab\Tests\Unit;

use CarMoneyLab\Domain\DecisionEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DecisionEngineTest extends TestCase
{
    private DecisionEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new DecisionEngine([
            'approve_max' => 60.0,
            'review_max' => 85.0,
            'review_max_mileage_km' => 400000,
        ]);
    }

    #[DataProvider('ltvValues')]
    public function testDecidesByLtv(float $ltv, int $mileage, string $expected): void
    {
        self::assertSame($expected, $this->engine->decide($ltv, $mileage));
    }

    /** @return array<string,array{float,int,string}> */
    public static function ltvValues(): array
    {
        return [
            'низкий LTV' => [28.5, 96000, DecisionEngine::APPROVE],
            'середина зелёной зоны' => [45.0, 96000, DecisionEngine::APPROVE],
            'серая зона' => [72.3, 96000, DecisionEngine::REVIEW],
            'верхняя граница серой зоны' => [85.0, 96000, DecisionEngine::REVIEW],
            'сразу за верхней границей' => [85.01, 96000, DecisionEngine::REJECT],
            'высокий LTV' => [120.0, 96000, DecisionEngine::REJECT],
        ];
    }

    #[DataProvider('mileageRules')]
    public function testAppliesMileageRule(int $mileage, float $ltv, string $expected): void
    {
        self::assertSame($expected, $this->engine->decide($ltv, $mileage));
    }

    /** @return array<string,array{int,float,string}> */
    public static function mileageRules(): array
    {
        return [
            'пробег 399999 при низком LTV — порог не срабатывает' => [399999, 50.0, DecisionEngine::APPROVE],
            'пробег ровно 400000 при низком LTV — порог включительный' => [400000, 50.0, DecisionEngine::APPROVE],
            'пробег 400001 при низком LTV — перевод в review' => [400001, 50.0, DecisionEngine::REVIEW],
            'пробег 500000 при низком LTV — верхняя граница диапазона' => [500000, 50.0, DecisionEngine::REVIEW],
            'пробег 400001 при среднем LTV — review сохраняется' => [400001, 70.0, DecisionEngine::REVIEW],
            'пробег 400001 при высоком LTV — reject сохраняет приоритет' => [400001, 90.0, DecisionEngine::REJECT],
            'пробег 0 при низком LTV — нижняя граница валидации' => [0, 50.0, DecisionEngine::APPROVE],
        ];
    }
}
