<?php

namespace App\Services\Mensajeria;

use App\Jobs\Mensajeria\ProcesarAdjuntoMensajeJob;
use App\Models\Conversacion;
use App\Models\Mensaje;
use App\Models\MensajeAdjunto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubirAdjuntoMensajeService
{
    private const LIMITES = [
        Mensaje::TIPO_IMAGEN => 10 * 1024 * 1024,
        Mensaje::TIPO_VIDEO => 50 * 1024 * 1024,
        Mensaje::TIPO_AUDIO => 10 * 1024 * 1024,
        Mensaje::TIPO_ARCHIVO => 25 * 1024 * 1024,
    ];

    public function __construct(
        private EnviarMensajeService $enviarMensaje,
        private OptimizarImagenMensajeService $optimizarImagen,
        private FormatearMensajeService $formatearMensaje,
        private NotificarMensajeEnviadoService $notificarMensaje,
        private IndexarTextoAdjuntoService $indexarTexto,
    ) {}

    public function ejecutar(
        Conversacion $conversacion,
        User $user,
        UploadedFile $file,
        string $tipo,
        ?string $contenido = null,
        ?int $replyToId = null,
    ): array {
        if (!isset(self::LIMITES[$tipo])) {
            throw ValidationException::withMessages(['archivo' => 'Tipo de adjunto no válido.']);
        }

        if ($file->getSize() > self::LIMITES[$tipo]) {
            $mb = self::LIMITES[$tipo] / (1024 * 1024);
            throw ValidationException::withMessages([
                'archivo' => "El archivo excede el límite de {$mb} MB.",
            ]);
        }

        $adjuntoData = match ($tipo) {
            Mensaje::TIPO_IMAGEN => $this->procesarImagen($file, $conversacion->id),
            default => $this->guardarArchivo($file, $conversacion->id),
        };

        $formateado = DB::transaction(function () use ($conversacion, $user, $tipo, $contenido, $replyToId, $adjuntoData, $file) {
            $mensajeData = $this->enviarMensaje->ejecutar($conversacion, $user, [
                'tipo' => $tipo,
                'contenido' => $contenido,
                'reply_to_id' => $replyToId,
            ], broadcast: false);

            $adjunto = MensajeAdjunto::create([
                'mensaje_id' => $mensajeData['id'],
                'ruta' => $adjuntoData['ruta'],
                'thumbnail_ruta' => $adjuntoData['thumbnail_ruta'] ?? null,
                'nombre_original' => $adjuntoData['nombre_original'] ?? $file->getClientOriginalName(),
                'mime' => $adjuntoData['mime'] ?? $file->getMimeType(),
                'tamano' => $adjuntoData['tamano'] ?? $file->getSize(),
                'duracion_seg' => $adjuntoData['duracion_seg'] ?? null,
            ]);

            $mensaje = Mensaje::with(['user:id,name,foto_perfil', 'adjuntos', 'lecturas'])
                ->find($mensajeData['id']);

            $adjunto->setRelation('mensaje', $mensaje);
            $this->indexarTexto->ejecutar($adjunto);

            return $this->formatearMensaje->ejecutar($mensaje, $user, $conversacion);
        });

        $adjunto = MensajeAdjunto::where('mensaje_id', $formateado['id'])->first();
        if ($adjunto) {
            ProcesarAdjuntoMensajeJob::dispatch($adjunto->id);
        }

        $mensaje = Mensaje::with(['user:id,name,foto_perfil', 'adjuntos', 'lecturas'])
            ->find($formateado['id']);

        $this->notificarMensaje->ejecutar(
            $conversacion,
            $user,
            $this->formatearMensaje->ejecutarParaBroadcast($mensaje, $conversacion)
        );

        return $formateado;
    }

    private function procesarImagen(UploadedFile $file, int $conversacionId): array
    {
        try {
            return $this->optimizarImagen->ejecutar($file, $conversacionId);
        } catch (ValidationException) {
            return $this->guardarArchivo($file, $conversacionId);
        }
    }

    private function guardarArchivo(UploadedFile $file, int $conversacionId): array
    {
        $ext = $file->getClientOriginalExtension() ?: 'bin';
        $filename = Str::uuid() . '.' . $ext;
        $directory = "mensajeria/{$conversacionId}";

        if (! Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        Storage::disk('public')->putFileAs($directory, $file, $filename);

        return [
            'ruta' => "{$directory}/{$filename}",
            'tamano' => $file->getSize(),
            'nombre_original' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
        ];
    }
}
