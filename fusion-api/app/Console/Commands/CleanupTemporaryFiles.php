<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CleanupTemporaryFiles extends Command
{
    protected $signature = 'cleanup:files';
    protected $description = 'Delete temporary directories older than 1 minute';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $directories = Storage::disk('temporary')->directories();

        foreach ($directories as $directory) {
            $timestamp = basename($directory);
            $timestampParts = explode('-', $timestamp);
            
            if (isset($timestampParts[1]) && is_numeric($timestampParts[1])) {
                $folderTime = Carbon::createFromTimestamp($timestampParts[1]);

                if ($folderTime->isBefore(now()->subMinutes(10))) {
                    Storage::disk('temporary')->deleteDirectory($directory);
                    $this->info("Deleted directory: $directory");
                }
            }
        }
    }
}
