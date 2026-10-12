<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAiMessage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

class AuditRecommendationsDocxExporter
{
    public function build(Project $project, ProjectAiMessage $message): string
    {
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'marginTop' => 900,
            'marginBottom' => 900,
            'marginLeft' => 1100,
            'marginRight' => 1100,
        ]);

        $section->addText(
            'РЕКОМЕНДАЦИИ ПО АУДИТУ amoCRM',
            ['bold' => true, 'size' => 16],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 120]
        );
        $section->addText(
            (string) $project->company_name,
            ['bold' => true, 'size' => 14],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 80]
        );
        $section->addText(
            'Документ для клиента - команда F5',
            ['size' => 10, 'color' => '666666'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 200]
        );

        if ($message->created_at) {
            $section->addText(
                'Дата: '.$message->created_at->format('d.m.Y'),
                ['size' => 10, 'color' => '555555'],
                ['spaceAfter' => 240]
            );
        }

        $section->addText(
            'Ниже - рекомендации по улучшению настройки и процессов в amoCRM по итогам аудита / встречи. '
            .'Пункты сформулированы для обсуждения с командой клиента и приоритизации работ.',
            ['italic' => true, 'size' => 10, 'color' => '444444'],
            ['spaceAfter' => 280]
        );

        $this->writeMarkdownLikeText($section, trim((string) $message->content));

        $section->addTextBreak(1);
        $section->addText(
            '- конец документа -',
            ['size' => 9, 'color' => '999999'],
            ['alignment' => Jc::CENTER]
        );

        $tempFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'f5plan_audit_recommendations_'.uniqid('', true).'.docx';
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);

        return $tempFile;
    }

    public function filename(Project $project, ProjectAiMessage $message): string
    {
        $name = preg_replace('/[^\p{L}\p{N}\-_]+/u', '_', $project->company_name) ?: 'project';
        $suffix = $message->created_at?->format('Y-m-d_H-i') ?? 'audit';

        return $name.'_рекомендации_по_аудиту_'.$suffix.'.docx';
    }

    private function writeMarkdownLikeText($section, string $text): void
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];

        foreach ($lines as $index => $line) {
            $line = trim((string) preg_replace('/\*\*(.*?)\*\*/u', '$1', $line));
            // Убираем управляющие символы, которые ломают OOXML/конвертацию Google.
            $line = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $line) ?? $line;

            if ($line === '') {
                if ($index > 0) {
                    $section->addTextBreak();
                }

                continue;
            }

            if (preg_match('/^#{1,3}\s+(.+)$/', $line, $matches)) {
                $section->addText($matches[1], ['bold' => true, 'size' => 13], ['spaceBefore' => 200, 'spaceAfter' => 80]);

                continue;
            }

            if (preg_match('/^\d+\.\s+(.+)$/', $line, $matches)) {
                $section->addText($line, [], ['spaceAfter' => 60]);

                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/', $line, $matches)) {
                $section->addText('- '.$matches[1], [], ['spaceAfter' => 40]);

                continue;
            }

            $section->addText($line, [], ['spaceAfter' => 60]);
        }
    }
}
