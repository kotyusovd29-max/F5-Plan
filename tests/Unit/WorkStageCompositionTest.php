<?php

namespace Tests\Unit;

use App\Support\WorkStageComposition;
use PHPUnit\Framework\TestCase;

class WorkStageCompositionTest extends TestCase
{
    public function test_formats_multiline_and_inline_points(): void
    {
        $this->assertSame(
            "1) Обследование\n2) Проектирование",
            WorkStageComposition::format("Обследование\nПроектирование")
        );

        $this->assertSame(
            "1) Первый\n2) Второй\n3) Третий",
            WorkStageComposition::format('1) Первый 2) Второй 3) Третий')
        );

        $this->assertSame(
            "1) Аудит\n2) Настройка\n3) Обучение",
            WorkStageComposition::format('Аудит; Настройка; Обучение')
        );

        $this->assertSame(
            "1) Воронки\n2) Поля\n3) Автоматизации",
            WorkStageComposition::format('Воронки, Поля, Автоматизации')
        );
    }

    public function test_accepts_array_of_points(): void
    {
        $this->assertSame(
            ['Интервью', 'Схема процессов'],
            WorkStageComposition::items(['1) Интервью', '• Схема процессов'])
        );
    }
}
