<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class WhatsappServer extends Command
{
    protected $signature = 'whatsapp:serve {--port=3001 : The port for the WhatsApp server}';
    protected $description = 'Start the Bun/Baileys WhatsApp server';

    public function handle(): int
    {
        $port = $this->option('port');
        $serverPath = base_path('whatsapp-server');

        if (!file_exists($serverPath . '/server.ts')) {
            $this->error('WhatsApp server not found at: ' . $serverPath);
            return self::FAILURE;
        }

        $this->info("Starting WhatsApp server on port {$port}...");
        $this->info('Press Ctrl+C to stop.');

        // Merge with system env so bun/node are found in PATH
        $env = array_merge($_ENV, $_SERVER, [
            'PORT' => $port,
            'LARAVEL_URL' => env('APP_URL', 'http://localhost:8000'),
            'WHATSAPP_SECRET' => config('services.whatsapp.secret', 'whatsapp-erp-secret'),
            'STORAGE_PATH' => storage_path('app/whatsapp'),
        ]);

        $process = new Process(
        ['bun', 'run', 'server.ts'],
            $serverPath,
            $env
            );

        $process->setTimeout(null);
        $process->start();

        $process->wait(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        return $process->getExitCode() ?? self::SUCCESS;
    }
}