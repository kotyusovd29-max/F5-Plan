<?php

namespace App\Services;

use App\Exceptions\F5AiException;
use App\Exceptions\GoogleDocsException;
use App\Models\Project;
use App\Models\User;
use App\Support\F5AiModels;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use PhpOffice\PhpPresentation\DocumentLayout;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Shape\RichText;
use PhpOffice\PhpPresentation\Slide;
use PhpOffice\PhpPresentation\Slide\Background\Color as BgColor;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Style\Fill;

/**
 * КП без шаблона: ИИ придумывает содержание и палитру (3 цвета под сферу клиента),
 * сайт рендерит PPTX по фиксированной дизайн-системе (каркас слайдов, скруглённые
 * карточки, шевроны, таймлайн) и конвертирует в Google Slides через Drive.
 *
 * Скругления/шевроны/круги получаем пост-обработкой XML готового pptx:
 * PhpPresentation пишет только prst="rect", патчер заменяет пресет геометрии
 * на roundRect/chevron/ellipse по порядку создания фигур.
 */
class AiKpPresentationService
{
    private const SLIDES_MIME = 'application/vnd.google-apps.presentation';

    private const PPTX_MIME = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';

    /** Холст 13.33x7.5in @ 96dpi — стандартный слайд 16:9. */
    private const W = 1280;

    private const H = 720;

    private const M = 58; // боковое поле

    private const CW = 1164; // ширина контента (W - 2*M)

    private const FONT = 'Arial';

    private const FOOTER_TEXT = 'Команда F5 · Коммерческое предложение по внедрению amoCRM';

    /** Палитра по умолчанию (фирменная F5), если ИИ не прислал валидную. */
    private const DEFAULT_PALETTE = [
        'primary' => '0D1B3E',
        'brand' => '005FF9',
        'accent' => 'FF9E2C',
    ];

    private const SLIDE_TYPES = [
        'title', 'cards', 'bullets', 'problems', 'process', 'stats', 'table', 'timeline', 'twocol', 'contacts',
    ];

    /**
     * Пресеты геометрии RichText-фигур по слайдам: slideIndex => list<array{preset, ctr}>.
     * Порядок строго совпадает с порядком создания фигур — патчер идёт по XML последовательно.
     *
     * @var array<int, list<array{preset: string, ctr: bool}>>
     */
    private array $geometry = [];

    private int $slideIndex = -1;

    public function __construct(
        private readonly F5AiService $f5AiService,
        private readonly ProjectAiContextBuilder $contextBuilder,
        private readonly GoogleOAuthService $oauth
    ) {}

    /**
     * @return array{file_id: string, name: string, url: string, pptx_path: string}
     */
    public function build(Project $project, User $user, ?int $userId = null, ?string $brief = null): array
    {
        @set_time_limit(300);

        $deck = $this->designDeck($project, $userId, $brief);
        $pptxPath = $this->renderPptx($project, $deck);

        $name = 'КП '.$project->company_name.' · '.now()->format('d.m.Y');

        try {
            $drive = new Drive($this->oauth->clientForUser($user));
            $file = $drive->files->create(
                new DriveFile([
                    'name' => $name,
                    'mimeType' => self::SLIDES_MIME,
                ]),
                [
                    'data' => file_get_contents($pptxPath),
                    'mimeType' => self::PPTX_MIME,
                    'uploadType' => 'multipart',
                    'fields' => 'id,name',
                ]
            );
        } catch (\Throwable $exception) {
            @unlink($pptxPath);

            throw new GoogleDocsException(
                'Не удалось загрузить презентацию в Google Slides: '.$exception->getMessage()
            );
        }

        $fileId = (string) $file->id;

        return [
            'file_id' => $fileId,
            'name' => $name,
            'url' => 'https://docs.google.com/presentation/d/'.$fileId.'/edit',
            'pptx_path' => $pptxPath,
        ];
    }

