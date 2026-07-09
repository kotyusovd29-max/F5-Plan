<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectSkkField;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\JcTable;

class ProjectDocxExporter
{
    private const TABLE_STYLE = [
        'borderSize' => 6,
        'borderColor' => 'CCCCCC',
        'cellMargin' => 80,
        'alignment' => JcTable::START,
    ];

    public function build(Project $project): string
    {
        $project->load(['funnels.stages', 'integrations', 'skkFields']);

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection();

        $section->addTitle($project->company_name, 1);
        $section->addText('План внедрения amoCRM');
        $section->addTextBreak();

        $this->addFunnelsSection($section, $project);
        $this->addSkkSection($section, $project);
        $this->addIntegrationsSection($section, $project);

        $tempFile = sys_get_temp_dir().'/f5plan_'.uniqid('', true).'.docx';
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);

        return $tempFile;
    }

    public function filename(Project $project): string
    {
        $name = preg_replace('/[^\p{L}\p{N}\-_]+/u', '_', $project->company_name) ?: 'project';

        return $name.'_план.docx';
    }

    private function addFunnelsSection($section, Project $project): void
    {
        $section->addTitle('Воронки', 2);

        if ($project->funnels->isEmpty()) {
            $section->addText('Воронки не добавлены.');
            $section->addTextBreak();

            return;
        }

        foreach ($project->funnels as $funnel) {
            $section->addText($funnel->name, ['bold' => true, 'size' => 12]);
            $section->addTextBreak(0.5);

            $table = $section->addTable(self::TABLE_STYLE);

            $table->addRow();
            $this->addHeaderCell($table, 2500, 'Название этапа');
            $this->addHeaderCell($table, 3500, 'Действие на этапе');
            $this->addHeaderCell($table, 3500, 'Комментарий к этапу');

            if ($funnel->stages->isEmpty()) {
                $table->addRow();
                $this->addCellWithMultilineText($table, 2500, '—');
                $this->addCellWithMultilineText($table, 3500, 'Этапы не добавлены');
                $this->addCellWithMultilineText($table, 3500, '—');
            } else {
                foreach ($funnel->stages as $stage) {
                    $table->addRow();
                    $this->addCellWithMultilineText($table, 2500, $stage->name);
                    $this->addCellWithMultilineText($table, 3500, $stage->action);
                    $this->addCellWithMultilineText($table, 3500, $stage->comment);
                }
            }

            $section->addTextBreak();
        }
    }

    private function addSkkSection($section, Project $project): void
    {
        $section->addTitle(ProjectSkkField::SECTION_LABEL, 2);
        $skkByType = $project->skkFields->groupBy('entity_type');

        foreach (ProjectSkkField::TYPE_LABELS as $type => $label) {
            $section->addText($label, ['bold' => true]);
            $fields = $skkByType->get($type, collect());

            if ($fields->isEmpty()) {
                $section->addText('Поля не добавлены.');
            } else {
                $table = $section->addTable(self::TABLE_STYLE);

                $table->addRow();
                $this->addHeaderCell($table, 9500, 'Название поля');

                foreach ($fields as $field) {
                    $table->addRow();
                    $this->addCellWithMultilineText($table, 9500, $field->name);
                }
            }

            $section->addTextBreak();
        }
    }

    private function addIntegrationsSection($section, Project $project): void
    {
        $section->addTitle('Интеграции и виджеты', 2);

        if ($project->integrations->isEmpty()) {
            $section->addText('Интеграции и виджеты не добавлены.');

            return;
        }

        $table = $section->addTable(self::TABLE_STYLE);

        $table->addRow();
        $this->addHeaderCell($table, 3500, 'Название');
        $this->addHeaderCell($table, 6000, 'Комментарий');

        foreach ($project->integrations as $integration) {
            $table->addRow();
            $this->addCellWithMultilineText($table, 3500, $integration->name);
            $this->addCellWithMultilineText($table, 6000, $integration->comment);
        }
    }

    private function addHeaderCell($table, int $width, string $text): void
    {
        $cell = $table->addCell($width, ['bgColor' => 'F3F4F6']);
        $cell->addText($text, ['bold' => true]);
    }

    private function addCellWithMultilineText($table, int $width, ?string $text): void
    {
        $cell = $table->addCell($width);
        $this->writeMultilineText($cell, $text);
    }

    private function writeMultilineText($container, ?string $text): void
    {
        if ($text === null || trim($text) === '') {
            $container->addText('—');

            return;
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $normalized);

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $container->addTextBreak();
            }

            if ($line !== '') {
                $container->addText($line);
            }
        }
    }
}
