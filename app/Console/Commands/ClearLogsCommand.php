<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClearLogsCommand extends Command
{
    protected $signature = 'log:clear';
    protected $description = 'Clear all Laravel log files';

    public function handle()
    {
        $logPath = storage_path('logs');
        
        // Get all log files
        $logFiles = File::glob($logPath . '/*.log');
        
        foreach ($logFiles as $logFile) {
            File::put($logFile, '');
            $this->info("Cleared: " . basename($logFile));
        }
        
        $this->info('All log files have been cleared!');
        
        return Command::SUCCESS;
    }
}