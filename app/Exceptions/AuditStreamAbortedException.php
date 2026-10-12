<?php

namespace App\Exceptions;

/**
 * Клиент закрыл SSE-соединение во время AI-аудита — частичный результат не сохраняем.
 */
class AuditStreamAbortedException extends \RuntimeException
{
}
