<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class DevServe extends Command
{
    protected $signature = 'dev
        {--host=127.0.0.1 : The host address}
        {--port=8000 : The Laravel port}
        {--whatsapp-port=3001 : The WhatsApp server port}';

    protected $description = 'Start Laravel, Reverb, and WhatsApp server together';

    private array $processes = [];

    public function handle(): int
    {
        $host = $this->option('host');
        $port = (int)$this->option('port');
        $whatsappPort = $this->option('whatsapp-port');

        $this->freePort($port);
        $this->freePort((int)$whatsappPort);
        $this->freePort(8080); // Reverb default

        $this->info('');
        $this->info('🚀 <fg=white;options=bold>Starting ERP Development Environment</>');
        $this->info('');

        // 1. Start Reverb
        $this->startBgProcess('reverb', [
            PHP_BINARY, 'artisan', 'reverb:start',
        ], base_path(), '🟣');

        // 2. Start WhatsApp Bun server
        $whatsappPath = base_path('whatsapp-server');
        if (file_exists($whatsappPath . '/server.ts')) {
            $env = array_merge($_ENV, $_SERVER, [
                'PORT' => $whatsappPort,
                'LARAVEL_URL' => "http://{$host}:{$port}",
                'WHATSAPP_SECRET' => config('services.whatsapp.secret', 'whatsapp-erp-secret'),
                'STORAGE_PATH' => storage_path('app/whatsapp'),
            ]);

            $this->startBgProcess('whatsapp', [
                'bun', 'run', 'server.ts',
            ], $whatsappPath, '🟢', $env);
        }
        else {
            $this->warn('  ⚠ WhatsApp server not found, skipping.');
        }

        // 3. Start PHP built-in server directly
        $serverFile = file_exists(base_path('server.php'))
            ? base_path('server.php')
            : base_path('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');

        $phpProcess = new Process([
            PHP_BINARY, '-S', "{$host}:{$port}", $serverFile,
        ], base_path('public'));
        $phpProcess->setTimeout(null);
        $phpProcess->start();

        usleep(500000);

        if (!$phpProcess->isRunning()) {
            $errorOut = $phpProcess->getErrorOutput();
            $this->error("🔵 [laravel] Failed to start: {$errorOut}");
            $this->shutdown();
            return self::FAILURE;
        }

        $this->info("🔵 [laravel]  http://{$host}:{$port} (PID: {$phpProcess->getPid()})");
        $this->info('');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  ✅ All services running. Press Ctrl+C to stop.');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('');

        // Graceful shutdown
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function () use ($phpProcess) {
                $phpProcess->stop(3);
                $this->shutdown();
                exit(0);
            });
            pcntl_signal(SIGTERM, function () use ($phpProcess) {
                $phpProcess->stop(3);
                $this->shutdown();
                exit(0);
            });
        }

        // Main loop
        while ($phpProcess->isRunning()) {
            foreach ($this->processes as $name => $data) {
                $proc = $data['process'];
                $icon = $data['icon'];

                $this->pipeOutput($proc, "{$icon} [{$name}]");

                if (!$proc->isRunning()) {
                    $this->warn("{$icon} [{$name}] crashed — restarting...");
                    sleep(2);
                    $proc->start();
                }
            }

            $this->pipeOutput($phpProcess, '🔵 [laravel]');

            usleep(200000);
        }

        $this->shutdown();
        return self::SUCCESS;
    }

    private function startBgProcess(string $name, array $command, string $cwd, string $icon, ?array $env = null): void
    {
        $process = new Process($command, $cwd, $env);
        $process->setTimeout(null);
        $process->start();

        $this->processes[$name] = [
            'process' => $process,
            'icon' => $icon,
        ];

        $this->info("{$icon} [{$name}]  Started (PID: {$process->getPid()})");
    }

    private function pipeOutput(Process $proc, string $prefix): void
    {
        $out = $proc->getIncrementalOutput();
        if ($out) {
            foreach (explode("\n", rtrim($out)) as $line) {
                if (trim($line) !== '') {
                    $this->line("{$prefix} {$line}");
                }
            }
        }

        $err = $proc->getIncrementalErrorOutput();
        if ($err) {
            foreach (explode("\n", rtrim($err)) as $line) {
                if (trim($line) !== '') {
                    $this->line("{$prefix} {$line}");
                }
            }
        }
    }

    private function freePort(int $port): void
    {
        $pid = trim((string)@shell_exec("lsof -ti:{$port} 2>/dev/null"));
        if ($pid && is_numeric($pid)) {
            @shell_exec("kill -9 {$pid} 2>/dev/null");
            usleep(300000);
            $this->comment("  Killed stuck process on port {$port} (PID: {$pid})");
        }
    }

    private function shutdown(): void
    {
        $this->info('');
        $this->info('Shutting down all services...');

        foreach ($this->processes as $name => $data) {
            $proc = $data['process'];
            if ($proc->isRunning()) {
                $proc->stop(3);
                $this->info("  ✓ {$name} stopped");
            }
        }

        $this->info('  Done.');
    }
}