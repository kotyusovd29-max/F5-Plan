<?php

namespace Tests\Unit;

use App\Services\BriefImageOcrService;
use Tests\TestCase;

class BriefImageOcrServiceTest extends TestCase
{
    public function test_recognize_returns_null_when_file_missing(): void
    {
        $service = app(BriefImageOcrService::class);

        $this->assertNull($service->recognize(sys_get_temp_dir().DIRECTORY_SEPARATOR.'missing-f5-ocr.png'));
    }
}
