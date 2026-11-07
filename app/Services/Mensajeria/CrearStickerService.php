<?php

namespace App\Services\Mensajeria;

use App\Models\MensajeriaSticker;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CrearStickerService
{
    private const MAX_BYTES = 2 * 1024 * 1024;

    private const LADO = 512;

    public function ejecutar(User $user, UploadedFile $file): MensajeriaSticker
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'archivo' => 'El sticker debe pesar menos de 2 MB.',
            ]);
        }

        $guardado = $this->guardar($file, $user->id);

        return MensajeriaSticker::create([
            'user_id' => $user->id,
            'ruta' => $guardado['ruta'],
            'mime' => $guardado['mime'],
            'tamano' => $guardado['tamano'],
        ]);
    }

    private function guardar(UploadedFile $file, int $userId): array
    {
        $directory = "mensajeria/stickers/{$userId}";
        $procesada = $this->procesar($file, $directory);

        if ($procesada) {
            return $procesada;
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $filename = Str::uuid().'.'.$ext;
        Storage::disk('public')->putFileAs($directory, $file, $filename);

        return [
            'ruta' => "{$directory}/{$filename}",
            'mime' => $file->getMimeType() ?: 'image/png',
            'tamano' => (int) ($file->getSize() ?: 0),
        ];
    }

    private function procesar(UploadedFile $file, string $directory): ?array
    {
        if (! function_exists('imagewebp') || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $blob = @file_get_contents($file->getRealPath());
        if ($blob === false) {
            return null;
        }

        $origen = @imagecreatefromstring($blob);
        if (! $origen) {
            return null;
        }

        $ancho = imagesx($origen);
        $alto = imagesy($origen);
        $lado = min($ancho, $alto);
        $sx = (int) floor(($ancho - $lado) / 2);
        $sy = (int) floor(($alto - $lado) / 2);
        $destino = imagecreatetruecolor(self::LADO, self::LADO);
        imagealphablending($destino, false);
        imagesavealpha($destino, true);
        $transparente = imagecolorallocatealpha($destino, 0, 0, 0, 127);
        imagefilledrectangle($destino, 0, 0, self::LADO, self::LADO, $transparente);
        imagecopyresampled($destino, $origen, 0, 0, $sx, $sy, self::LADO, self::LADO, $lado, $lado);
        imagedestroy($origen);

        $filename = Str::uuid().'.webp';
        $relative = "{$directory}/{$filename}";
        $full = Storage::disk('public')->path($relative);

        if (! is_dir(dirname($full))) {
            mkdir(dirname($full), 0755, true);
        }

        $ok = imagewebp($destino, $full, 82);
        imagedestroy($destino);

        if (! $ok || ! is_file($full)) {
            return null;
        }

        return [
            'ruta' => $relative,
            'mime' => 'image/webp',
            'tamano' => filesize($full) ?: 0,
        ];
    }
}
