<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Services\AiKpPresentationService;
use Tests\TestCase;

class AiKpPresentationTest extends TestCase
{
    private function invoke(string $method, array $args): mixed
    {
        $service = app(AiKpPresentationService::class);
        $reflection = new \ReflectionMethod($service, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($service, $args);
    }

    public function test_normalize_deck_forces_title_and_contacts_and_sanitizes_palette(): void
    {
        $project = new Project(['company_name' => 'ООО Ромашка']);

        $deck = $this->invoke('normalizeDeck', [[
            'palette' => [
                'primary' => '1a2b3c',
                'brand' => 'not-a-color',
                'accent' => 'ffd166',
            ],
            'slides' => [
                ['type' => 'unknown_type', 'title' => 'Пропадёт'],
                ['type' => 'cards', 'kicker' => 'КТО МЫ', 'title' => 'О нас', 'cards' => [
                    ['title' => 'а', 'text' => 'б'],
                ]],
            ],
        ], $project]);

        // Валидные цвета приняты, невалидный brand заменён дефолтом
        $this->assertSame('FF1A2B3C', $deck['theme']['ink']);
        $this->assertSame('FF005FF9', $deck['theme']['brand']);
        $this->assertSame('FFFFD166', $deck['theme']['accent']);
        // Производные оттенки вычислены
        $this->assertMatchesRegularExpression('/^FF[0-9A-F]{6}$/', $deck['theme']['tint']);
        $this->assertMatchesRegularExpression('/^FF[0-9A-F]{6}$/', $deck['theme']['surface']);
        $this->assertMatchesRegularExpression('/^FF[0-9A-F]{6}$/', $deck['theme']['onDarkMuted']);

        $this->assertSame('title', $deck['slides'][0]['type']);
        $this->assertSame('cards', $deck['slides'][1]['type']);
        $this->assertSame('contacts', $deck['slides'][array_key_last($deck['slides'])]['type']);
        $this->assertCount(3, $deck['slides']);
    }

    public function test_render_pptx_produces_valid_presentation_with_design_system_geometry(): void
    {
        $project = new Project(['company_name' => 'ООО Ромашка']);

        $deck = $this->invoke('normalizeDeck', [[
            'palette' => [
                'primary' => '0D1B3E',
                'brand' => '005FF9',
                'accent' => 'FF9E2C',
            ],
            'slides' => [
                ['type' => 'title', 'title' => 'Внедрение amoCRM', 'subtitle' => 'для ООО Ромашка', 'lines' => ['Подготовлено для: Ромашка', 'F5 · 29.09.2026']],
                ['type' => 'cards', 'kicker' => 'КТО МЫ', 'title' => 'О компании', 'lead' => 'Внедрение и сопровождение', 'cards' => [
                    ['title' => 'Внедрение CRM', 'text' => 'Воронки и автозадачи'],
                    ['title' => 'Интеграции', 'text' => 'Мессенджеры и телефония'],
                    ['title' => 'Сопровождение', 'text' => 'Обучаем команду'],
                ], 'band' => ['title' => 'Партнёр amoCRM', 'text' => 'Тестовый аккаунт']],
                ['type' => 'bullets', 'kicker' => 'ВВОДНЫЕ', 'title' => 'Как мы поняли задачу', 'panel_title' => 'Модель бизнеса', 'bullets' => ['пункт 1', 'пункт 2'], 'highlight' => ['kicker' => 'Цель', 'items' => ['Найти', 'Зарегистрировать'], 'note' => 'пояснение'], 'band' => 'договорённость'],
                ['type' => 'problems', 'kicker' => 'ПРОБЛЕМЫ', 'title' => 'Что мешает', 'cards' => [
                    ['title' => 'Нет учёта', 'text' => '...'],
                    ['title' => 'Потеря контактов', 'text' => '...'],
                    ['title' => 'Нет контроля', 'text' => '...'],
                    ['title' => 'Хаос', 'text' => '...'],
                ], 'band' => 'Каждая потеря — деньги'],
                ['type' => 'process', 'kicker' => 'СТРУКТУРА CRM', 'title' => 'Воронки', 'rows' => [
                    ['label' => 'Воронка 1', 'steps' => [
                        ['title' => 'Новое', 'sub' => 'обращение'],
                        ['title' => 'Квалификация', 'sub' => 'город'],
                        ['title' => 'Успех', 'sub' => ''],
                    ]],
                    ['label' => 'Воронка 2', 'steps' => [
                        ['title' => 'Контроль', 'sub' => '7 дней'],
                        ['title' => 'Реактивация', 'sub' => ''],
                    ]],
                ], 'panel' => ['title' => 'Критерий успеха', 'text' => 'настраивается']],
                ['type' => 'stats', 'kicker' => 'РЕЗУЛЬТАТ', 'title' => 'Эффект', 'stats' => [
                    ['value' => '0', 'label' => 'потерянных заявок'],
                    ['value' => '100%', 'label' => 'контроль'],
                    ['value' => '24/7', 'label' => 'автоответы'],
                ], 'panel' => ['title' => 'Эффект для компании', 'bullets' => ['рост конверсии', 'масштабирование']]],
                ['type' => 'table', 'kicker' => 'СМЕТА', 'title' => 'Состав работ', 'headers' => ['Этап', 'Срок', 'Цена'], 'rows' => [
                    ['Аналитика', '5 дн', '50 000 ₽'],
                    ['Настройка', '10 дн', '120 000 ₽'],
                ], 'price' => ['value' => '170 000 ₽', 'label' => 'разовая настройка'], 'note' => ['title' => '+ лицензии', 'text' => 'по тарифам вендора']],
                ['type' => 'timeline', 'kicker' => 'ШАГИ', 'title' => 'Как стартуем', 'steps' => [
                    ['kicker' => 'СЕГОДНЯ', 'title' => 'КП', 'text' => 'отправляем'],
                    ['kicker' => 'ЗАВТРА', 'title' => 'Встреча', 'text' => 'обсуждаем'],
                    ['kicker' => 'ДАЛЕЕ', 'title' => 'Старт', 'text' => 'подключаем'],
                    ['kicker' => 'ФИНАЛ', 'title' => 'Запуск', 'text' => 'работаем'],
                ], 'note' => 'Будем рады вопросам'],
                ['type' => 'twocol', 'kicker' => 'ПЛАН', 'title' => 'Поэтапное внедрение', 'left' => ['title' => 'Этап 1', 'bullets' => ['а', 'б'], 'result' => 'Результат: порядок'], 'right' => ['title' => 'Этап 2', 'bullets' => ['в', 'г'], 'result' => 'Результат: автоматизация'], 'note' => 'сначала быстрый результат'],
            ],
        ], $project]);

        $path = $this->invoke('renderPptx', [$project, $deck]);

        try {
            $this->assertFileExists($path);
            $this->assertGreaterThan(1000, filesize($path));

            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path) === true);

            $slideNames = array_filter(
                $this->zipNames($zip),
                fn ($name) => preg_match('#^ppt/slides/slide\d+\.xml$#', $name) === 1
            );
            // 9 слайдов из запроса + contacts, добавленный нормализатором
            $this->assertCount(10, $slideNames);

            // Холст 13.33x7.5in
            $presXml = $zip->getFromName('ppt/presentation.xml');
            $this->assertStringContainsString('cx="12192000"', $presXml);
            $this->assertStringContainsString('cy="6858000"', $presXml);

            // Титульный слайд: скруглённые декоративные плашки
            $slide1 = $zip->getFromName('ppt/slides/slide1.xml');
            $this->assertStringContainsString('prst="roundRect"', $slide1);

            // Процессы: шевроны с центрированным текстом
            $slide5 = $zip->getFromName('ppt/slides/slide5.xml');
            $this->assertStringContainsString('prst="chevron"', $slide5);
            $this->assertStringContainsString('anchor="ctr"', $slide5);

            // Таймлайн: круги с номерами
            $slide8 = $zip->getFromName('ppt/slides/slide8.xml');
            $this->assertStringContainsString('prst="ellipse"', $slide8);

            // После патча не должно остаться «лишних» rect-фигур сверх учтённых:
            // каждый слайд парсится как валидный XML
            foreach ($slideNames as $name) {
                $xml = $zip->getFromName($name);
                $this->assertNotFalse(simplexml_load_string($xml), "Слайд {$name} невалиден после патча геометрии");
            }

            $zip->close();
        } finally {
            @unlink($path);
        }
    }

    /** @return list<string> */
    private function zipNames(\ZipArchive $zip): array
    {
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }

        return $names;
    }
}
