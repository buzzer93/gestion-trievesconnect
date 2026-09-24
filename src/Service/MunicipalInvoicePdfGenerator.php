<?php

declare(strict_types=1);

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Construit le PDF de la facture trimestrielle mairie (récap par
 * association + total) à partir du même récap que la page Budget mairie
 * (cf. MunicipalBudgetSummaryProvider), rendu via un template Twig dédié
 * puis converti en PDF par Dompdf.
 */
class MunicipalInvoicePdfGenerator
{
    public function __construct(private readonly Environment $twig)
    {
    }

    /**
     * @param array<int, array{association: \App\Entity\Association, amountCents: int}> $byAssociation
     */
    public function generate(array $byAssociation, int $totalCents, int $year, int $quarter): Dompdf
    {
        $html = $this->twig->render('admin/municipal_budget/invoice_pdf.html.twig', [
            'byAssociation' => $byAssociation,
            'totalCents' => $totalCents,
            'year' => $year,
            'quarter' => $quarter,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        return $dompdf;
    }
}
