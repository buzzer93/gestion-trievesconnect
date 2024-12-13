<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\Service;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DataExport
{
    /**
     * Summary of exportProduct
     * @param array<Product> $products Tableau contenant des objets Product
     * @param array<Service> $services Tableau contenant des objets Service
     * @return void
     */
    public function exportData($products, $services): void
    {

        $spreadsheet = new Spreadsheet();
        $activeWorksheet = $spreadsheet->getActiveSheet();
        $activeWorksheet->getDefaultColumnDimension()->setWidth(100, 'pt');
        $activeWorksheet->setCellValue('A1', 'Code');
        $activeWorksheet->setCellValue('B1', 'Libellé');
        $activeWorksheet->setCellValue('C1', 'Type');
        $activeWorksheet->setCellValue('D1', 'Famille');
        $activeWorksheet->setCellValue('E1', 'Description');
        $activeWorksheet->setCellValue('F1', 'Prix de vente HT');
        $activeWorksheet->setCellValue('G1', 'Unité');
        $activeWorksheet->setCellValue('H1', 'Compte comptable');

        foreach ($products as $key => $product) {
            $key = $key + 2;
            $activeWorksheet->setCellValue('A' . $key, (string)$product->getBarCode());
            $activeWorksheet->setCellValue('B' . $key, $product->getName());
            $activeWorksheet->setCellValue('C' . $key, 'Produit');
            $activeWorksheet->setCellValue('D' . $key, $product->getCategoriesName());
            $activeWorksheet->setCellValue('E' . $key, $product->getComment());
            $activeWorksheet->setCellValue('F' . $key, round($product->getSellingPriceHT(), 2));
            $activeWorksheet->setCellValue('G' . $key, 'Pièce');
            $activeWorksheet->setCellValue('H' . $key, 707000);
            $activeWorksheet->getStyle('A' . $key)->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_NUMBER);
        }
        foreach ($services as $key => $service) {
            $key = $key + 2 + count($products);
            $activeWorksheet->setCellValue('A' . $key, (string)$service->getBarCode());
            $activeWorksheet->setCellValue('B' . $key, $service->getName());
            $activeWorksheet->setCellValue('C' . $key, 'Service');
            $activeWorksheet->setCellValue('E' . $key, $service->getDescription());
            $activeWorksheet->setCellValue('F' . $key, round($service->getSellingPriceHT(), 2));
            $activeWorksheet->setCellValue('H' . $key, 706000);
            $activeWorksheet->getStyle('A' . $key)->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_NUMBER);
        }
        $writer = new Xlsx($spreadsheet);
        $writer->save('./export/ProductsDatas.xlsx');
    }
}
