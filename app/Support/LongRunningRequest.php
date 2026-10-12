<?php

namespace App\Support;

class LongRunningRequest
{
    /**
     * Снимает лимиты PHP, чтобы длинная выгрузка amoCRM и ответ ИИ не обрывались по Time out.
     */
    public static function disableTimeouts(): void
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('max_input_time', '0');
        @ini_set('default_socket_timeout', '-1');
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', 'off');
        @ini_set('implicit_flush', '1');

        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
    }
}
