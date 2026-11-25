<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\PrintPriceRate;
use PHPUnit\Framework\TestCase;

final class PrintPriceRateTest extends TestCase
{
    public function testPrintLabelCombinesColorModeAndPaperSize(): void
    {
        self::assertSame('Couleur A3', PrintPriceRate::labelFor('COLOR', 'A3'));
    }

    public function testLegacyPrintWithoutCharacteristicsIsMonochromeA4(): void
    {
        self::assertSame('Monochrome A4', PrintPriceRate::labelFor(null, null));
    }

    public function testKnownServiceUsesItsBusinessLabel(): void
    {
        self::assertSame('Reliure de dossier', PrintPriceRate::labelFor('SERVICE', 'RELIURE_DOSSIER'));
    }

    public function testServiceAddedFromAdminGetsReadableLabel(): void
    {
        $rate = new PrintPriceRate(PrintPriceRate::SCOPE_CLIENT, PrintPriceRate::COLOR_MODE_SERVICE, 'SCAN_COULEUR', 100);

        self::assertTrue($rate->isService());
        self::assertSame('Scan couleur', $rate->getLabel());
    }
}
