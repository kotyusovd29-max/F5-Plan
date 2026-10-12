<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAiMessage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class MeetingAiMessageDocxExporter
{
    public function build(Project $project, ProjectAiMessage $message): string
    {
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection();

        $section->addText((string) $project->company_name, ['bold' => true, 'size' => 16], ['spaceAfter' => 80]);
        $section->addText('Структура первичной встречи по внедрению amoCRM', [], ['spaceAfter' => 120]);

        if ($message->created_at) {
            $section->addText(
                'Подготовлено: '.$message->created_at->format('d.m.Y H:i'),
                ['size' => 10, 'color' => '555555'],
                ['spaceAfter' => 200]
            );
        }

        $this->writeMarkdownLikeText($section, trim((string) $message->content));

        $tempFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'f5plan_meeting_'.uniqid('', true).'.docx';
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);

        return $tempFile;
    }

    public function filename(Project $project, ProjectAiMessage $message): string
    {
        $name = preg_replace('/[^\p{L}\p{N}\-_]+/u', '_', $project->company_name) ?: 'project';
        $suffix = $message->created_at?->format('Y-m-d_H-i') ?? 'meeting';

        return $name.'_встреча_'.$suffix.'.docx';
    }

    private function writeMarkdownLikeText($section, string $text): void
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];

        foreach ($lines as $index => $line) {
            $line = trim((string) preg_replace('/\*\*(.*?)\*\*/u', '$1', $line));
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
