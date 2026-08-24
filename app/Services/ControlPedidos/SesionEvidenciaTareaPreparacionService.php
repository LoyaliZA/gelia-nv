<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\ControlPedidos\PedidoBmaTareaSesionEvidencia;
use App\Models\ControlPedidos\PedidoBmaTareaSesionEvidenciaFoto;
use App\Support\FormPublicUrl;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SesionEvidenciaTareaPreparacionService
{
    private const ALFABETO = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';

    public function generar(PedidoBmaTareaPreparacion $tarea, int $usuarioId): array
    {
        $this->revocarAbiertas($tarea);

        $codigo = $this->generarCodigoUnico();
        $sesion = PedidoBmaTareaSesionEvidencia::query()->create([
            'pedido_bma_tarea_preparacion_id' => $tarea->id,
            'token_hash' => hash('sha256', $codigo),
            'codigo_publico' => $codigo,
            'estado' => PedidoBmaTareaSesionEvidencia::ESTADO_PENDIENTE,
            'expira_en' => now()->addMinutes(PedidoBmaTareaSesionEvidencia::TTL_MINUTOS),
            'creado_por' => $usuarioId,
            'snapshot_json' => ['productos' => $tarea->productos()->get(['id', 'descripcion_snapshot', 'sku'])->toArray()],
            'tipos_evidencia_json' => ['evidencia_general'],
        ]);

        $url = FormPublicUrl::tiendaEvidenciaShow($codigo);

        return [
            'sesion' => $sesion,
            'token' => $codigo,
            'url' => $url,
            'qr_data_uri' => $this->qrDataUri($url),
            'expira_en' => $sesion->expira_en->toIso8601String(),
        ];
    }

    public function vigente(PedidoBmaTareaPreparacion $tarea): ?PedidoBmaTareaSesionEvidencia
    {
        $sesion = PedidoBmaTareaSesionEvidencia::query()
            ->where('pedido_bma_tarea_preparacion_id', $tarea->id)
            ->whereIn('estado', [
                PedidoBmaTareaSesionEvidencia::ESTADO_PENDIENTE,
                PedidoBmaTareaSesionEvidencia::ESTADO_ACTIVA,
            ])
            ->latest('id')
            ->first();

        if (! $sesion) {
            return null;
        }

        if ($sesion->expira_en?->isPast()) {
            $sesion->update(['estado' => PedidoBmaTareaSesionEvidencia::ESTADO_EXPIRADA]);

            return null;
        }

        return $sesion;
    }

    public function cancelar(PedidoBmaTareaPreparacion $tarea): void
    {
        $sesion = $this->vigente($tarea);
        if ($sesion) {
            $sesion->update(['estado' => PedidoBmaTareaSesionEvidencia::ESTADO_CANCELADA]);
        }
    }

    public function porCodigo(string $codigo): PedidoBmaTareaSesionEvidencia
    {
        $sesion = PedidoBmaTareaSesionEvidencia::query()
            ->where('codigo_publico', $codigo)
            ->orWhere('token_hash', hash('sha256', $codigo))
            ->first();

        if (! $sesion) {
            throw ValidationException::withMessages(['codigo' => 'Sesión no encontrada o expirada.']);
        }

        return $sesion;
    }

    public function reclamar(string $codigo, string $ip, string $ua): PedidoBmaTareaSesionEvidencia
    {
        $sesion = $this->porCodigo($codigo);
        $this->asegurarVigente($sesion);

        if ($sesion->estado === PedidoBmaTareaSesionEvidencia::ESTADO_PENDIENTE) {
            $sesion->update([
                'estado' => PedidoBmaTareaSesionEvidencia::ESTADO_ACTIVA,
                'reclamado_en' => now(),
                'claim_ip' => $ip,
                'claim_ua' => mb_substr($ua, 0, 500),
            ]);
        }

        return $sesion->fresh(['fotos', 'tarea.pedido.cliente']);
    }

    public function subirFoto(PedidoBmaTareaSesionEvidencia $sesion, UploadedFile $file): PedidoBmaTareaSesionEvidenciaFoto
    {
        $this->asegurarVigente($sesion);
        if ($sesion->estado !== PedidoBmaTareaSesionEvidencia::ESTADO_ACTIVA) {
            throw ValidationException::withMessages(['foto' => 'La sesión no está activa.']);
        }

        $tarea = $sesion->tarea;
        if ($tarea && ! in_array($tarea->estado, [
            PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION,
            PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
        ], true)) {
            throw ValidationException::withMessages(['foto' => 'La tarea ya fue respondida.']);
        }

        $ruta = $file->store("pedidos_bma/tareas_preparacion/{$tarea?->id}/sesion", 'public');
        $orden = (int) $sesion->fotos()->max('orden') + 1;

        return $sesion->fotos()->create([
            'ruta' => $ruta,
            'nombre_original' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'tamano_bytes' => $file->getSize(),
            'orden' => $orden,
        ]);
    }

    public function promoverATarea(PedidoBmaTareaPreparacion $tarea, int $usuarioId): void
    {
        $sesion = $this->vigente($tarea);
        if (! $sesion) {
            return;
        }

        DB::transaction(function () use ($sesion, $tarea, $usuarioId) {
            foreach ($sesion->fotos as $foto) {
                $tarea->documentos()->create([
                    'tipo_evidencia' => PedidoBmaTareaDocumento::TIPO_EVIDENCIA_GENERAL,
                    'ruta_interna' => $foto->ruta,
                    'nombre_original' => $foto->nombre_original ?: 'evidencia_movil.jpg',
                    'mime_type' => $foto->mime_type,
                    'tamano_bytes' => $foto->tamano_bytes,
                    'subido_por_id' => $usuarioId,
                    'subido_at' => now(),
                ]);
            }
            $sesion->update(['estado' => PedidoBmaTareaSesionEvidencia::ESTADO_COMPLETADA]);
        });
    }

    public function payloadPublico(PedidoBmaTareaSesionEvidencia $sesion): array
    {
        $sesion->loadMissing(['tarea.pedido.cliente', 'fotos']);

        return [
            'folio' => $sesion->tarea?->pedido?->folio_remision ?: $sesion->tarea?->pedido?->folio,
            'estado' => $sesion->estado,
            'expira_en' => $sesion->expira_en?->toIso8601String(),
            'productos' => $sesion->snapshot_json['productos'] ?? [],
            'fotos_count' => $sesion->fotos->count(),
        ];
    }

    private function revocarAbiertas(PedidoBmaTareaPreparacion $tarea): void
    {
        PedidoBmaTareaSesionEvidencia::query()
            ->where('pedido_bma_tarea_preparacion_id', $tarea->id)
            ->whereIn('estado', [
                PedidoBmaTareaSesionEvidencia::ESTADO_PENDIENTE,
                PedidoBmaTareaSesionEvidencia::ESTADO_ACTIVA,
            ])
            ->update(['estado' => PedidoBmaTareaSesionEvidencia::ESTADO_CANCELADA]);
    }

    private function asegurarVigente(PedidoBmaTareaSesionEvidencia $sesion): void
    {
        if ($sesion->expira_en?->isPast()) {
            $sesion->update(['estado' => PedidoBmaTareaSesionEvidencia::ESTADO_EXPIRADA]);
            throw ValidationException::withMessages(['codigo' => 'La sesión expiró. Genere un nuevo QR.']);
        }
        if (in_array($sesion->estado, [
            PedidoBmaTareaSesionEvidencia::ESTADO_CANCELADA,
            PedidoBmaTareaSesionEvidencia::ESTADO_COMPLETADA,
            PedidoBmaTareaSesionEvidencia::ESTADO_EXPIRADA,
        ], true)) {
            throw ValidationException::withMessages(['codigo' => 'Esta sesión ya no está disponible.']);
        }
    }

    private function generarCodigoUnico(): string
    {
        do {
            $codigo = Str::random(8, self::ALFABETO);
        } while (PedidoBmaTareaSesionEvidencia::query()->where('codigo_publico', $codigo)->exists());

        return $codigo;
    }

    private function qrDataUri(string $url): string
    {
        $qr = new QrCode($url);
        $qr->setErrorCorrectionLevel(ErrorCorrectionLevel::Medium);
        $qr->setSize(280);
        $writer = new PngWriter();

        return $writer->write($qr)->getDataUri();
    }
}
