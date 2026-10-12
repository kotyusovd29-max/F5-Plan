<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\PhpExecutableFinder;

class ProjectAiBackgroundDispatcher
{
    public function dispatch(
        int $projectId,
        int $userMessageId,
        int $assistantMessageId,
        ?string $mode = null
    ): void {
        if (config('f5ai.sync_processing', false)) {
            $this->runInline($projectId, $userMessageId, $assistantMessageId, $mode);

            return;
        }

        $php = $this->resolvePhpBinary();
        $artisan = base_path('artisan');
        $arguments = [
            $php,
            $artisan,
            'ai:process-chat-message',
            (string) $projectId,
            (string) $userMessageId,
            (string) $assistantMessageId,
            $mode ?? '',
        ];

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $this->startDetachedWindows($arguments);
            } else {
                $this->startDetachedUnix($arguments);
            }
        } catch (\Throwable $exception) {
            Log::warning('AI background process failed to start, running inline.', [
                'project_id' => $projectId,
                'assistant_message_id' => $assistantMessageId,
                'mode' => $mode,
                'error' => $exception->getMessage(),
            ]);

            $this->runInline($projectId, $userMessageId, $assistantMessageId, $mode);
        }
    }

    /**
     * @param  list<string>  $arguments
     */
    private function startDetachedWindows(array $arguments): void
    {
        // На Windows дочерний Process из PHP-CGI/Apache убивается вместе с HTTP-запросом.
        // start /B /D запускает CLI-процесс независимо от родителя и из корня проекта.
        $workdir = '"'.str_replace('"', '""', base_path()).'"';
        $command = 'start /B /D '.$workdir.' "" '.implode(' ', array_map(
            static fn (string $argument): string => '"'.str_replace('"', '""', $argument).'"',
            $arguments
        )).' >NUL 2>NUL';

        $handle = popen($command, 'r');

        if ($handle === false) {
            throw new \RuntimeException('Failed to open Windows background process.');
        }

        pclose($handle);

        Log::info('AI background process started (Windows detached).', [
            'command' => $arguments,
        ]);
    }

    /**
     * @param  list<string>  $arguments
     */
    private function startDetachedUnix(array $arguments): void
    {
        // Symfony Process в деструкторе останавливает дочерний процесс — для Linux
        // нужен настоящий detach через nohup/setsid, иначе ИИ умирает вместе с HTTP-запросом.
        $command = implode(' ', array_map(
            static fn (string $argument): string => escapeshellarg($argument),
            $arguments
        ));
        $logFile = storage_path('logs/ai-background.log');
        $launcher = sprintf(
            'cd %s && nohup %s >> %s 2>&1 & echo $!',
            escapeshellarg(base_path()),
            $command,
            escapeshellarg($logFile)
        );

        $pid = trim((string) shell_exec($launcher));

        if ($pid === '' || ! ctype_digit($pid)) {
            throw new \RuntimeException('Failed to start detached AI process.');
        }

        usleep(150000);

        if (function_exists('posix_kill') && ! @posix_kill((int) $pid, 0)) {
            throw new \RuntimeException('Detached AI process exited immediately.');
        }

        Log::info('AI background process started (Unix detached).', [
            'pid' => (int) $pid,
            'command' => $arguments,
        ]);
    }

    private function runInline(
        int $projectId,
        int $userMessageId,
        int $assistantMessageId,
        ?string $mode
    ): void {
        $exitCode = artisan()->call('ai:process-chat-message', [
            'project' => (string) $projectId,
            'userMessage' => (string) $userMessageId,
            'assistantMessage' => (string) $assistantMessageId,
            'mode' => $mode ?? '',
        ]);

        if ($exitCode !== 0) {
            Log::error('AI inline processing failed.', [
                'project_id' => $projectId,
                'assistant_message_id' => $assistantMessageId,
                'mode' => $mode,
                'exit_code' => $exitCode,
            ]);
        }
    }

    private function resolvePhpBinary(): string
    {
        $configured = config('f5ai.php_binary');

        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        if (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') {
            $binary = PHP_BINARY;
            $base = strtolower(basename($binary));

            // php-cgi / php-fpm не подходят для artisan в фоне.
            if (! str_contains($base, 'cgi') && ! str_contains($base, 'fpm')) {
                return $binary;
            }

            $cliCandidate = dirname($binary).DIRECTORY_SEPARATOR.'php.exe';

            if (is_file($cliCandidate)) {
                return $cliCandidate;
            }

            $cliCandidate = dirname($binary).DIRECTORY_SEPARATOR.'php';

            if (is_file($cliCandidate)) {
                return $cliCandidate;
            }
        }

        return (new PhpExecutableFinder)->find(false) ?: 'php';
    }
}
