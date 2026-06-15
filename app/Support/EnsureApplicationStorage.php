<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

final class EnsureApplicationStorage
{
    /**
     * @return list<string>
     */
    public static function requiredDirectories(): array
    {
        return [
            storage_path('app/private'),
            storage_path('app/private/od'),
            storage_path('app/public'),
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
        ];
    }

    public static function run(): void
    {
        foreach (self::requiredDirectories() as $directory) {
            if (! File::isDirectory($directory)) {
                File::makeDirectory($directory, 0775, true);
            }
        }

        $logFile = storage_path('logs/laravel.log');
        if (! File::exists($logFile)) {
            File::put($logFile, '');
        }
    }
}
