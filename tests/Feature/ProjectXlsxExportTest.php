<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectIntegration;
use App\Models\ProjectWorkStage;
use App\Models\User;
use App\Services\GoogleDocsExportService;
use App\Services\ProjectXlsxExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ProjectXlsxExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            $this->markTestSkipped('RefreshDatabase must not run against the development MySQL database.');
        }

        parent::setUp();

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost/google/callback',
        ]);
    }

    public function test_user_can_download_project_xlsx_estimate(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
            'employees_count' => 8,
        ]);

        ProjectWorkStage::create([
            'project_id' => $project->id,
            'name' => 'Аналитика',
            'composition' => "Интервью\nСхема процессов",
            'cost' => 49990,
            'duration_days' => 5,
            'sort_order' => 1,
        ]);

        ProjectWorkStage::create([
            'project_id' => $project->id,
            'name' => 'Настройка',
            'composition' => "Права доступа\nОбучение",
            'cost' => 30000,
            'duration_days' => 10,
            'sort_order' => 2,
        ]);

        ProjectIntegration::create([
            'project_id' => $project->id,
            'name' => 'Chatter',
            'widget_slug' => 'chatter',
            'billing_quantity' => 2,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.export.xlsx', $project));

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml.sheet',
            (string) $response->headers->get('content-type')
        );

        $temp = tempnam(sys_get_temp_dir(), 'xlsx_test_');
        file_put_contents($temp, $response->streamedContent());

        $spreadsheet = IOFactory::load($temp);
        $sheet = $spreadsheet->getActiveSheet();
        $values = $sheet->toArray(null, true, true, true);

        $this->assertStringContainsString('№', (string) ($values[1]['A'] ?? ''));
        $this->assertSame('Стоимость работ', (string) ($values[1]['D'] ?? ''));
        $this->assertNotSame('Виджеты - ПО', (string) ($values[1]['D'] ?? ''));
        $flat = collect($values)->flatten()->filter()->implode(' | ');
        $this->assertStringContainsString('Аналитика', $flat);
        $this->assertStringContainsString('1) Интервью', $flat);
        $this->assertStringContainsString('2) Схема процессов', $flat);
        $this->assertStringContainsString('1) Права доступа', $flat);
        $this->assertStringContainsString('2) Обучение', $flat);
        $this->assertStringContainsString('5 раб. дней', $flat);
        $this->assertStringContainsString('10 раб. дней', $flat);
        $this->assertStringContainsString('Лицензия amoCRM', $flat);
        $this->assertStringContainsString('Тариф amoCRM не выбран', $flat);
        $this->assertStringContainsString('Chatter', $flat);
        $this->assertStringContainsString('6+1', $flat);
        $this->assertStringContainsString('10+3', $flat);
        $this->assertStringContainsString('Вариант 2', $flat);
        $this->assertStringContainsString('Вариант 3', $flat);
        $this->assertStringContainsString('Тариф Base', $flat);
        $this->assertStringContainsString('Безлимитная подписка', $flat);

        // Chatter: 3500 × 2 канала × 6 / × 10
        $this->assertStringContainsString('42,000.00', $flat);
        $this->assertStringContainsString('70,000.00', $flat);

        $chatterUrl = null;
        foreach ($sheet->getRowIterator() as $excelRow) {
            foreach ($excelRow->getCellIterator('B', 'B') as $cell) {
                if ((string) $cell->getValue() === 'Chatter' && $cell->hasHyperlink()) {
                    $chatterUrl = $cell->getHyperlink()->getUrl();
                    break 2;
                }
            }
        }
        $this->assertNotNull($chatterUrl);
        $this->assertStringContainsString('cmdf5.ru', $chatterUrl);

        // Unlimited tariffs use Excel formulas (price × users × 7 / × 12)
        $foundFormula = false;
        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator('E', 'F') as $cell) {
                $value = (string) $cell->getValue();
                if (str_starts_with($value, '=C') && str_contains($value, '*D') && str_contains($value, '*7')) {
                    $foundFormula = true;
                    break 2;
                }
            }
        }
        $this->assertTrue($foundFormula, 'Expected unlimited subscription Excel formulas in columns E/F');
        $this->assertStringContainsString('на 7 месяцев', $flat);
        $this->assertStringContainsString('12+1', $flat);
        $this->assertStringContainsString('на 13 месяцев', $flat);

        $foundThirteen = false;
        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator('F', 'F') as $cell) {
                $value = (string) $cell->getValue();
                if (str_starts_with($value, '=C') && str_ends_with($value, '*13')) {
                    $foundThirteen = true;
                    break 2;
                }
            }
        }
        $this->assertTrue($foundThirteen, 'Expected Variant 3 long-period formula to multiply by 13');

        @unlink($temp);
    }

    public function test_xlsx_includes_only_selected_amocrm_license_tariff(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
            'employees_count' => 8,
            'amocrm_license_tariff' => 'advanced',
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.export.xlsx', $project));

        $response->assertOk();

        $temp = tempnam(sys_get_temp_dir(), 'xlsx_lic_');
        file_put_contents($temp, $response->streamedContent());

        $spreadsheet = IOFactory::load($temp);
        $flat = collect($spreadsheet->getActiveSheet()->toArray(null, true, true, true))
            ->flatten()
            ->filter()
            ->implode(' | ');

        $this->assertStringContainsString('Лицензия amoCRM', $flat);
        $this->assertStringContainsString('Расширенный', $flat);
        $this->assertStringNotContainsString('Базовый', $flat);
        $this->assertStringNotContainsString('Профессиональный', $flat);
        $this->assertStringNotContainsString('Тариф amoCRM не выбран', $flat);

        @unlink($temp);
    }

    public function test_exporter_filename_contains_company(): void
    {
        $project = new Project(['company_name' => 'Acme Soft']);
        $filename = app(ProjectXlsxExporter::class)->filename($project);

        $this->assertStringEndsWith('_состав_работ_и_смета.xlsx', $filename);
        $this->assertStringContainsString('Acme', $filename);
    }

    public function test_export_google_sheets_requires_connect(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $this->actingAs($user)
            ->postJson(route('projects.export.google-sheets', $project))
            ->assertStatus(409)
            ->assertJsonPath('needs_google_connect', true);
    }

    public function test_export_google_sheets_success(): void
    {
        $user = User::factory()->create([
            'google_id' => 'gid-1',
            'google_email' => 'user@gmail.com',
            'google_access_token' => json_encode(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'google_refresh_token' => 'refresh-test',
            'google_token_expires_at' => now()->addHour(),
        ]);

        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $this->mock(GoogleDocsExportService::class, function ($mock) {
            $mock->shouldReceive('uploadXlsxAsGoogleSheet')
                ->once()
                ->withArgs(fn ($user, $path, $title, $existingId) => $existingId === null)
                ->andReturn([
                    'id' => 'sheet-123',
                    'name' => 'Смета',
                    'url' => 'https://docs.google.com/spreadsheets/d/sheet-123/edit',
                    'updated' => false,
                ]);
        });

        $this->actingAs($user)
            ->postJson(route('projects.export.google-sheets', $project))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('document.id', 'sheet-123')
            ->assertJsonPath('document.source', 'estimate')
            ->assertJsonPath('document.updated', false);

        $this->assertDatabaseHas('project_google_documents', [
            'project_id' => $project->id,
            'google_file_id' => 'sheet-123',
            'source' => 'estimate',
        ]);
    }

    public function test_export_google_sheets_updates_existing(): void
    {
        $user = User::factory()->create([
            'google_id' => 'gid-1',
            'google_email' => 'user@gmail.com',
            'google_access_token' => json_encode(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'google_refresh_token' => 'refresh-test',
            'google_token_expires_at' => now()->addHour(),
        ]);

        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        \App\Models\ProjectGoogleDocument::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'google_file_id' => 'sheet-existing',
            'name' => 'Старая смета',
            'url' => 'https://docs.google.com/spreadsheets/d/sheet-existing/edit',
            'source' => 'estimate',
        ]);

        $this->mock(GoogleDocsExportService::class, function ($mock) {
            $mock->shouldReceive('uploadXlsxAsGoogleSheet')
                ->once()
                ->withArgs(fn ($user, $path, $title, $existingId) => $existingId === 'sheet-existing')
                ->andReturn([
                    'id' => 'sheet-existing',
                    'name' => 'Смета',
                    'url' => 'https://docs.google.com/spreadsheets/d/sheet-existing/edit',
                    'updated' => true,
                ]);
        });

        $this->actingAs($user)
            ->postJson(route('projects.export.google-sheets', $project))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('document.id', 'sheet-existing')
            ->assertJsonPath('document.updated', true)
            ->assertJsonPath('message', 'Таблица обновлена в Google Sheets.');

        $this->assertSame(1, \App\Models\ProjectGoogleDocument::query()
            ->where('project_id', $project->id)
            ->where('source', 'estimate')
            ->count());
    }
}
