<?php

namespace App\Jobs;

use App\Models\ErrorLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;

class LogError implements ShouldQueue
{
    use Queueable;

    protected $title;
    protected $filename;
    protected $description;

    public function __construct($title, $filename, $description)
    {
        $this->title = $title;
        $this->filename = $filename;
        $this->description = $description;
    }

    public function handle()
    {
        $user = Auth::user();

        ErrorLog::create([
            'title' => $this->title,
            'user_id' => $user?->id,
            'filename' => $this->filename,
            'description' => $this->description,
        ]);
    }
}