    /**
     * ИИ-дизайнер: палитра (3 цвета) + содержание слайдов.
     *
     * @return array{theme: array<string, string>, slides: list<array<string, mixed>>}
     */
    private function designDeck(Project $project, ?int $userId, ?string $brief): array
    {
        $context = $this->contextBuilder->build($project);
        $brief = trim((string) $brief);

        if ($brief !== '') {
            $context['meeting_notes'] = mb_substr($brief, 0, 8000);
        }

        $system = <<<'PROMPT'
Ты — пресейл-архитектор и арт-директор команды F5 (внедрение amoCRM). Собери коммерческое предложение как презентацию: продумай содержание слайдов и подбери палитру под сферу клиента. Дизайн-каркас фиксированный (светлый, корпоративный) — ты выбираешь только 3 цвета и содержание.

Ответь ТОЛЬКО валидным JSON без markdown:
{
  "palette": {
    "primary": "0D1B3E",
    "brand": "005FF9",
    "accent": "FF9E2C"
  },
  "slides": [
    {"type": "title", "title": "Автоматизация продаж на базе amoCRM", "subtitle": "Единая система: от первого обращения до повторной продажи", "lines": ["Подготовлено для: ООО «Компания»", "По итогам встречи 21 сентября 2026 г.", "Имя Фамилия · системный аналитик, «Команда F5»"]},
    {"type": "cards", "kicker": "КТО МЫ", "title": "О компании «Команда F5»", "lead": "Специализируемся на внедрении CRM, доработках и сопровождении", "cards": [{"title": "Внедрение CRM", "text": "Проектируем воронки, поля и автозадачи под реальный процесс"}], "band": {"title": "Партнёр amoCRM", "text": "Тестовый аккаунт и прозрачные условия по лицензиям"}},
    {"type": "bullets", "kicker": "ВВОДНЫЕ СО ВСТРЕЧИ", "title": "Как мы поняли вашу задачу", "panel_title": "Модель бизнеса", "bullets": ["пункт"], "highlight": {"kicker": "КЛЮЧЕВАЯ ЦЕЛЬ", "items": ["Найти кандидата", "Зарегистрировать", "Вывести на заказы"], "note": "пояснение"}, "band": "важная договорённость"},
    {"type": "problems", "kicker": "ЧТО МЕШАЕТ ЗАРАБАТЫВАТЬ", "title": "Проблемы текущего процесса", "cards": [{"title": "Нет учёта", "text": "описание боли"}], "band": "вывод: чего это стоит бизнесу"},
    {"type": "process", "kicker": "СТРУКТУРА CRM", "title": "Воронка под ваш процесс", "rows": [{"label": "Воронка 1 · «Продажи»", "steps": [{"title": "Новое обращение", "sub": "подпись"}]}], "panel": {"title": "Критерий успешной сделки", "text": "..."}},
    {"type": "stats", "kicker": "РЕЗУЛЬТАТ ВНЕДРЕНИЯ", "title": "Что получит ваш бизнес", "stats": [{"value": "0", "label": "потерянных заявок"}], "panel": {"title": "Эффект для компании", "bullets": ["пункт"]}},
    {"type": "table", "kicker": "СМЕТА", "title": "Состав работ и стоимость", "headers": ["Этап", "Срок", "Цена"], "rows": [["Аналитика", "5 дн", "50 000 ₽"]], "price": {"value": "49 990 ₽", "label": "разовая настройка"}, "note": {"title": "+ лицензии amoCRM", "text": "по тарифам вендора"}},
    {"type": "timeline", "kicker": "СЛЕДУЮЩИЕ ШАГИ", "title": "Как стартуем", "steps": [{"kicker": "СЕГОДНЯ", "title": "Детализированное КП", "text": "..."}], "note": "призыв к действию"},
    {"type": "twocol", "kicker": "ПЛАН РАБОТ", "title": "Поэтапное внедрение", "left": {"title": "Этап 1 · Базовая настройка", "bullets": ["пункт"], "result": "Результат: ..."}, "right": {"title": "Этап 2 · Автоматизация", "bullets": ["пункт"], "result": "Результат: ..."}},
    {"type": "contacts", "title": "Спасибо за встречу!", "subtitle": "Готовы ответить на вопросы и начать с тестового доступа.", "name": "Имя Фамилия", "role": "Системный аналитик, «Команда F5»", "lines": ["Телефон: ...", "Telegram: ...", "E-mail: ..."]}
  ]
}

Правила:
- palette: цвета HEX без «#». primary — глубокий тёмный (не чёрный): фон первого/последнего слайда, заголовки. brand — насыщенный основной: полосы, кикеры, шевроны, цифры. accent — яркий и контрастный к обоим (к холодным — тёплый и наоборот): ценовые плашки, ключевые акценты. Палитра должна отражать сферу клиента (ювелирка — графит/золото, медицина — индиго/бирюза, логистика — синий/оранжевый).
- 8–12 слайдов. Первый — title, последний — contacts. Обязательно: problems (боли клиента), process (воронка), table (смета), timeline (шаги).
- Лимиты: cards 2–4 шт (title ≤ 30 символов, text ≤ 110); problems ровно 4 карточки; bullets 3–6 пунктов ≤ 110 символов; process 1–2 ряда по 3–5 шагов (title ≤ 22, sub ≤ 26); stats 3–4 цифры (value ≤ 7 символов); table 2–4 колонки, до 7 строк; timeline 3–4 шага (text ≤ 130); twocol 4–6 пунктов в колонке.
- Цены и сроки — только из контекста проекта (work_stages, integrations). Если цен нет — «по запросу». Не выдумывай числа.
- Если в контексте есть meeting_notes (резюме встречи/пожелания клиента) — это главный источник содержания: боли, задачи, ожидания клиента.
- kicker — 2–4 слова КАПСОМ. Заголовки короткие и ёмкие. Пиши по-русски, конкретно, без воды. Каждый слайд — новая мысль.
PROMPT;

        $content = $this->f5AiService->chatCompletion([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => "Контекст проекта:\n".json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)],
        ], [
            'user' => F5AiModels::resolveUser($project, $userId),
            'large' => true,
            'temperature' => 0.6,
            'timeout' => (int) config('f5ai.timeout', 300),
        ]);

        $decoded = json_decode($content, true);
        if (! is_array($decoded) && preg_match('/\{.*\}/s', $content, $m) === 1) {
            $decoded = json_decode($m[0], true);
        }

        if (! is_array($decoded) || ! is_array($decoded['slides'] ?? null)) {
            throw new F5AiException('ИИ не подготовил структуру презентации. Попробуйте ещё раз.');
        }

        return $this->normalizeDeck($decoded, $project);
    }

    /**
     * @param  array<string, mixed>  $deck
     * @return array{theme: array<string, string>, slides: list<array<string, mixed>>}
     */
    private function normalizeDeck(array $deck, Project $project): array
    {
        $palette = self::DEFAULT_PALETTE;
        foreach (array_keys($palette) as $key) {
            $value = (string) ($deck['palette'][$key] ?? '');
            if (preg_match('/^[0-9A-Fa-f]{6}$/', $value) === 1) {
                $palette[$key] = strtoupper($value);
            }
        }

        $slides = [];
        foreach ($deck['slides'] as $spec) {
            if (! is_array($spec)) {
                continue;
            }
            $type = (string) ($spec['type'] ?? '');
            if (! in_array($type, self::SLIDE_TYPES, true)) {
                continue;
            }
            $slides[] = $spec;
        }

        if (($slides[0]['type'] ?? null) !== 'title') {
            array_unshift($slides, [
                'type' => 'title',
                'title' => 'Коммерческое предложение',
                'subtitle' => 'для '.$project->company_name,
                'lines' => ['Команда F5 · '.now()->format('d.m.Y')],
            ]);
        }

        if (($slides[array_key_last($slides)]['type'] ?? null) !== 'contacts') {
            $slides[] = [
                'type' => 'contacts',
                'title' => 'Спасибо за внимание!',
                'subtitle' => 'Готовы ответить на вопросы.',
                'name' => 'Команда F5',
                'role' => 'Внедрение amoCRM',
                'lines' => [],
            ];
        }

        if (count($slides) > 13) {
            $contacts = array_pop($slides);
            $slides = array_slice($slides, 0, 12);
            $slides[] = $contacts;
        }

        return ['theme' => $this->makeTheme($palette), 'slides' => array_values($slides)];
    }

    /**
     * Полная тема дизайн-системы из 3 базовых цветов:
     * все оттенки выводим программно, чтобы сочетания были гармоничными.
     *
     * @param  array{primary: string, brand: string, accent: string}  $palette
     * @return array<string, string>
     */
    private function makeTheme(array $palette): array
    {
        $primary = 'FF'.$palette['primary'];
        $brand = 'FF'.$palette['brand'];
        $accent = 'FF'.$palette['accent'];

        $success = self::isGreenish($brand) ? 'FF0E8AC8' : 'FF12A57C';

        return [
            'bg' => 'FFFFFFFF',
            'ink' => $primary,
            'body' => self::mix($primary, 'FFFFFF', 0.16),
            'muted' => self::mix($primary, 'FFFFFF', 0.45),
            'faint' => self::mix($primary, 'FFFFFF', 0.12),
            'surface' => self::mix($primary, 'FFFFFF', 0.05),
            'tint' => self::mix($brand, 'FFFFFF', 0.10),
            'brand' => $brand,
            'brandLight' => self::mix($brand, 'FFFFFF', 0.70),
            'brandPale' => self::mix($brand, 'FFFFFF', 0.85),
            'accent' => $accent,
            'accentInk' => self::mix($accent, '000000', 0.55),
            'accentTint' => self::mix($accent, 'FFFFFF', 0.12),
            'onDark' => 'FFFFFFFF',
            'onDarkMuted' => self::mix($primary, 'FFFFFF', 0.20),
            'onDarkFaint' => self::mix($primary, 'FFFFFF', 0.35),
            'darkCard1' => self::mix($primary, 'FFFFFF', 0.09),
            'darkCard2' => self::mix($primary, 'FFFFFF', 0.16),
            'danger' => 'FFD94A4A',
            'dangerTint' => 'FFFCECEC',
            'success' => $success,
            'successTint' => self::mix($success, 'FFFFFF', 0.10),
            'steel' => self::mix($brand, $success, 0.55),
        ];
    }

    /** Смешение двух цветов: результат = $a с весом $wa. */
    private static function mix(string $a, string $b, float $wa): string
    {
        $a = substr($a, -6);
        $b = substr($b, -6);

        $r = (int) round(hexdec(substr($a, 0, 2)) * $wa + hexdec(substr($b, 0, 2)) * (1 - $wa));
        $g = (int) round(hexdec(substr($a, 2, 2)) * $wa + hexdec(substr($b, 2, 2)) * (1 - $wa));
        $bl = (int) round(hexdec(substr($a, 4, 2)) * $wa + hexdec(substr($b, 4, 2)) * (1 - $wa));

        return sprintf('FF%02X%02X%02X', $r, $g, $bl);
    }

    private static function isGreenish(string $argb): bool
    {
        $hex = substr($argb, -6);

        return hexdec(substr($hex, 2, 2)) > hexdec(substr($hex, 0, 2))
            && hexdec(substr($hex, 2, 2)) > hexdec(substr($hex, 4, 2));
    }

    /**
     * @param  array{theme: array<string, string>, slides: list<array<string, mixed>>}  $deck
     */
    private function renderPptx(Project $project, array $deck): string
    {
        $pres = new PhpPresentation;
        $pres->getDocumentProperties()
            ->setCreator('F5 Plan')
            ->setTitle('КП '.$project->company_name);
        $pres->getLayout()->setCX(12192000, DocumentLayout::UNIT_EMU);
        $pres->getLayout()->setCY(6858000, DocumentLayout::UNIT_EMU);
        $pres->removeSlideByIndex();

        $this->geometry = [];

        $theme = $deck['theme'];
        $total = count($deck['slides']);

        foreach ($deck['slides'] as $index => $spec) {
            $this->slideIndex = $index;
            $slide = $pres->createSlide();

            $type = (string) $spec['type'];
            $dark = in_array($type, ['title', 'contacts'], true);
            $this->paintBackground($slide, $dark ? $theme['ink'] : $theme['bg']);

            match ($type) {
                'title' => $this->renderTitle($slide, $spec, $theme),
                'cards' => $this->renderCards($slide, $spec, $theme, $index + 1, $total),
                'bullets' => $this->renderBullets($slide, $spec, $theme, $index + 1, $total),
                'problems' => $this->renderProblems($slide, $spec, $theme, $index + 1, $total),
                'process' => $this->renderProcess($slide, $spec, $theme, $index + 1, $total),
                'stats' => $this->renderStats($slide, $spec, $theme, $index + 1, $total),
                'table' => $this->renderTable($slide, $spec, $theme, $index + 1, $total),
                'timeline' => $this->renderTimeline($slide, $spec, $theme, $index + 1, $total),
                'twocol' => $this->renderTwocol($slide, $spec, $theme, $index + 1, $total),
                default => $this->renderContacts($slide, $spec, $theme),
            };
        }

        $tempFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'f5plan_ai_kp_'.uniqid('', true).'.pptx';
        IOFactory::createWriter($pres, 'PowerPoint2007')->save($tempFile);

        if (! is_file($tempFile) || filesize($tempFile) === 0) {
            throw new F5AiException('Не удалось отрисовать презентацию. Попробуйте ещё раз.');
        }

        $this->patchGeometry($tempFile);

        return $tempFile;
    }

    // ------------------------------------------------------------------
    // Базовые помощники отрисовки
    // ------------------------------------------------------------------

    /** Регистрирует RichText-фигуру и её пресет геометрии для пост-обработки. */
    private function shape(Slide $slide, string $preset = 'rect', bool $ctr = false): RichText
    {
        $this->geometry[$this->slideIndex][] = ['preset' => $preset, 'ctr' => $ctr];

        return $slide->createRichTextShape();
    }

    /** Плашка с заливкой (без текста). */
    private function box(Slide $slide, string $fill, float $x, float $y, float $w, float $h, string $preset = 'rect'): RichText
    {
        $box = $this->shape($slide, $preset)
            ->setOffsetX($x)->setOffsetY($y)->setWidth($w)->setHeight($h);
        $box->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($fill));

        return $box;
    }

    /** Текстовый блок без заливки, с нулевыми внутренними отступами. */
    private function text(Slide $slide, float $x, float $y, float $w, float $h): RichText
    {
        $shape = $this->shape($slide)
            ->setOffsetX($x)->setOffsetY($y)->setWidth($w)->setHeight($h);
        $shape->setInsetLeft(0)->setInsetRight(0)->setInsetTop(0)->setInsetBottom(0);

        return $shape;
    }

    /** Один текстовый ран с заданным стилем. */
    private function run(RichText $shape, string $text, float $size, string $color, bool $bold = false): void
    {
        $run = $shape->createTextRun($text === '' ? ' ' : $text);
        $run->getFont()->setName(self::FONT)->setSize($size)->setBold($bold)->setColor(new Color($color));
    }

    private function paintBackground(Slide $slide, string $argb): void
    {
        $bg = new BgColor;
        $bg->setColor(new Color($argb));
        $slide->setBackground($bg);
    }

    /** Каркас контентного слайда: верхние полосы, кикер, заголовок, футер. */
    private function paintFrame(Slide $slide, array $t, string $kicker, string $title, int $num, int $total): void
    {
        $this->box($slide, $t['brand'], 0, 0, self::W, 10);
        $this->box($slide, $t['accent'], 0, 10, 230, 4);

        $k = $this->text($slide, self::M, 29, self::CW, 26);
        $this->run($k, mb_strtoupper(trim($kicker) !== '' ? $kicker : 'F5 · amoCRM'), 11, $t['brand'], true);

        $h = $this->text($slide, self::M, 54, self::CW, 72);
        $this->run($h, trim($title) !== '' ? $title : ' ', 27, $t['ink'], true);

        // Футер
        $this->box($slide, $t['faint'], self::M, 678, self::CW, 2);
        $f = $this->text($slide, self::M, 685, 900, 24);
        $this->run($f, self::FOOTER_TEXT, 9, $t['muted']);
        $n = $this->text($slide, self::W - self::M - 72, 685, 72, 24);
        $n->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $this->run($n, (string) $num, 9, $t['muted']);
    }

    /** Маркированный список с литеральными «•» и межстрочным интервалом. */
    private function fillBullets(RichText $shape, array $items, float $size, string $color, int $max = 7, int $lineSpacing = 130): void
    {
        $first = true;
        foreach (array_slice($items, 0, $max) as $item) {
            $text = trim((string) $item);
            if ($text === '') {
                continue;
            }
            if (! $first) {
                $shape->createParagraph();
            }
            $shape->getActiveParagraph()->setLineSpacing($lineSpacing);
            $this->run($shape, '•  '.$text, $size, $color);
            $first = false;
        }
    }

    // ------------------------------------------------------------------
    // Слайды
    // ------------------------------------------------------------------

    private function renderTitle(Slide $slide, array $spec, array $t): void
    {
        // Акцентная полоса сверху + декоративные скруглённые плашки справа
        $this->box($slide, $t['accent'], 0, 0, self::W, 13);
        $this->box($slide, $t['darkCard1'], 905, -115, 620, 620, 'roundRect');
        $this->box($slide, $t['darkCard2'], 1018, 19, 442, 442, 'roundRect');
        $this->box($slide, $t['brand'], 1070, 72, 336, 336, 'roundRect');

        $k = $this->text($slide, self::M, 149, 912, 38);
        $this->run($k, mb_strtoupper(trim((string) ($spec['kicker'] ?? 'Коммерческое предложение'))), 14, $t['accent'], true);

        $title = $this->text($slide, self::M, 197, 980, 200);
        $this->run($title, trim((string) ($spec['title'] ?? 'Коммерческое предложение')), 40, $t['onDark'], true);

        $subtitle = trim((string) ($spec['subtitle'] ?? ''));
        if ($subtitle !== '') {
            $s = $this->text($slide, self::M, 389, 920, 84);
            $s->getActiveParagraph()->setLineSpacing(125);
            $this->run($s, $subtitle, 15, $t['onDarkMuted']);
        }

        $this->box($slide, $t['accent'], self::M, 494, 134, 3);

        $lines = is_array($spec['lines'] ?? null) ? array_slice($spec['lines'], 0, 4) : [];
        $meta = $this->text($slide, self::M, 523, 1008, 134);
        $meta->getActiveParagraph()->setLineSpacing(140);
        $first = true;
        foreach ($lines as $i => $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            if (! $first) {
                $meta->createBreak();
            }
            $this->run($meta, $line, $i === 0 ? 13.5 : 12, $i === 0 ? $t['onDark'] : $t['onDarkMuted'], $i === 0);
            $first = false;
        }
    }

    private function renderCards(Slide $slide, array $spec, array $t, int $num, int $total): void
    {
        $this->paintFrame($slide, $t, (string) ($spec['kicker'] ?? ''), (string) ($spec['title'] ?? ''), $num, $total);

        $lead = trim((string) ($spec['lead'] ?? ''));
        if ($lead !== '') {
            $l = $this->text($slide, self::M, 139, self::CW, 56);
            $this->run($l, $lead, 14.5, $t['muted']);
        }

        $band = is_array($spec['band'] ?? null) ? $spec['band'] : null;

        $cards = array_values(array_filter(array_map(
            fn ($card) => is_array($card) ? $card : null,
            is_array($spec['cards'] ?? null) ? $spec['cards'] : []
        )));
        if ($cards === []) {
            return;
        }
        $cards = array_slice($cards, 0, 4);
        $count = count($cards);

        $cols = $count === 3 ? 3 : min($count, 2);
        $rows = (int) ceil($count / $cols);
        $gap = 22;

        $y0 = $lead !== '' ? 216 : 150;
        $yMax = $band ? 472 : 660;
        $cardW = (int) ((self::CW - $gap * ($cols - 1)) / $cols);
        $cardH = (int) (($yMax - $y0 - $gap * ($rows - 1)) / $rows);

        $edges = [$t['brand'], $t['steel'], $t['success'], $t['accent']];
        $fills = [$t['tint'], $t['tint'], $t['successTint'], $t['accentTint']];

        foreach ($cards as $i => $card) {
            $col = $i % $cols;
            $row = intdiv($i, $cols);
            $x = self::M + $col * ($cardW + $gap);
            $y = $y0 + $row * ($cardH + $gap);

            $this->box($slide, $fills[$i % 4], $x, $y, $cardW, $cardH, 'roundRect');
            $this->box($slide, $edges[$i % 4], $x, $y + 12, 7, $cardH - 24);

            $label = $this->text($slide, $x + 27, $y + 24, $cardW - 50, 34);
            $this->run($label, trim((string) ($card['title'] ?? '')), 16, $t['ink'], true);

            $body = $this->text($slide, $x + 27, $y + 64, $cardW - 50, $cardH - 88);
            $body->getActiveParagraph()->setLineSpacing(125);
            $this->run($body, trim((string) ($card['text'] ?? '')), 12.5, $t['body']);
        }

        if ($band) {
            $b = $this->shape($slide, 'roundRect', true)
                ->setOffsetX(self::M)->setOffsetY(494)->setWidth(self::CW)->setHeight(130);
            $b->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($t['ink']));
            $b->setInsetLeft(33)->setInsetRight(33);
            $this->run($b, trim((string) ($band['title'] ?? '')), 14, $t['accent'], true);
            $text = trim((string) ($band['text'] ?? ''));
            if ($text !== '') {
                $b->createBreak();
                $this->run($b, $text, 12.5, $t['onDark']);
            }
        }
    }

    private function renderBullets(Slide $slide, array $spec, array $t, int $num, int $total): void
    {
        $this->paintFrame($slide, $t, (string) ($spec['kicker'] ?? ''), (string) ($spec['title'] ?? ''), $num, $total);

        $highlight = is_array($spec['highlight'] ?? null) ? $spec['highlight'] : null;
        $band = trim((string) ($spec['band'] ?? ''));

        $panelW = $highlight ? 706 : self::CW;
        $this->box($slide, $t['surface'], self::M, 144, $panelW, 418, 'roundRect');

        $panelTitle = trim((string) ($spec['panel_title'] ?? ''));
        if ($panelTitle !== '') {
            $pt = $this->text($slide, self::M + 28, 168, $panelW - 56, 34);
            $this->run($pt, $panelTitle, 16, $t['ink'], true);
        }

        $bullets = is_array($spec['bullets'] ?? null) ? $spec['bullets'] : [];
        $list = $this->text($slide, self::M + 28, 214, $panelW - 56, 330);
        $this->fillBullets($list, $bullets, 13, $t['body']);

        if ($highlight) {
            $this->box($slide, $t['brand'], 787, 144, 435, 418, 'roundRect');

            $hk = $this->text($slide, 818, 177, 373, 28);
            $this->run($hk, mb_strtoupper(trim((string) ($highlight['kicker'] ?? 'Ключевая цель'))), 12, $t['accent'], true);

            $items = is_array($highlight['items'] ?? null) ? array_slice($highlight['items'], 0, 4) : [];
            $steps = $this->text($slide, 818, 215, 373, 250);
            $steps->getActiveParagraph()->setLineSpacing(125);
            $first = true;
            foreach ($items as $item) {
                $item = trim((string) $item);
                if ($item === '') {
                    continue;
                }
                if (! $first) {
                    $steps->createBreak();
                    $this->run($steps, '↓', 16, $t['brandPale'], true);
                    $steps->createBreak();
                }
                $this->run($steps, $item, 17, $t['onDark'], true);
                $first = false;
            }

            $note = trim((string) ($highlight['note'] ?? ''));
            if ($note !== '') {
                $n = $this->text($slide, 818, 480, 373, 70);
                $n->getActiveParagraph()->setLineSpacing(120);
                $this->run($n, $note, 11.5, $t['brandPale']);
            }
        }

        if ($band !== '') {
            $b = $this->shape($slide, 'roundRect', true)
                ->setOffsetX(self::M)->setOffsetY(586)->setWidth(self::CW)->setHeight(69);
            $b->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($t['accentTint']));
            $b->setInsetLeft(28)->setInsetRight(28);
            $this->run($b, $band, 12, $t['accentInk'], true);
        }
    }

    private function renderProblems(Slide $slide, array $spec, array $t, int $num, int $total): void
    {
        $this->paintFrame($slide, $t, (string) ($spec['kicker'] ?? ''), (string) ($spec['title'] ?? ''), $num, $total);

        $cards = array_values(array_filter(array_map(
            fn ($card) => is_array($card) ? $card : null,
            is_array($spec['cards'] ?? null) ? $spec['cards'] : []
        )));
        $cards = array_slice($cards, 0, 4);

        $band = trim((string) ($spec['band'] ?? ''));
        $yMax = $band !== '' ? 566 : 660;

        $count = max(count($cards), 1);
        $rows = (int) ceil($count / 2);
        $gap = 22;
        $cardW = 571;
        $cardH = (int) (($yMax - 149 - $gap * ($rows - 1)) / $rows);

        foreach ($cards as $i => $card) {
            $x = self::M + ($i % 2) * ($cardW + $gap);
            $y = 149 + intdiv($i, 2) * ($cardH + $gap);

            $this->box($slide, $t['dangerTint'], $x, $y, $cardW, $cardH, 'roundRect');
            $this->box($slide, $t['danger'], $x, $y + 12, 7, $cardH - 24);

            $label = $this->text($slide, $x + 31, $y + 21, $cardW - 57, 30);
            $this->run($label, trim((string) ($card['title'] ?? '')), 15.5, $t['danger'], true);

            $body = $this->text($slide, $x + 31, $y + 57, $cardW - 57, $cardH - 76);
            $body->getActiveParagraph()->setLineSpacing(125);
            $this->run($body, trim((string) ($card['text'] ?? '')), 12.5, $t['body']);
        }

        if ($band !== '') {
            $b = $this->shape($slide, 'roundRect', true)
                ->setOffsetX(self::M)->setOffsetY(581)->setWidth(self::CW)->setHeight(75);
            $b->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($t['danger']));
            $b->setInsetLeft(28)->setInsetRight(28);
            $this->run($b, $band, 14, $t['onDark'], true);
        }
    }

    private function renderProcess(Slide $slide, array $spec, array $t, int $num, int $total): void
    {
        $this->paintFrame($slide, $t, (string) ($spec['kicker'] ?? ''), (string) ($spec['title'] ?? ''), $num, $total);

        $rows = array_values(array_filter(array_map(
            fn ($row) => is_array($row) ? $row : null,
            is_array($spec['rows'] ?? null) ? $spec['rows'] : []
        )));
        $rows = array_slice($rows, 0, 2);

        $bases = [$t['brand'], $t['success']];

        foreach ($rows as $ri => $row) {
            $labelY = $ri === 0 ? 144 : 331;
            $chevY = $ri === 0 ? 187 : 374;

            $label = $this->text($slide, self::M, $labelY, self::CW, 38);
            $this->run($label, trim((string) ($row['label'] ?? '')), 15, $t['ink'], true);

            $steps = array_values(array_filter(array_map(
                fn ($step) => is_array($step) ? $step : null,
                is_array($row['steps'] ?? null) ? $row['steps'] : []
            )));
            $steps = array_slice($steps, 0, 5);
            $count = count($steps);
            if ($count === 0) {
                continue;
            }

            $gap = 9;
            $w = (int) min(226, (self::CW - $gap * ($count - 1)) / $count);
            $base = $bases[$ri % 2];
            $from = self::mix($base, 'FFFFFF', 0.70);
            $to = $ri === 0 ? self::mix($t['ink'], 'FFFFFF', 0.90) : self::mix($base, '000000', 0.72);

            foreach ($steps as $si => $step) {
                $x = self::M + $si * ($w + $gap);
                $color = $count > 1 ? self::mix($from, $to, 1 - $si / ($count - 1)) : $base;

                $chev = $this->shape($slide, 'chevron', true)
                    ->setOffsetX($x)->setOffsetY($chevY)->setWidth($w)->setHeight(110);
                $chev->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($color));
                $chev->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $chev->getActiveParagraph()->setLineSpacing(110);
                $this->run($chev, trim((string) ($step['title'] ?? '')), 11.5, $t['onDark'], true);
                $sub = trim((string) ($step['sub'] ?? ''));
                if ($sub !== '') {
                    $chev->createBreak();
                    $this->run($chev, $sub, 11.5, $t['onDark']);
                }
            }
        }

        $note = trim((string) ($spec['note'] ?? ''));
        if ($note !== '') {
            $n = $this->text($slide, self::M, 500, self::CW, 34);
            $this->run($n, $note, 11.5, $t['muted']);
        }

        $panel = is_array($spec['panel'] ?? null) ? $spec['panel'] : null;
        if ($panel) {
            $this->box($slide, $t['surface'], self::M, 518, self::CW, 125, 'roundRect');
            $this->box($slide, $t['accent'], self::M, 530, 7, 101);

            $pt = $this->text($slide, self::M + 30, 538, self::CW - 60, 26);
            $this->run($pt, trim((string) ($panel['title'] ?? '')), 13, $t['ink'], true);

            $pb = $this->text($slide, self::M + 30, 566, self::CW - 60, 60);
            $pb->getActiveParagraph()->setLineSpacing(120);
            $this->run($pb, trim((string) ($panel['text'] ?? '')), 12, $t['body']);
        }
    }

    private function renderStats(Slide $slide, array $spec, array $t, int $num, int $total): void
    {
        $this->paintFrame($slide, $t, (string) ($spec['kicker'] ?? ''), (string) ($spec['title'] ?? ''), $num, $total);

        $stats = array_values(array_filter(array_map(
            fn ($stat) => is_array($stat) ? $stat : null,
            is_array($spec['stats'] ?? null) ? $spec['stats'] : []
        )));
        $stats = array_slice($stats, 0, 4);

        $panel = is_array($spec['panel'] ?? null) ? $spec['panel'] : null;

        $count = count($stats);
        if ($count > 0) {
            $gap = 29;
            $w = (int) ((self::CW - $gap * ($count - 1)) / $count);
            $fills = [$t['tint'], $t['surface']];

            foreach ($stats as $i => $stat) {
                $x = self::M + $i * ($w + $gap);

                $this->box($slide, $fills[$i % 2], $x, 154, $w, 221, 'roundRect');

                $value = $this->text($slide, $x + 21, 178, $w - 42, 62);
                $this->run($value, trim((string) ($stat['value'] ?? '—')), 34, $t['brand'], true);

                $label = $this->text($slide, $x + 21, 249, $w - 42, 110);
                $label->getActiveParagraph()->setLineSpacing(120);
                $this->run($label, trim((string) ($stat['label'] ?? '')), 11.5, $t['body']);
            }
        }

        if ($panel) {
            $this->box($slide, $t['surface'], self::M, 403, self::CW, 230, 'roundRect');
            $this->box($slide, $t['success'], self::M, 415, 7, 208);

            $pt = $this->text($slide, self::M + 33, 427, self::CW - 66, 30);
            $this->run($pt, trim((string) ($panel['title'] ?? '')), 15, $t['ink'], true);

            $bullets = is_array($panel['bullets'] ?? null) ? $panel['bullets'] : [];
            $list = $this->text($slide, self::M + 33, 465, self::CW - 66, 150);
            $this->fillBullets($list, $bullets, 12.5, $t['body'], 4, 125);
        }
    }

    private function renderTable(Slide $slide, array $spec, array $t, int $num, int $total): void
    {
        $this->paintFrame($slide, $t, (string) ($spec['kicker'] ?? ''), (string) ($spec['title'] ?? ''), $num, $total);

        $headers = is_array($spec['headers'] ?? null) ? array_values($spec['headers']) : [];
        $rows = is_array($spec['rows'] ?? null) ? $spec['rows'] : [];

        $cols = min(max(count($headers), 2), 4);

        $table = $slide->createTableShape($cols);
        $table->setWidth(self::CW)->setOffsetX(self::M)->setOffsetY(149);

        $widths = match ($cols) {
            2 => [0.62, 0.38],
            3 => [0.50, 0.25, 0.25],
            default => [0.40, 0.20, 0.20, 0.20],
        };

        $headerRow = $table->createRow();
        $headerRow->setHeight(33);
        foreach (array_slice($headers, 0, $cols) as $i => $h) {
            $cell = $headerRow->getCell($i);
            $cell->setWidth((int) (self::CW * $widths[$i]));
            $cell->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($t['brand']));
            $run = $cell->createTextRun(trim((string) $h));
            $run->getFont()->setName(self::FONT)->setBold(true)->setSize(10.5)->setColor(new Color($t['onDark']));
            $cell->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        $ri = 0;
        foreach (array_slice($rows, 0, 7) as $rowData) {
            if (! is_array($rowData)) {
                continue;
            }
            $row = $table->createRow();
            $row->setHeight(29);
            $fill = $ri % 2 === 0 ? 'FFFFFFFF' : $t['surface'];
            foreach (array_values($rowData) as $i => $val) {
                if ($i >= $cols) {
                    break;
                }
                $cell = $row->getCell($i);
                $cell->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($fill));
                $run = $cell->createTextRun(trim((string) $val));
                $run->getFont()->setName(self::FONT)->setSize(10)->setColor(new Color($t['body']));
            }
            $ri++;
        }

        $price = is_array($spec['price'] ?? null) ? $spec['price'] : null;
        $note = is_array($spec['note'] ?? null) ? $spec['note'] : null;

        if ($price) {
            $p = $this->shape($slide, 'roundRect', true)
                ->setOffsetX(self::M)->setOffsetY(552)->setWidth(442)->setHeight(106);
            $p->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($t['accent']));
            $p->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $this->run($p, trim((string) ($price['value'] ?? 'по запросу')), 26, $t['onDark'], true);
            $label = trim((string) ($price['label'] ?? ''));
            if ($label !== '') {
                $p->createBreak();
                $this->run($p, $label, 11.5, $t['onDark']);
            }
        }

        if ($note) {
            $n = $this->shape($slide, 'roundRect', true)
                ->setOffsetX(523)->setOffsetY(552)->setWidth(699)->setHeight(106);
            $n->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($t['ink']));
            $n->setInsetLeft(28)->setInsetRight(28);
            $this->run($n, trim((string) ($note['title'] ?? '')), 14, $t['onDark'], true);
            $text = trim((string) ($note['text'] ?? ''));
            if ($text !== '') {
                $n->createBreak();
                $this->run($n, $text, 11, $t['onDarkMuted']);
            }
        }
    }

    private function renderTimeline(Slide $slide, array $spec, array $t, int $num, int $total): void
    {
        $this->paintFrame($slide, $t, (string) ($spec['kicker'] ?? ''), (string) ($spec['title'] ?? ''), $num, $total);

        $steps = array_values(array_filter(array_map(
            fn ($step) => is_array($step) ? $step : null,
            is_array($spec['steps'] ?? null) ? $spec['steps'] : []
        )));
        $steps = array_slice($steps, 0, 4);
        $count = count($steps);
        if ($count === 0) {
            return;
        }

        $centers = $count === 4 ? [169, 464, 758, 1053] : [243, 640, 1037];
        if ($count < 3) {
            $centers = array_slice([393, 887], 0, $count);
        }

        $this->box($slide, $t['faint'], 86, 205, 1108, 3);

        foreach ($steps as $i => $step) {
            $cx = $centers[$i];
            $isLast = $i === $count - 1;

            $oval = $this->shape($slide, 'ellipse', true)
                ->setOffsetX($cx - 30)->setOffsetY(176)->setWidth(60)->setHeight(60);
            $oval->getFill()->setFillType(Fill::FILL_SOLID)
                ->setStartColor(new Color($isLast ? $t['success'] : $t['brand']));
            $oval->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $oval->setInsetLeft(0)->setInsetRight(0)->setInsetTop(0)->setInsetBottom(0);
            $this->run($oval, (string) ($i + 1), 15, $t['onDark'], true);

            $this->box($slide, $t['surface'], $cx - 140, 264, 280, 317, 'roundRect');

            $k = $this->text($slide, $cx - 116, 288, 232, 24);
            $this->run($k, mb_strtoupper(trim((string) ($step['kicker'] ?? ''))), 10.5, $t['accent'], true);

            $title = $this->text($slide, $cx - 116, 316, 232, 56);
            $title->getActiveParagraph()->setLineSpacing(110);
            $this->run($title, trim((string) ($step['title'] ?? '')), 14.5, $t['ink'], true);

            $body = $this->text($slide, $cx - 116, 372, 232, 190);
            $body->getActiveParagraph()->setLineSpacing(120);
            $this->run($body, trim((string) ($step['text'] ?? '')), 11.5, $t['body']);
        }

        $note = trim((string) ($spec['note'] ?? ''));
        if ($note !== '') {
            $n = $this->text($slide, self::M, 610, self::CW, 48);
            $this->run($n, $note, 13, $t['brand'], true);
        }
    }

    private function renderTwocol(Slide $slide, array $spec, array $t, int $num, int $total): void
    {
        $this->paintFrame($slide, $t, (string) ($spec['kicker'] ?? ''), (string) ($spec['title'] ?? ''), $num, $total);

        $sides = [
            ['data' => is_array($spec['left'] ?? null) ? $spec['left'] : [], 'x' => self::M, 'fill' => $t['tint'], 'head' => $t['brand']],
            ['data' => is_array($spec['right'] ?? null) ? $spec['right'] : [], 'x' => 651, 'fill' => $t['successTint'], 'head' => $t['success']],
        ];

        foreach ($sides as $side) {
            $x = $side['x'];
            $data = $side['data'];

            $this->box($slide, $side['fill'], $x, 144, 571, 422, 'roundRect');

            $head = $this->shape($slide, 'roundRect', true)
                ->setOffsetX($x)->setOffsetY(144)->setWidth(571)->setHeight(67);
            $head->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($side['head']));
            $head->setInsetLeft(28);
            $this->run($head, trim((string) ($data['title'] ?? '')), 15, $t['onDark'], true);

            $bullets = is_array($data['bullets'] ?? null) ? $data['bullets'] : [];
            $list = $this->text($slide, $x + 31, 235, 509, 240);
            $this->fillBullets($list, $bullets, 13, $t['body'], 6);

            $result = trim((string) ($data['result'] ?? ''));
            if ($result !== '') {
                $r = $this->text($slide, $x + 31, 506, 509, 50);
                $r->getActiveParagraph()->setLineSpacing(115);
                $this->run($r, $result, 12.5, $side['head'], true);
            }
        }

        $note = trim((string) ($spec['note'] ?? ''));
        if ($note !== '') {
            $n = $this->text($slide, self::M, 595, self::CW, 58);
            $n->getActiveParagraph()->setLineSpacing(120);
            $this->run($n, $note, 12.5, $t['muted']);
        }
    }

    private function renderContacts(Slide $slide, array $spec, array $t): void
    {
        $this->box($slide, $t['accent'], 0, 0, self::W, 13);
        $this->box($slide, $t['darkCard1'], 941, 374, 576, 576, 'roundRect');
        $this->box($slide, $t['brand'], 1046, 480, 374, 374, 'roundRect');

        $title = $this->text($slide, self::M, 192, 912, 110);
        $this->run($title, trim((string) ($spec['title'] ?? 'Спасибо за встречу!')), 38, $t['onDark'], true);
        $subtitle = trim((string) ($spec['subtitle'] ?? ''));
        if ($subtitle !== '') {
            $title->createBreak();
            $this->run($title, $subtitle, 15, $t['onDarkMuted']);
        }

        $this->box($slide, $t['accent'], self::M, 360, 134, 3);

        $block = $this->text($slide, self::M, 394, 864, 230);
        $block->getActiveParagraph()->setLineSpacing(135);
        $name = trim((string) ($spec['name'] ?? ''));
        if ($name !== '') {
            $this->run($block, $name, 20, $t['onDark'], true);
        }
        $role = trim((string) ($spec['role'] ?? ''));
        if ($role !== '') {
            $block->createBreak();
            $this->run($block, $role, 13.5, $t['accent']);
        }
        $lines = is_array($spec['lines'] ?? null) ? array_slice($spec['lines'], 0, 5) : [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $block->createBreak();
            $this->run($block, $line, 13, $t['onDark']);
        }

        $footer = $this->text($slide, self::M, 662, self::CW, 38);
        $this->run($footer, trim((string) ($spec['footer'] ?? '')) !== '' ? (string) $spec['footer'] : '«Команда F5» · внедрение CRM, доработки, сопровождение', 10.5, $t['onDarkFaint']);
    }

    // ------------------------------------------------------------------
    // Пост-обработка XML: пресеты геометрии и вертикальное центрирование
    // ------------------------------------------------------------------

    /**
     * PhpPresentation пишет все RichText-фигуры как prst="rect" с верхней
     * привязкой текста. Здесь заменяем пресет (roundRect/chevron/ellipse)
     * и добавляем anchor="ctr" тем фигурам, которым нужно центрирование.
     * Порядок фигур в XML совпадает с порядком их создания.
     */
    private function patchGeometry(string $path): void
    {
        if ($this->geometry === []) {
            return;
        }

        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            return;
        }

        foreach ($this->geometry as $slideIdx => $items) {
            $name = 'ppt/slides/slide'.($slideIdx + 1).'.xml';
            $xml = $zip->getFromName($name);
            if ($xml === false) {
                continue;
            }

            $pos = 0;
            foreach ($items as $item) {
                $found = strpos($xml, '<a:prstGeom prst="rect">', $pos);
                if ($found === false) {
                    throw new F5AiException('Ошибка сборки презентации: рассинхрон фигур на слайде '.($slideIdx + 1));
                }

                if ($item['preset'] !== 'rect') {
                    // Убираем флаг текстового поля, чтобы фигура стала полноценным автошейпом
                    $tb = strrpos(substr($xml, 0, $found), '<p:cNvSpPr txBox="1"/>');
                    if ($tb !== false) {
                        $xml = substr($xml, 0, $tb).'<p:cNvSpPr/>'.substr($xml, $tb + 22);
                        $found -= 10; // длина удалённого фрагмента ' txBox="1"'
                    }

                    $avlst = $item['preset'] === 'roundRect'
                        ? '<a:avLst><a:gd name="adj" fmla="val 12000"/></a:avLst>'
                        : '<a:avLst/>';
                    $replacement = '<a:prstGeom prst="'.$item['preset'].'">'.$avlst.'</a:prstGeom>';

                    $close = strpos($xml, '</a:prstGeom>', $found);
                    $xml = substr($xml, 0, $found).$replacement.substr($xml, $close + 13);
                    $pos = $found + strlen($replacement);
                } else {
                    $pos = $found + 5;
                }

                if ($item['ctr']) {
                    $bp = strpos($xml, '<a:bodyPr', $pos);
                    if ($bp !== false) {
                        $xml = substr($xml, 0, $bp + 9).' anchor="ctr"'.substr($xml, $bp + 9);
                        $pos = $bp + 19;
                    }
                }
            }

            $zip->deleteName($name);
            $zip->addFromString($name, $xml);
        }

        $zip->close();
    }
}
