<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectIntegration;
use App\Support\F5WidgetCatalog;
use App\Support\WidgetPricingCalculator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProjectXlsxExporter
{
    private const GREEN = '93C47D';

    private const RED = 'F4CCCC';

    private const YELLOW = 'FFFF00';

    private const LIGHT_GRAY = 'F3F3F3';

    private const MONEY_FORMAT = '#,##0.00';

    public function build(Project $project): string
    {
        $project->loadMissing(['workStages', 'integrations']);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Лист1');
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        $this->configureColumns($sheet);

        $row = $this->writeWorksHeader($sheet);
        $row = $this->writeWorkStages($sheet, $project, $row);
        $row += 2;
        $row = $this->writeCustomServices($sheet, $project, $row);
        $row += 2;
        $row = $this->writeAmocrmLicenseSection($sheet, $project, $row);
        $row += 2;
        $row = $this->writeWidgetsSection($sheet, $project, $row);
        $row += 2;
        $this->writeUnlimitedSubscriptions($sheet, $project, $row);

        $tempFile = tempnam(sys_get_temp_dir(), 'f5xlsx_');
        if ($tempFile === false) {
            throw new \RuntimeException('Не удалось создать временный файл XLSX.');
        }

        $xlsxPath = $tempFile.'.xlsx';
        @unlink($tempFile);

        (new Xlsx($spreadsheet))->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();

        return $xlsxPath;
    }

    public function filename(Project $project): string
    {
        $name = preg_replace('/[^\p{L}\p{N}\-_]+/u', '_', $project->company_name) ?: 'project';

        return $name.'_состав_работ_и_смета.xlsx';
    }

    private function configureColumns(Worksheet $sheet): void
    {
        $sheet->getColumnDimension('A')->setWidth(11.25);
        $sheet->getColumnDimension('B')->setWidth(40);
        $sheet->getColumnDimension('C')->setWidth(76);
        $sheet->getColumnDimension('D')->setWidth(22);
        $sheet->getColumnDimension('E')->setWidth(38);
        $sheet->getColumnDimension('F')->setWidth(38);
    }

    private function writeWorksHeader(Worksheet $sheet): int
    {
        $headers = [
            'A1' => '№',
            'B1' => 'Название',
            'C1' => 'Состав работ',
            'D1' => 'Стоимость работ',
            'E1' => 'Срок реализации',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'name' => 'Arial'],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => $this->thinBorders(),
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        return 2;
    }

    private function writeWorkStages(Worksheet $sheet, Project $project, int $row): int
    {
        if ($project->workStages->isEmpty()) {
            $sheet->setCellValue("A{$row}", '—');
            $sheet->setCellValue("B{$row}", 'Этапы работ пока не добавлены');
            $sheet->mergeCells("B{$row}:E{$row}");
            $this->styleWorksRow($sheet, $row);
            $row++;

            return $row;
        }

        $index = 1;
        foreach ($project->workStages as $stage) {
            $composition = \App\Support\WorkStageComposition::format($stage->composition) ?? '';

            $sheet->setCellValue("A{$row}", $index);
            $sheet->setCellValue("B{$row}", $stage->name);
            $sheet->setCellValue("C{$row}", $composition);

            if ($stage->cost !== null) {
                $sheet->setCellValue("D{$row}", (float) $stage->cost);
                $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode(self::MONEY_FORMAT.'" руб."');
            }

            if ($stage->duration_days !== null) {
                $days = (int) $stage->duration_days;
                $sheet->setCellValue("E{$row}", $days.' '.$this->workingDayWord($days));
            } else {
                $sheet->setCellValue("E{$row}", '');
            }

            $sheet->setCellValue("F{$row}", 'разово за услуги');

            $sheet->getStyle("A{$row}:F{$row}")->getAlignment()
                ->setWrapText(true)
                ->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$row}")->getFont()->setBold(true)->setSize(12);
            $this->styleWorksRow($sheet, $row);

            $lineCount = max(3, substr_count($composition, "\n") + 1);
            $sheet->getRowDimension($row)->setRowHeight(min(220, max(45, $lineCount * 16)));

            $index++;
            $row++;
        }

        return $row;
    }

    private function writeCustomServices(Worksheet $sheet, Project $project, int $row): int
    {
        $services = $project->integrations->filter(
            fn ($item) => $item->widget_slug === null || $item->widget_slug === '' || $item->widget_slug === F5WidgetCatalog::CUSTOM_SLUG
        );

        if ($services->isEmpty()) {
            return $row;
        }

        $first = true;
        foreach ($services as $service) {
            if ($first) {
                $sheet->setCellValue("C{$row}", 'Сервис');
                $sheet->getStyle("C{$row}")->getFont()->setBold(true)->setSize(12);
                $first = false;
            }

            $sheet->setCellValue("D{$row}", $service->name);

            $paymentHint = '';
            if ($service->payment_type === ProjectIntegration::PAYMENT_ONE_TIME && $service->cost !== null) {
                $paymentHint = 'Единовременно: '.WidgetPricingCalculator::formatRub((float) $service->cost);
            } elseif ($service->cost !== null) {
                $paymentHint = 'Подписка: '.WidgetPricingCalculator::formatRub((float) $service->cost).' / мес';
            }

            $commentParts = array_values(array_filter([
                $paymentHint !== '' ? $paymentHint : null,
                filled($service->comment) ? (string) $service->comment : null,
            ]));
            $sheet->setCellValue("E{$row}", implode(' · ', $commentParts));
            $sheet->getStyle("D{$row}:E{$row}")->getAlignment()->setWrapText(true);
            $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
                'borders' => $this->thinBorders(),
            ]);
            $row++;
        }

        return $row;
    }

    private function writeAmocrmLicenseSection(Worksheet $sheet, Project $project, int $row): int
    {
        $cost = WidgetPricingCalculator::amocrmLicenseEstimatePeriods(
            $project->employees_count,
            $project->amocrm_license_tariff
        );

        $sheet->setCellValue("B{$row}", 'Лицензия amoCRM');
        $sheet->setCellValue("C{$row}", 'Цена за 1 польз. в месяц');
        $sheet->setCellValue("D{$row}", 'Кол-во польз.');
        $sheet->setCellValue("E{$row}", 'на 6+1 (вкл. 1 мес. в подарок) месяцев');
        $sheet->setCellValue("F{$row}", 'на 10+3 месяцев (вкл. 3 мес. в подарок)');

        $sheet->getStyle("B{$row}:F{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::GREEN],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => $this->thinBorders(),
        ]);
        $sheet->getRowDimension($row)->setRowHeight(36);
        $row++;

        if ($cost['selectedSlug'] === null || $cost['tariffs'] === []) {
            $sheet->setCellValue("A{$row}", 'Лицензии');
            $this->paintVariantLabel($sheet, "A{$row}");
            $sheet->setCellValue("B{$row}", 'Тариф amoCRM не выбран');
            $sheet->mergeCells("B{$row}:F{$row}");
            $this->applyRowBorder($sheet, "A{$row}:F{$row}");
            $row++;

            return $row;
        }

        foreach ($cost['tariffs'] as $index => $tariff) {
            if ($index === 0) {
                $sheet->setCellValue("A{$row}", 'Лицензии');
                $this->paintVariantLabel($sheet, "A{$row}");
            }

            $sheet->setCellValue("B{$row}", $tariff['name']);
            $sheet->setCellValue("C{$row}", $tariff['price_per_user']);
            $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
            $sheet->setCellValue("D{$row}", $cost['billingUsers']);
            $sheet->setCellValue("E{$row}", $tariff['costSixPlusOne']);
            $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode(self::MONEY_FORMAT.'" руб."');
            $sheet->setCellValue("F{$row}", $tariff['costTenPlusThree']);
            $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode(self::MONEY_FORMAT.'" руб."');

            $sheet->getStyle("B{$row}:F{$row}")->applyFromArray([
                'borders' => $this->thinBorders(),
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ]);
            $sheet->getStyle("A{$row}")->applyFromArray([
                'borders' => $this->thinBorders(),
            ]);
            $row++;
        }

        $sheet->setCellValue("B{$row}", $cost['sourceNote'] !== ''
            ? $cost['sourceNote'].' '.$cost['sourceUrl']
            : $cost['sourceUrl']);
        $sheet->mergeCells("B{$row}:F{$row}");
        $sheet->getStyle("B{$row}")->getFont()->setItalic(true)->setSize(9);
        $sheet->getStyle("B{$row}")->getAlignment()->setWrapText(true);
        $sheet->getRowDimension($row)->setRowHeight(28);
        $row++;

        return $row;
    }

    private function writeWidgetsSection(Worksheet $sheet, Project $project, int $row): int
    {
        $payload = $project->integrations->map(fn ($item) => [
            'name' => $item->name,
            'widget_slug' => $item->widget_slug,
            'billing_quantity' => $item->billing_quantity,
            'comment' => $item->comment,
            'payment_type' => $item->payment_type,
            'cost' => $item->cost,
        ])->all();

        $urlsByName = $this->integrationUrlsByName($project);

        $cost = WidgetPricingCalculator::calculateEstimatePeriods(
            $payload,
            F5WidgetCatalog::widgets(),
            $project->employees_count
        );

        $sheet->setCellValue("B{$row}", 'Стоимость виджетов - ПО');
        $sheet->setCellValue("C{$row}", 'Цена за 1 польз. в месяц');
        $sheet->setCellValue("D{$row}", 'Кол-во польз.');
        $sheet->setCellValue("E{$row}", 'на 6+1 (вкл. 1 мес. в подарок) месяцев');
        $sheet->setCellValue("F{$row}", 'на 10+3 месяцев (вкл. 3 мес. в подарок)');

        $sheet->getStyle("B{$row}:F{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::GREEN],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => $this->thinBorders(),
        ]);
        $sheet->getRowDimension($row)->setRowHeight(36);
        $row++;

        $indexShown = false;

        if ($cost['lines'] === []) {
            $sheet->setCellValue("A{$row}", 'Вариант 1');
            $sheet->setCellValue("B{$row}", 'Виджеты с фиксированным тарифом не добавлены');
            $sheet->mergeCells("B{$row}:F{$row}");
            $this->paintVariantLabel($sheet, "A{$row}");
            $this->applyRowBorder($sheet, "A{$row}:F{$row}");
            $row++;
        }

        foreach ($cost['lines'] as $line) {
            if (! $indexShown) {
                $sheet->setCellValue("A{$row}", 'Вариант 1');
                $this->paintVariantLabel($sheet, "A{$row}");
                $indexShown = true;
            }

            $this->setWidgetNameCell($sheet, "B{$row}", $line['name'], $urlsByName[$line['name']] ?? null);
            if ($line['pricePerUser'] !== null) {
                $sheet->setCellValue("C{$row}", $line['pricePerUser']);
                $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
            } else {
                $sheet->setCellValue("C{$row}", $line['priceLabel']);
            }

            if ($line['quantity'] === '—') {
                $sheet->setCellValue("D{$row}", '—');
            } else {
                $sheet->setCellValue("D{$row}", $line['quantity']);
            }

            if ($line['costSixPlusOne'] !== null) {
                $sheet->setCellValue("E{$row}", $line['costSixPlusOne']);
                $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
            }
            if ($line['costTenPlusThree'] !== null) {
                $sheet->setCellValue("F{$row}", $line['costTenPlusThree']);
                $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
            }

            $sheet->getStyle("C{$row}:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $this->applyRowBorder($sheet, "A{$row}:F{$row}");
            $row++;
        }

        foreach ($cost['skipped'] as $line) {
            // custom already shown as services; catalog skips without price stay listed under widgets
            if (str_contains($line['reason'], 'Своя интеграция')) {
                continue;
            }

            if (! $indexShown) {
                $sheet->setCellValue("A{$row}", 'Вариант 1');
                $this->paintVariantLabel($sheet, "A{$row}");
                $indexShown = true;
            }

            $this->setWidgetNameCell($sheet, "B{$row}", $line['name'], $urlsByName[$line['name']] ?? null);
            $sheet->setCellValue("C{$row}", $line['priceLabel'] !== '' ? $line['priceLabel'] : $line['reason']);
            $sheet->setCellValue("D{$row}", $cost['billingUsers']);
            $sheet->setCellValue("E{$row}", '—');
            $sheet->setCellValue("F{$row}", '—');
            $this->applyRowBorder($sheet, "A{$row}:F{$row}");
            $row++;
        }

        if ($cost['lines'] !== []) {
            $sheet->setCellValue("D{$row}", 'Итого');
            $sheet->setCellValue("E{$row}", $cost['totalSixPlusOne']);
            $sheet->setCellValue("F{$row}", $cost['totalTenPlusThree']);
            $sheet->getStyle("E{$row}:F{$row}")->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
            $sheet->getStyle("D{$row}:F{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => self::YELLOW],
                ],
                'borders' => $this->thinBorders(),
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $row++;
        }

        return $row;
    }

    /**
     * @return array<string, string>
     */
    private function integrationUrlsByName(Project $project): array
    {
        $urls = [];

        foreach ($project->integrations as $integration) {
            $url = F5WidgetCatalog::resolveUrl($integration->widget_slug, $integration->widget_url);

            if ($url !== null) {
                $urls[(string) $integration->name] = $url;
            }
        }

        return $urls;
    }

    private function setWidgetNameCell(Worksheet $sheet, string $cell, string $name, ?string $url): void
    {
        $sheet->setCellValue($cell, $name);
        $sheet->getStyle($cell)->getFont()->setBold(true)->setSize(12);

        if ($url === null || $url === '') {
            return;
        }

        $sheet->getCell($cell)->getHyperlink()->setUrl($url);
        $sheet->getStyle($cell)->getFont()
            ->setUnderline(true)
            ->getColor()
            ->setRGB('0563C1');
    }

    private function writeUnlimitedSubscriptions(Worksheet $sheet, Project $project, int $row): int
    {
        $variants = WidgetPricingCalculator::unlimitedSubscriptionRows($project->employees_count);

        foreach ($variants as $variant) {
            $sheet->setCellValue("A{$row}", $variant['label']);
            $sheet->setCellValue("B{$row}", $variant['title']);
            $sheet->setCellValue("C{$row}", $variant['condition']);
            $sheet->mergeCells("C{$row}:D{$row}");
            $sheet->setCellValue("E{$row}", 'на 7 месяцев');
            $sheet->setCellValue("F{$row}", $variant['longPeriodLabel']);

            $this->paintVariantLabel($sheet, "A{$row}");
            $sheet->getStyle("B{$row}")->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle("C{$row}")->getAlignment()->setWrapText(true);
            $sheet->getStyle("E{$row}:F{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => self::GREEN],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'wrapText' => true,
                ],
                'borders' => $this->thinBorders(),
            ]);
            $this->applyRowBorder($sheet, "A{$row}:D{$row}");
            $sheet->getRowDimension($row)->setRowHeight(40);
            $row++;

            $longMonths = (int) $variant['longMonths'];

            foreach ($variant['tariffs'] as $tariff) {
                $sheet->setCellValue("B{$row}", $tariff['name']);
                $sheet->setCellValue("C{$row}", $tariff['price_per_user']);
                $sheet->setCellValue("D{$row}", $tariff['users']);
                // цена × пользователи × месяцы периода
                $sheet->setCellValue(
                    "E{$row}",
                    '=C'.$row.'*D'.$row.'*'.WidgetPricingCalculator::UNLIMITED_SHORT_MONTHS
                );
                $sheet->setCellValue(
                    "F{$row}",
                    '=C'.$row.'*D'.$row.'*'.$longMonths
                );
                $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
                $sheet->getStyle("E{$row}:F{$row}")->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
                $sheet->getStyle("B{$row}")->getAlignment()->setWrapText(true);
                $sheet->getStyle("C{$row}:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $this->applyRowBorder($sheet, "A{$row}:F{$row}");
                $sheet->getStyle("A{$row}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => self::RED],
                    ],
                ]);
                $row++;
            }

            $row++;
        }

        return $row;
    }

    private function styleWorksRow(Worksheet $sheet, int $row): void
    {
        $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
            'borders' => $this->thinBorders(),
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::LIGHT_GRAY],
            ],
        ]);
    }

    private function paintVariantLabel(Worksheet $sheet, string $cell): void
    {
        $sheet->getStyle($cell)->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::RED],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => $this->thinBorders(),
        ]);
    }

    private function applyRowBorder(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'borders' => $this->thinBorders(),
        ]);
    }

    /**
     * @return array{allBorders: array{borderStyle: string, color: array{rgb: string}}}
     */
    private function thinBorders(): array
    {
        return [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'B7B7B7'],
            ],
        ];
    }

    private function workingDayWord(int $days): string
    {
        $mod10 = $days % 10;
        $mod100 = $days % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return 'раб. день';
        }

        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return 'раб. дня';
        }

        return 'раб. дней';
    }
}
