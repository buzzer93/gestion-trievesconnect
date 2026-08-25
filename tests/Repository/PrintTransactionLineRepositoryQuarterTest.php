<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\PrintTransactionLineRepository;
use PHPUnit\Framework\TestCase;

/**
 * Trimestre SCOLAIRE (pas calendaire), décision du 2026-08-26 : T1 =
 * septembre-décembre, T2 = janvier-avril, T3 = mai-août. Test unitaire pur
 * (pas de DB) car ce calcul de bornes/navigation est fait main et sujet à
 * erreur, notamment le changement d'année : T1 est numéroté "1" mais
 * chronologiquement LE DERNIER trimestre de l'année civile.
 */
final class PrintTransactionLineRepositoryQuarterTest extends TestCase
{
    public function testQuarterBoundsStayWithinASingleCivilYear(): void
    {
        [$start1, $end1] = PrintTransactionLineRepository::quarterBounds(2026, 1);
        self::assertSame('2026-09-01', $start1->format('Y-m-d'));
        self::assertSame('2027-01-01', $end1->format('Y-m-d'));

        [$start2, $end2] = PrintTransactionLineRepository::quarterBounds(2026, 2);
        self::assertSame('2026-01-01', $start2->format('Y-m-d'));
        self::assertSame('2026-05-01', $end2->format('Y-m-d'));

        [$start3, $end3] = PrintTransactionLineRepository::quarterBounds(2026, 3);
        self::assertSame('2026-05-01', $start3->format('Y-m-d'));
        self::assertSame('2026-09-01', $end3->format('Y-m-d'));
    }

    /**
     * @dataProvider currentQuarterProvider
     */
    public function testCurrentQuarterMatchesTheMonth(string $date, int $expectedQuarter): void
    {
        self::assertSame($expectedQuarter, PrintTransactionLineRepository::currentQuarter(new \DateTimeImmutable($date)));
    }

    public static function currentQuarterProvider(): iterable
    {
        yield 'rentrée (septembre)' => ['2026-09-01', 1];
        yield 'décembre' => ['2026-12-31', 1];
        yield 'janvier' => ['2026-01-15', 2];
        yield 'avril' => ['2026-04-30', 2];
        yield 'mai' => ['2026-05-01', 3];
        yield 'août' => ['2026-08-31', 3];
    }

    /**
     * Navigation chronologique complète sur deux années civiles : T2(2026)
     * -> T3(2026) -> T1(2026) -> T2(2027) -> ... Seul le passage T1 -> T2
     * change d'année (avance), et seul T2 -> T1 en recule (via previous).
     */
    public function testNextQuarterFollowsSchoolYearChronology(): void
    {
        self::assertSame([2026, 3], PrintTransactionLineRepository::nextQuarter(2026, 2));
        self::assertSame([2026, 1], PrintTransactionLineRepository::nextQuarter(2026, 3));
        self::assertSame([2027, 2], PrintTransactionLineRepository::nextQuarter(2026, 1));
    }

    public function testPreviousQuarterFollowsSchoolYearChronology(): void
    {
        self::assertSame([2026, 3], PrintTransactionLineRepository::previousQuarter(2026, 1));
        self::assertSame([2025, 1], PrintTransactionLineRepository::previousQuarter(2026, 2));
        self::assertSame([2026, 2], PrintTransactionLineRepository::previousQuarter(2026, 3));
    }

    public function testNextAndPreviousQuarterAreInverses(): void
    {
        foreach ([1, 2, 3] as $quarter) {
            [$nextYear, $nextQuarter] = PrintTransactionLineRepository::nextQuarter(2026, $quarter);
            self::assertSame([2026, $quarter], PrintTransactionLineRepository::previousQuarter($nextYear, $nextQuarter));
        }
    }
}
