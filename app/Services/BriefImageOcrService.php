<?php

namespace App\Services;

use App\Models\ProjectBriefDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class BriefImageOcrService
{
    /**
     * Распознать текст на изображении и сохранить в extracted_text.
     * F5AI chat/completions сейчас отбрасывает image_url, поэтому OCR — основной канал.
     */
    public function ensureExtractedText(ProjectBriefDocument $document): ?string
    {
        if (! $document->isImage()) {
            return trim((string) $document->extracted_text) ?: null;
        }

        $existing = trim((string) $document->extracted_text);
        if ($existing !== '') {
            return $existing;
        }

        if (! $document->disk_path || ! Storage::disk('local')->exists($document->disk_path)) {
            return null;
        }

        $absolutePath = Storage::disk('local')->path($document->disk_path);
        $text = $this->recognize($absolutePath);

        if ($text === null || $text === '') {
            return null;
        }

        $document->forceFill([
            'extracted_text' => $text,
            'char_count' => mb_strlen($text),
        ])->save();

        return $text;
    }

    public function isAvailable(): bool
    {
        $binary = $this->binary();

        try {
            $result = Process::timeout(15)->run([$binary, '--version']);

            return $result->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function recognize(string $absolutePath): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $binary = $this->binary();
        $languages = (string) config('f5ai.ocr_languages', 'rus+eng');
        $tempBase = tempnam(sys_get_temp_dir(), 'f5ocr_');

        if ($tempBase === false) {
            return null;
        }

        // tesseract appends .txt to the output base path
        @unlink($tempBase);
        $outputBase = $tempBase;
        $outputFile = $outputBase.'.txt';

        try {
            $result = Process::timeout((int) config('f5ai.ocr_timeout', 90))
                ->run([
                    $binary,
                    $absolutePath,
                    $outputBase,
                    '-l',
                    $languages,
                    '--psm',
                    '6',
                ]);

            if (! $result->successful()) {
                Log::warning('Brief image OCR failed', [
                    'path' => $absolutePath,
                    'error' => $result->errorOutput() ?: $result->output(),
                ]);

                return null;
            }

            if (! is_file($outputFile)) {
                return null;
            }

            $raw = file_get_contents($outputFile);
            if ($raw === false) {
                return null;
            }

            return $this->normalizeText($raw);
        } catch (\Throwable $exception) {
            Log::warning('Brief image OCR exception', [
                'path' => $absolutePath,
                'error' => $exception->getMessage(),
            ]);

            return null;
        } finally {
            if (is_file($outputFile)) {
                @unlink($outputFile);
            }
            if (is_file($tempBase)) {
                @unlink($tempBase);
            }
        }
    }

    private function binary(): string
    {
        $configured = trim((string) config('f5ai.ocr_binary', 'tesseract'));

        return $configured !== '' ? $configured : 'tesseract';
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
