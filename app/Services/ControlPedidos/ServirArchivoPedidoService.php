<?php

namespace App\Services\ControlPedidos;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Se invoca después de comprobar la visibilidad del pedido y del documento. */
class ServirArchivoPedidoService
{
    public function responder(Request $request, string $disk, string $ruta, string $nombre, ?string $mime): BinaryFileResponse
    {
        $almacen = Storage::disk($disk);
        abort_unless($almacen->exists($ruta), 404);
        $path = $almacen->path($ruta);
        $mime = $mime ?: 'application/octet-stream';
        $vista = $request->query('vista');
        if (in_array($vista, ['miniatura', 'previa'], true) && in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $version = $this->versionWebp($path, $vista === 'miniatura' ? 360 : 2560);
            if ($version !== null) {
                $path = $version;
                $mime = 'image/webp';
                $nombre = pathinfo($nombre, PATHINFO_FILENAME).'.webp';
            }
        }

        $response = response()->file($path, ['Content-Type' => $mime]);
        $response->setContentDisposition('inline', $nombre, 'documento.'.(pathinfo($nombre, PATHINFO_EXTENSION) ?: 'bin'));
        // Revalidar conserva las facultades vigentes antes de reutilizar el cuerpo local.
        $response->headers->set('Cache-Control', 'private, no-cache');
        $response->setEtag(hash('sha256', $path.'|'.filemtime($path).'|'.filesize($path)));
        $response->setLastModified((new \DateTimeImmutable)->setTimestamp(filemtime($path)));
        $response->isNotModified($request);

        return $response;
    }

    private function versionWebp(string $original, int $lado): ?string
    {
        if (! function_exists('imagewebp')) {
            return null;
        }
        $cache = Storage::disk('local');
        $clave = 'pedidos_vistas/v1/'.hash('sha256', $original.'|'.filemtime($original).'|'.filesize($original)."|{$lado}").'.webp';
        if ($cache->exists($clave.'.original')) {
            return null;
        }
        if ($cache->exists($clave)) {
            return $cache->path($clave);
        }
        $info = @getimagesize($original);
        if (! $info || $info[0] * $info[1] > 16000000 || filesize($original) > 10 * 1024 * 1024) {
            return null;
        }
        $ratio = min(1, $lado / max($info[0], $info[1]));
        $ancho = max(1, (int) round($info[0] * $ratio));
        $alto = max(1, (int) round($info[1] * $ratio));
        $limite = trim(ini_get('memory_limit'));
        $bytes = (int) $limite * match (strtolower(substr($limite, -1))) {
            'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1,
        };
        if ($bytes > 0 && memory_get_usage(true) + $info[0] * $info[1] * 16 + $ancho * $alto * 8 + 16 * 1024 * 1024 > $bytes) {
            return null;
        }
        $imagen = null;
        $destino = null;
        $temporal = null;
        try {
            $imagen = match ($info[2]) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($original),
                IMAGETYPE_PNG => @imagecreatefrompng($original),
                IMAGETYPE_WEBP => @imagecreatefromwebp($original),
                default => false,
            };
            if (! $imagen) {
                return null;
            }
            // Aplicar la orientación de cámara también a las vistas derivadas.
            if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
                $orientacion = (@exif_read_data($original)['Orientation'] ?? 1);
                if (in_array($orientacion, [2, 4, 5, 7], true)) {
                    imageflip($imagen, IMG_FLIP_HORIZONTAL);
                }
                $grados = match ($orientacion) { 3, 4 => 180, 6, 7 => -90, 5, 8 => 90, default => 0 };
                if ($grados) {
                    $rotada = imagerotate($imagen, $grados, 0);
                    if ($rotada) {
                        imagedestroy($imagen);
                        $imagen = $rotada;
                    }
                }
            }
            $ratio = min(1, $lado / max(imagesx($imagen), imagesy($imagen)));
            $ancho = max(1, (int) round(imagesx($imagen) * $ratio));
            $alto = max(1, (int) round(imagesy($imagen) * $ratio));
            $destino = imagecreatetruecolor($ancho, $alto);
            imagealphablending($destino, false);
            imagesavealpha($destino, true);
            imagecopyresampled($destino, $imagen, 0, 0, 0, 0, $ancho, $alto, imagesx($imagen), imagesy($imagen));
            $temporal = tempnam(sys_get_temp_dir(), 'pedido-vista-');
            if (! $temporal || ! imagewebp($destino, $temporal, 85)) {
                return null;
            }
            // No sustituir una imagen pequeña por una versión más pesada.
            if (filesize($temporal) >= filesize($original) && $ratio === 1) {
                $cache->put($clave.'.original', '1');
                return null;
            }
            $cache->makeDirectory(dirname($clave));
            $publicacion = tempnam(dirname($cache->path($clave)), 'vista-');
            if (! $publicacion) {
                return null;
            }
            if (! copy($temporal, $publicacion) || ! rename($publicacion, $cache->path($clave))) {
                @unlink($publicacion);
                return null;
            }

            return $cache->path($clave);
        } catch (\Throwable $e) {
            report($e);
            return null;
        } finally {
            if ($imagen) imagedestroy($imagen);
            if ($destino) imagedestroy($destino);
            if ($temporal && is_file($temporal)) unlink($temporal);
        }
    }
}
