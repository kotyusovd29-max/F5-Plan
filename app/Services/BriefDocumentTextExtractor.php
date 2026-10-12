<?php

namespace App\Services;

use App\Exceptions\F5AiException;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ZipArchive;

class BriefDocumentTextExtractor
{
    /**
     * @return array{text: string, char_count: int, truncated: bool}
     */
    public function extract(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = strtolower((string) $file->getMimeType());

        $text = match (true) {
            $extension === 'docx' || str_contains($mimeType, 'wordprocessingml') => $this->extractDocx($file->getRealPath()),
            $extension === 'pdf' || $mimeType === 'application/pdf' => $this->extractPdf($file->getRealPath()),
            in_array($extension, ['xlsx', 'xls'], true) || str_contains($mimeType, 'spreadsheetml') || str_contains($mimeType, 'ms-excel') => $this->extractSpreadsheet($file->getRealPath()),
            in_array($extension, ['txt', 'md', 'csv'], true) || str_starts_with($mimeType, 'text/') => $this->extractPlainText($file->getRealPath()),
            $extension === 'rtf' || $mimeType === 'application/rtf' => $this->extractRtf($file->getRealPath()),
            default => throw new F5AiException('Поддерживаются файлы PDF, DOCX, XLSX, TXT, MD и RTF.'),
        };

        $text = $this->normalizeText($text);

        if ($text === '') {
            throw new F5AiException('Не удалось извлечь текст из документа. Проверьте файл или загрузите PDF/DOCX/XLSX/TXT.');
        }

        return [
            'text' => $text,
            'char_count' => mb_strlen($text),
            'truncated' => false,
        ];
    }

    private function extractPlainText(string $path): string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            return '';
        }

        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1251, ISO-8859-1, UTF-8');
        }

        return $contents;
    }

    private function extractDocx(string $path): string
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new F5AiException('Не удалось прочитать DOCX-файл.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false || $xml === '') {
            return '';
        }

        $xml = str_replace(
            ['</w:p>', '<w:tab/>', '<w:br/>', '<w:cr/>'],
            ["\n", "\t", "\n", "\n"],
            $xml
        );

        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');

        return $text;
    }

    private function extractPdf(string $path): string
    {
        try {
            $parser = new \Smalot\PdfParser\Parser;
            $pdf = $parser->parseFile($path);
            $text = $pdf->getText();
        } catch (\Throwable $exception) {
            report($exception);

            throw new F5AiException('Не удалось прочитать PDF. Если файл защищён или сканированный — загрузите текстовый PDF или DOCX.');
        }

        return is_string($text) ? $text : '';
    }

    private function extractRtf(string $path): string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            return '';
        }

        $text = preg_replace('/\{\\\\\*\\\\[^{}]+}|[{}]|\\\\[a-z]+\\d* ?|\\\\\'[0-9a-f]{2}/i', '', $contents) ?? $contents;

        return $text;
    }

    private function extractSpreadsheet(string $path): string
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $exception) {
            report($exception);

            throw new F5AiException('Не удалось прочитать Excel-файл. Загрузите XLSX или экспортируйте лист в CSV/TXT.');
        }

        $parts = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $title = trim((string) $sheet->getTitle());
            $rows = [];

            foreach ($sheet->toArray(null, true, true, false) as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $cells = array_map(
                    fn ($value) => trim((string) ($value ?? '')),
                    $row
                );
                $cells = array_values(array_filter($cells, fn (string $value) => $value !== ''));

                if ($cells === []) {
                    continue;
                }

                $rows[] = implode("\t", $cells);
            }

            if ($rows === []) {
                continue;
            }

            $parts[] = ($title !== '' ? "Лист: {$title}\n" : '').implode("\n", $rows);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return implode("\n\n", $parts);
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace("\x00", '', $text);
        $text = preg_replace("/\r\n|\r/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
