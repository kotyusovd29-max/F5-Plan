<?php

namespace App\Services;

use App\Models\Project;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class AmoCrmAiAuditDocxExporter
{
    public function build(Project $project): string
    {
        $account = $project->amocrmAccount;
        $content = trim((string) $account?->last_ai_audit_result);

        if ($content === '') {
            abort(422, 'AI-аудит amoCRM ещё не выполнен.');
        }

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection();
        $section->addText($this->normalizeDocText('AI-аудит amoCRM'), ['bold' => true, 'size' => 16], ['spaceAfter' => 120]);
        $section->addText($this->normalizeDocText((string) $project->company_name), [], ['spaceAfter' => 60]);

        if ($account?->subdomain) {
            $section->addText($this->normalizeDocText('Аккаунт: '.$account->subdomain.'.amocrm.ru'));
        }

        if ($account?->last_ai_audit_at) {
            $section->addText($this->normalizeDocText(
                'Дата аудита: '.$account->last_ai_audit_at->timezone(config('app.timezone'))->format('d.m.Y H:i')
            ));
        }

        $section->addTextBreak();
        $this->writeMarkdownLikeText($section, $content);

        $tempFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'f5plan_amocrm_audit_'.uniqid('', true).'.docx';
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);

        return $tempFile;
    }

    public function filename(Project $project): string
    {
        $name = preg_replace('/[^\p{L}\p{N}\-_]+/u', '_', $project->company_name) ?: 'project';

        return $name.'_ai_audit_amocrm.docx';
    }

    private function writeMarkdownLikeText($section, string $text): void
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];

        foreach ($lines as $index => $line) {
            $line = trim((string) preg_replace('/\*\*(.*?)\*\*/u', '$1', $line));
            $line = $this->normalizeDocText($line);

            if ($line === '') {
                if ($index > 0) {
                    $section->addTextBreak();
                }

                continue;
            }

            if (preg_match('/^#{1,2}\s+(.+)$/', $line, $matches)) {
                $section->addText($matches[1], ['bold' => true, 'size' => 13], ['spaceBefore' => 200, 'spaceAfter' => 80]);

                continue;
            }

            if (preg_match('/^#{3,4}\s+(.+)$/', $line, $matches)) {
                $section->addText($matches[1], ['bold' => true, 'size' => 12], ['spaceBefore' => 160, 'spaceAfter' => 60]);

                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/', $line, $matches)) {
                $section->addText('- '.$matches[1], [], ['spaceAfter' => 40]);

                continue;
            }

            $section->addText($line, [], ['spaceAfter' => 60]);
        }
    }

    private function normalizeDocText(string $text): string
    {
        $text = str_replace(
            ['ё', 'Ё', '—', '–', '−'],
            ['е', 'Е', '-', '-', '-'],
            $text
        );

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? $text;
    }
}
