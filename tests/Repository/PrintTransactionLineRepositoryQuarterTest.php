<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\PrintTransactionLineRepository;
use PHPUnit\Framework\TestCase;

/**
 * Trimestre SCOLAIRE (pas calendaire), décision du 2026-08-26 : 4
 * trimestres de 3 mois calés sur la rentrée (T1 = sept-nov, T2 = déc-févr,
 * T3 = mars-mai, T4 = juin-août) au lieu du 1er janvier. Test unitaire pur
 * (pas de DB) car T2 chevauche le 31/12 et le calcul de "année scolaire" /
 * navigation précédent-suivant est fait main.
 */
final class PrintTransactionLineRepositoryQuarterTest extends TestCase
{
    public function testQuarterBoundsUseSchoolYearStartAsYear(): void
    {
        // year=2026 = année scolaire 2026-2027.
        [$start1, $end1] = PrintTransactionLineRepository::quarterBounds(2026, 1);
        self::assertSame('2026-09-01', $start1->format('Y-m-d'));
        self::assertSame('2026-12-01', $end1->format('Y-m-d'));

        // T2 chevauche le 31/12 : commence en 2026, finit en 2027.
        [$start2, $end2] = PrintTransactionLineRepository::quarterBounds(2026, 2);
        self::assertSame('2026-12-01', $start2->format('Y-m-d'));
        self::assertSame('2027-03-01', $end2->format('Y-m-d'));

        [$start3, $end3] = PrintTransactionLineRepository::quarterBounds(2026, 3);
        self::assertSame('2027-03-01', $start3->format('Y-m-d'));
        self::assertSame('2027-06-01', $end3->format('Y-m-d'));

        [$start4, $end4] = PrintTransactionLineRepository::quarterBounds(2026, 4);
        self::assertSame('2027-06-01', $start4->format('Y-m-d'));
        self::assertSame('2027-09-01', $end4->format('Y-m-d'));
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
        yield 'novembre' => ['2026-11-30', 1];
        yield 'décembre' => ['2026-12-01', 2];
        yield 'janvier' => ['2027-01-15', 2];
        yield 'février' => ['2027-02-28', 2];
        yield 'mars' => ['2027-03-01', 3];
        yield 'mai' => ['2027-05-31', 3];
        yield 'juin' => ['2027-06-01', 4];
        yield 'août' => ['2027-08-31', 4];
    }

    /**
     * @dataProvider currentSchoolYearStartProvider
     */
    public function testCurrentSchoolYearStart(string $date, int $expectedYear): void
    {
        self::assertSame($expectedYear, PrintTransactionLineRepository::currentSchoolYearStart(new \DateTimeImmutable($date)));
    }

    public static function currentSchoolYearStartProvider(): iterable
    {
        yield 'septembre : nouvelle année scolaire' => ['2026-09-01', 2026];
        yield 'décembre : toujours la même année scolaire' => ['2026-12-15', 2026];
        yield 'janvier : encore l\'année scolaire commencée en septembre précédent' => ['2027-01-15', 2026];
        yield 'août : dernier mois de l\'année scolaire commencée l\'année civile précédente' => ['2027-08-31', 2026];
    }

    public function testNextQuarterOnlyChangesYearAtTheSeptemberBoundary(): void
    {
        self::assertSame([2026, 2], PrintTransactionLineRepository::nextQuarter(2026, 1));
        self::assertSame([2026, 3], PrintTransactionLineRepository::nextQuarter(2026, 2));
        self::assertSame([2026, 4], PrintTransactionLineRepository::nextQuarter(2026, 3));
        self::assertSame([2027, 1], PrintTransactionLineRepository::nextQuarter(2026, 4));
    }

    public function testPreviousQuarterOnlyChangesYearAtTheSeptemberBoundary(): void
    {
        self::assertSame([2025, 4], PrintTransactionLineRepository::previousQuarter(2026, 1));
        self::assertSame([2026, 1], PrintTransactionLineRepository::previousQuarter(2026, 2));
        self::assertSame([2026, 2], PrintTransactionLineRepository::previousQuarter(2026, 3));
        self::assertSame([2026, 3], PrintTransactionLineRepository::previousQuarter(2026, 4));
    }

    public function testNextAndPreviousQuarterAreInverses(): void
    {
        foreach ([1, 2, 3, 4] as $quarter) {
            [$nextYear, $nextQuarter] = PrintTransactionLineRepository::nextQuarter(2026, $quarter);
            self::assertSame([2026, $quarter], PrintTransactionLineRepository::previousQuarter($nextYear, $nextQuarter));
        }
    }
}
