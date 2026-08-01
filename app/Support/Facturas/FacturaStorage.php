<?php

namespace App\Support\Facturas;

use Illuminate\Support\Facades\Storage;

/** Disco privado para adjuntos de facturas; lee legacy `public` hasta migrar. */
class FacturaStorage
{
    public const DISK = 'local';

    public const DISK_LEGACY = 'public';

    public static function storeDisk(): string
    {
        return self::DISK;
    }

    public static function diskFor(string $path): string
    {
        if (Storage::disk(self::DISK)->exists($path)) {
            return self::DISK;
        }

        if (Storage::disk(self::DISK_LEGACY)->exists($path)) {
            return self::DISK_LEGACY;
        }

        return self::DISK;
    }

    public static function exists(string $path): bool
    {
        return Storage::disk(self::DISK)->exists($path)
            || Storage::disk(self::DISK_LEGACY)->exists($path);
    }

    public static function path(string $path): string
    {
        return Storage::disk(self::diskFor($path))->path($path);
    }

    public static function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        foreach ([self::DISK, self::DISK_LEGACY] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }
    }
}
