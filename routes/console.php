<?php

use App\Models\ProjectAmocrmAuditExport;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Выгрузки amoCRM для аудита временные — удаляем их через сутки после записи.
// Сам проект и его данные не затрагиваются.
Schedule::command('model:prune', ['--model' => ProjectAmocrmAuditExport::class])
    ->daily()
    ->name('prune-amocrm-audit-exports')
    ->withoutOverlapping();
