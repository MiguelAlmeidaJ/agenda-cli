<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PublicEstablishmentSearchService;
use PHPUnit\Framework\TestCase;

final class PublicEstablishmentSearchServiceTest extends TestCase
{
    public function testNormalizesFilters(): void
    {
        $filters = (new PublicEstablishmentSearchService())->filters([
            'q' => '  Corte masculino  ',
            'city' => '  Juiz de Fora ',
            'state' => 'mg',
            'sort' => 'price',
        ]);

        self::assertSame('Corte masculino', $filters['q']);
        self::assertSame('Juiz de Fora', $filters['city']);
        self::assertSame('MG', $filters['state']);
        self::assertSame('price', $filters['sort']);
    }

    public function testRejectsInvalidStateAndSort(): void
    {
        $filters = (new PublicEstablishmentSearchService())->filters([
            'state' => 'Minas Gerais',
            'sort' => 'distance',
        ]);

        self::assertSame('', $filters['state']);
        self::assertSame('relevance', $filters['sort']);
    }

    public function testRanksExactServiceBeforeDescription(): void
    {
        $service = new PublicEstablishmentSearchService();

        $exactService = $service->rank([
            'name' => 'Studio Central',
            'description' => '',
            'service_names' => ['Corte', 'Barba'],
        ], 'corte');

        $description = $service->rank([
            'name' => 'Studio Central',
            'description' => 'Especialistas em corte',
            'service_names' => ['Barba'],
        ], 'corte');

        self::assertLessThan($description, $exactService);
    }
}
