<?php

namespace App\Console\Commands;

use App\Services\TrainingProjectService;
use Illuminate\Console\Command;

class EnsureTrainingProjectCommand extends Command
{
    protected $signature = 'f5:ensure-training-project';

    protected $description = 'Create or restore the shared training project used by the product tour';

    public function handle(TrainingProjectService $trainingProjects): int
    {
        $project = $trainingProjects->ensure();

        $this->info('Training project ready.');
        $this->line('ID:   '.$project->id);
        $this->line('Name: '.$project->company_name);
        $this->line('URL:  '.route('projects.show', $project));

        return self::SUCCESS;
    }
}
