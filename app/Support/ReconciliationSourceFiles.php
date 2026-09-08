<?php

namespace App\Support;

use App\Models\ReconciliationRun;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Conservation des fichiers partenaires pour rejouer une réconciliation.
 */
final class ReconciliationSourceFiles
{
    private const DISK = 'local';

    private const PENDING_TTL_SECONDS = 86_400;

    /**
     * @param  list<UploadedFile>  $files
     * @return list<array{path: string, original_name: string}>
     */
    public static function storePending(int $userId, int $partenaireId, array $files): array
    {
        self::forgetPending($userId, $partenaireId);

        $stored = [];
        $base = 'reconciliation/pending/'.$userId.'/'.$partenaireId.'/'.Str::uuid();

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                ?: 'fichier';
            $ext = $file->getClientOriginalExtension();
            $filename = $safeName.($ext !== '' ? '.'.$ext : '');
            $path = $file->storeAs($base, $filename, self::DISK);

            if (! is_string($path) || $path === '') {
                continue;
            }

            $stored[] = [
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
            ];
        }

        Cache::put(self::pendingKey($userId, $partenaireId), $stored, self::PENDING_TTL_SECONDS);

        return $stored;
    }

    /**
     * @return list<array{path: string, original_name: string}>
     */
    public static function takePending(int $userId, int $partenaireId): array
    {
        $stored = Cache::pull(self::pendingKey($userId, $partenaireId), []);

        return is_array($stored) ? array_values(array_filter($stored, static function ($row) {
            return is_array($row)
                && filled($row['path'] ?? null)
                && Storage::disk(self::DISK)->exists((string) $row['path']);
        })) : [];
    }

    public static function forgetPending(int $userId, int $partenaireId): void
    {
        $existing = Cache::pull(self::pendingKey($userId, $partenaireId), []);
        if (! is_array($existing)) {
            return;
        }

        foreach ($existing as $row) {
            if (is_array($row) && filled($row['path'] ?? null)) {
                Storage::disk(self::DISK)->delete((string) $row['path']);
            }
        }
    }

    /**
     * @param  list<array{path: string, original_name: string}>  $pending
     * @return list<array{path: string, original_name: string}>
     */
    public static function attachToRun(ReconciliationRun $run, array $pending): array
    {
        if ($pending === []) {
            return [];
        }

        $attached = [];
        $base = 'reconciliation/runs/'.$run->id.'/sources';

        foreach ($pending as $index => $row) {
            $from = (string) $row['path'];
            if (! Storage::disk(self::DISK)->exists($from)) {
                continue;
            }

            $original = (string) ($row['original_name'] ?? basename($from));
            $safe = Str::slug(pathinfo($original, PATHINFO_FILENAME)) ?: 'fichier';
            $ext = pathinfo($original, PATHINFO_EXTENSION);
            $target = $base.'/'.$index.'_'.$safe.($ext !== '' ? '.'.$ext : '');

            Storage::disk(self::DISK)->copy($from, $target);
            Storage::disk(self::DISK)->delete($from);

            $attached[] = [
                'path' => $target,
                'original_name' => $original,
            ];
        }

        $run->forceFill(['source_files' => $attached])->save();

        return $attached;
    }

    /**
     * @return list<array{path: string, original_name: string}>
     */
    public static function copyToNewRun(ReconciliationRun $from, ReconciliationRun $to): array
    {
        $sources = self::usableSources($from->source_files);
        if ($sources === []) {
            return [];
        }

        $attached = [];
        $base = 'reconciliation/runs/'.$to->id.'/sources';

        foreach ($sources as $index => $row) {
            $fromPath = (string) $row['path'];
            $original = (string) $row['original_name'];
            $safe = Str::slug(pathinfo($original, PATHINFO_FILENAME)) ?: 'fichier';
            $ext = pathinfo($original, PATHINFO_EXTENSION);
            $target = $base.'/'.$index.'_'.$safe.($ext !== '' ? '.'.$ext : '');

            Storage::disk(self::DISK)->copy($fromPath, $target);

            $attached[] = [
                'path' => $target,
                'original_name' => $original,
            ];
        }

        $to->forceFill(['source_files' => $attached])->save();

        return $attached;
    }

    /**
     * @param  mixed  $sourceFiles
     * @return list<array{path: string, original_name: string, absolute_path: string}>
     */
    public static function usableSources(mixed $sourceFiles): array
    {
        if (! is_array($sourceFiles)) {
            return [];
        }

        $out = [];
        foreach ($sourceFiles as $row) {
            if (! is_array($row) || ! filled($row['path'] ?? null)) {
                continue;
            }

            $path = (string) $row['path'];
            if (! Storage::disk(self::DISK)->exists($path)) {
                continue;
            }

            $out[] = [
                'path' => $path,
                'original_name' => (string) ($row['original_name'] ?? basename($path)),
                'absolute_path' => Storage::disk(self::DISK)->path($path),
            ];
        }

        return $out;
    }

    public static function canRelaunch(ReconciliationRun $run): bool
    {
        return $run->date_debut !== null
            && $run->date_fin !== null
            && self::usableSources($run->source_files) !== [];
    }

    private static function pendingKey(int $userId, int $partenaireId): string
    {
        return 'recon.pending_sources.'.$userId.'.'.$partenaireId;
    }
}
