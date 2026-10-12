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
        $project->load(['funnels.stages', 'integrations', 'workStages', 'skkFields', 'aiMessages']);

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection();

        $section->addTitle($project->company_name, 1);
        $section->addText('План внедрения amoCRM');
        $section->addTextBreak();

        if ($project->website || $project->business_sphere || $project->current_services || $project->employees_count || $project->client_tasks) {
            $section->addTitle('О компании', 2);

            if ($project->website) {
                $section->addText('Сайт: '.$project->website);
            }

            if ($project->business_sphere) {
                $section->addText('Сфера бизнеса: '.$project->business_sphere);
            }

            if ($project->employees_count) {
                $section->addText('Сотрудников: '.$project->employees_count);
            }

            if ($project->current_services) {
                $section->addText('Сервисы: '.$project->current_services);
            }

            if ($project->client_tasks) {
                $section->addText('Задачи клиента: '.$project->client_tasks);
            }

            $section->addTextBreak();
        }

        $this->addAnalyticalReportSection($section, $project);
        $this->addFunnelsSection($section, $project);
        $this->addSkkSection($section, $project);
        $this->addIntegrationsSection($section, $project);
        $this->addWorkStagesSection($section, $project);

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

    private function addAnalyticalReportSection($section, Project $project): void
    {
        $report = app(ProjectAiReportExtractor::class)->latestReport($project);

        if ($report === null || $report === '') {
            return;
        }

        $section->addTitle('Аналитическая записка', 2);
        $this->writeMarkdownLikeText($section, $report);
        $section->addTextBreak();
    }

    private function writeMarkdownLikeText($section, string $text): void
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];

        foreach ($lines as $index => $line) {
            $line = trim((string) preg_replace('/\*\*(.*?)\*\*/u', '$1', $line));

            if ($line === '') {
                if ($index > 0) {
                    $section->addTextBreak();
                }

                continue;
            }

            if (preg_match('/^#{1,3}\s+(.+)$/', $line, $matches)) {
                $section->addTitle($matches[1], 3);

                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/', $line, $matches)) {
                $section->addText('• '.$matches[1]);

                continue;
            }

            $section->addText($line);
        }
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
        $skkByType = $project->skkFields->load('funnelStage.funnel')->groupBy('entity_type');

        foreach (ProjectSkkField::TYPE_LABELS as $type => $label) {
            $section->addText($label, ['bold' => true]);
            $fields = $skkByType->get($type, collect());

            if ($fields->isEmpty()) {
                $section->addText('Поля не добавлены.');
            } else {
                $table = $section->addTable(self::TABLE_STYLE);

                $table->addRow();
                $this->addHeaderCell($table, 2200, 'Название');
                $this->addHeaderCell($table, 1200, 'Тип');
                $this->addHeaderCell($table, 900, 'Обяз.');
                $this->addHeaderCell($table, 1800, 'Где показывать');
                $this->addHeaderCell($table, 1800, 'Значения');
                $this->addHeaderCell($table, 1600, 'Этап воронки');

                foreach ($fields as $field) {
                    $table->addRow();
                    $this->addCellWithMultilineText($table, 2200, $field->name);
                    $this->addCellWithMultilineText(
                        $table,
                        1200,
                        ProjectSkkField::VALUE_TYPE_LABELS[$field->value_type] ?? $field->value_type
                    );
                    $this->addCellWithMultilineText($table, 900, $field->is_required ? 'Да' : 'Нет');
                    $this->addCellWithMultilineText($table, 1800, $this->showInLabel($field));
                    $this->addCellWithMultilineText(
                        $table,
                        1800,
                        $field->options ? implode(', ', $field->options) : null
                    );
                    $stageLabel = $field->funnelStage
                        ? ($field->funnelStage->funnel?->name ?? 'Воронка').' → '.$field->funnelStage->name
                        : null;
                    $this->addCellWithMultilineText($table, 1600, $stageLabel);
                }
            }

            $section->addTextBreak();
        }
    }

    private function showInLabel(ProjectSkkField $field): ?string
    {
        $parts = [];

        if ($field->show_in_card) {
            $parts[] = 'карточка';
        }

        if ($field->show_in_list) {
            $parts[] = 'список';
        }

        if ($field->show_in_filter) {
            $parts[] = 'фильтр';
        }

        return $parts === [] ? null : implode(', ', $parts);
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

    private function addWorkStagesSection($section, Project $project): void
    {
        $section->addTitle('Этапы работ', 2);

        if ($project->workStages->isEmpty()) {
            $section->addText('Этапы работ не добавлены.');

            return;
        }

        $table = $section->addTable(self::TABLE_STYLE);

        $table->addRow();
        $this->addHeaderCell($table, 2200, 'Этап');
        $this->addHeaderCell($table, 4000, 'Состав работ');
        $this->addHeaderCell($table, 1600, 'Срок, раб. дн.');
        $this->addHeaderCell($table, 1700, 'Стоимость');

        foreach ($project->workStages as $stage) {
            $table->addRow();
            $this->addCellWithMultilineText($table, 2200, $stage->name);
            $this->addCellWithMultilineText($table, 4000, $stage->composition);
            $durationLabel = $stage->duration_days !== null
                ? (string) (int) $stage->duration_days
                : null;
            $this->addCellWithMultilineText($table, 1600, $durationLabel);
            $costLabel = $stage->cost !== null
                ? number_format((float) $stage->cost, 2, ',', ' ').' ₽'
                : null;
            $this->addCellWithMultilineText($table, 1700, $costLabel);
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
