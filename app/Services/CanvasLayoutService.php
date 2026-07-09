<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectCanvasBlock;
use App\Models\ProjectSkkField;
use Illuminate\Support\Collection;

class CanvasLayoutService
{
    public function syncBlocks(Project $project): Collection
    {
        $project->loadMissing(['funnels', 'integrations', 'skkFields']);

        $definitions = $this->blockDefinitions($project);
        $existing = $project->canvasBlocks()->get()->keyBy('block_key');

        foreach ($definitions as $key => $definition) {
            if ($existing->has($key)) {
                $update = [
                    'title' => $definition['title'],
                    'block_type' => $definition['block_type'],
                    'reference_id' => $definition['reference_id'],
                ];

                if ($definition['block_type'] === ProjectCanvasBlock::TYPE_FUNNEL) {
                    $update['width'] = $definition['width'];
                }

                $existing[$key]->update($update);

                continue;
            }

            $project->canvasBlocks()->create([
                'block_key' => $key,
                'block_type' => $definition['block_type'],
                'reference_id' => $definition['reference_id'],
                'title' => $definition['title'],
                'pos_x' => $definition['pos_x'],
                'pos_y' => $definition['pos_y'],
                'width' => $definition['width'],
                'z_index' => $definition['z_index'],
            ]);
        }

        $validKeys = array_keys($definitions);

        $project->canvasBlocks()
            ->whereNotIn('block_key', $validKeys)
            ->delete();

        return $project->canvasBlocks()
            ->orderBy('z_index')
            ->orderBy('id')
            ->get();
    }

    private function blockDefinitions(Project $project): array
    {
        $definitions = [
            'info' => [
                'block_type' => ProjectCanvasBlock::TYPE_INFO,
                'reference_id' => null,
                'title' => 'Основная информация',
                'pos_x' => 80,
                'pos_y' => 80,
                'width' => 360,
                'z_index' => 1,
            ],
            'integrations' => [
                'block_type' => ProjectCanvasBlock::TYPE_INTEGRATIONS,
                'reference_id' => null,
                'title' => 'Интеграции и виджеты',
                'pos_x' => 80,
                'pos_y' => 480,
                'width' => 360,
                'z_index' => 5,
            ],
        ];

        $skkPositions = [
            ProjectSkkField::TYPE_DEALS => ['x' => 460, 'y' => 80, 'z' => 2],
            ProjectSkkField::TYPE_CONTACTS => ['x' => 800, 'y' => 80, 'z' => 3],
            ProjectSkkField::TYPE_COMPANIES => ['x' => 1140, 'y' => 80, 'z' => 4],
        ];

        foreach (ProjectSkkField::TYPE_LABELS as $type => $label) {
            $definitions['skk-'.$type] = [
                'block_type' => ProjectCanvasBlock::TYPE_SKK,
                'reference_id' => null,
                'title' => ProjectSkkField::SECTION_LABEL.' — '.$label,
                'pos_x' => $skkPositions[$type]['x'],
                'pos_y' => $skkPositions[$type]['y'],
                'width' => 360,
                'z_index' => $skkPositions[$type]['z'],
                'entity_type' => $type,
            ];
        }

        $funnelY = 480;
        $funnelX = 460;
        $funnelGap = 560;

        foreach ($project->funnels as $index => $funnel) {
            $definitions['funnel-'.$funnel->id] = [
                'block_type' => ProjectCanvasBlock::TYPE_FUNNEL,
                'reference_id' => $funnel->id,
                'title' => $funnel->name,
                'pos_x' => $funnelX + ($index * $funnelGap),
                'pos_y' => $funnelY,
                'width' => 520,
                'z_index' => 10 + $index,
            ];
        }

        return $definitions;
    }

    public function entityTypeForBlock(ProjectCanvasBlock $block): ?string
    {
        if ($block->block_type !== ProjectCanvasBlock::TYPE_SKK) {
            return null;
        }

        if (! str_starts_with($block->block_key, 'skk-')) {
            return null;
        }

        return substr($block->block_key, 4);
    }
}
