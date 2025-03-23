<?php

namespace App\Service;

use App\Entity\Product;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DataExport
{
    /**
     * Summary of exportProduct
     * @param array<Product> $products
     * @return void
     */
    public function exportProduct($products): void
    {
        $spreadsheet = new Spreadsheet();
        $activeWorksheet = $spreadsheet->getActiveSheet();
        $activeWorksheet->getDefaultColumnDimension()->setWidth(100, 'pt');
        $activeWorksheet->setCellValue('A1', 'Code');
        $activeWorksheet->setCellValue('B1', 'Libellé');
        $activeWorksheet->setCellValue('C1', 'Type');
        $activeWorksheet->setCellValue('D1', 'Famille');
        $activeWorksheet->setCellValue('E1', 'Prix de vente HT');
        $activeWorksheet->setCellValue('F1', 'Unité');

        foreach ($products as $key => $product) {
            $key = $key + 2;
            $activeWorksheet->setCellValue('A' . $key, (string)$product->getBarCode());
            $activeWorksheet->setCellValue('B' . $key, $product->getName());
            $activeWorksheet->setCellValue('C' . $key, 'Produit');
            $activeWorksheet->setCellValue('D' . $key, $product->getCategoriesName());
            $activeWorksheet->setCellValue('E' . $key, round($product->getSellingPriceHT(),2));
            $activeWorksheet->setCellValue('F' . $key, 'Pièce');
            $activeWorksheet->getStyle('A' . $key)->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_NUMBER);
        }
        $writer = new Xlsx($spreadsheet);
        $writer->save('./export/ProductsDatas.xlsx');
    }
}
