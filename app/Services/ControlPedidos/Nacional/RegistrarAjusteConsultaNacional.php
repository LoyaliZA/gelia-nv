<?php

namespace App\Services\ControlPedidos\Nacional;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Services\ControlPedidos\ActualizarConsultaPedidoBmaService;
use App\Support\ControlPedidos\Nacional\CapturaNacional;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Anexo o retiro sin remisión sobre el mismo pedido nacional, con nueva consulta de pesaje. */
class RegistrarAjusteConsultaNacional
{
    public function __construct(
        private ActualizarConsultaPedidoBmaService $actualizarConsulta,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     * @param  list<UploadedFile>  $archivos
     * @return array{pedido: PedidoBma, idempotente: bool}
     */
    public function ejecutar(PedidoBma $pedido, User $usuario, array $datos, array $archivos): array
    {
        return DB::transaction(function () use ($pedido, $usuario, $datos, $archivos) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $solicitudId = (string) ($datos['solicitud_id'] ?? '');
            $previo = $this->ajustePrevio($pedido, $solicitudId);
            if ($previo !== null) {
                if ((int) ($datos['lote'] ?? 0) > 0) {
                    $this->anexarArchivosAlAjuste($pedido, $solicitudId, $archivos, (string) ($datos['tipo'] ?? 'anexo'));
                }

                return ['pedido' => $pedido->fresh(), 'idempotente' => true];
            }

            $tipo = (string) ($datos['tipo'] ?? '');
            if ($tipo === 'anexo' && ($pedido->numero_remision || $pedido->pago_validado_at)) {
                if (! CapturaNacional::es($pedido)) {
                    throw new \InvalidArgumentException('Este ajuste solo aplica a un envío nacional de la captura actual.');
                }
                $ya = $this->complementoPrevio($pedido, $solicitudId);
                app(RegistrarComplementoNacional::class)->ejecutar($pedido, $usuario, $datos, $archivos);

                return ['pedido' => $pedido->fresh(), 'idempotente' => $ya];
            }

            RevisionCapturaPedido::comprobar($pedido, $datos);
            if (! CapturaNacional::es($pedido)) {
                throw new \InvalidArgumentException('Este ajuste solo aplica a un envío nacional de la captura actual.');
            }
            if ($pedido->numero_remision || $pedido->pago_validado_at) {
                throw new \InvalidArgumentException('La mercancía ya tiene remisión o pago. El complemento se registra aparte y no modifica este pedido.');
            }
            $motivo = match ($tipo) {
                'anexo' => PedidoBma::MOTIVO_REPESAJE_ANEXO_PIEZAS,
                'retiro' => PedidoBma::MOTIVO_REPESAJE_QUITA_PIEZAS,
                default => throw new \InvalidArgumentException('Indique si el ajuste anexa o retira piezas.'),
            };
            $guardados = $this->guardarArchivos($pedido, $archivos, $tipo);
            if ($guardados === []) {
                throw new \InvalidArgumentException('Adjunte el soporte del ajuste.');
            }

            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $ajustes = is_array($json['ajustes_nacionales'] ?? null) ? array_values($json['ajustes_nacionales']) : [];
            $ajustes[] = [
                'solicitud_id' => $solicitudId,
                'tipo' => $tipo,
                'motivo' => trim((string) ($datos['motivo'] ?? $datos['condicion'] ?? '')),
                'piezas' => $this->piezas($datos),
                'documento_ids' => array_map(fn (PedidoBmaDocumento $documento) => $documento->id, $guardados),
            ];
            $json['ajustes_nacionales'] = $ajustes;
            $pedido->update(['solicitud_preparacion_json' => $json]);

            $actualizado = $this->actualizarConsulta->ejecutar($pedido->fresh(), $usuario->id, $motivo);
            $actualizado->increment('revision_registro');

            return ['pedido' => $actualizado->fresh(), 'idempotente' => false];
        });
    }

    /** @param  list<UploadedFile>  $archivos */
    private function anexarArchivosAlAjuste(PedidoBma $pedido, string $solicitudId, array $archivos, string $tipo): void
    {
        $guardados = $this->guardarArchivos($pedido, $archivos, $tipo);
        if ($guardados === []) {
            return;
        }
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $ajustes = is_array($json['ajustes_nacionales'] ?? null) ? array_values($json['ajustes_nacionales']) : [];
        foreach ($ajustes as $indice => $ajuste) {
            if (! is_array($ajuste) || (string) ($ajuste['solicitud_id'] ?? '') !== $solicitudId) {
                continue;
            }
            $ids = array_values(array_filter((array) ($ajuste['documento_ids'] ?? [])));
            foreach ($guardados as $documento) {
                $ids[] = $documento->id;
            }
            $ajustes[$indice]['documento_ids'] = $ids;
        }
        $json['ajustes_nacionales'] = $ajustes;
        $pedido->update(['solicitud_preparacion_json' => $json]);
    }

    /** @return array<string, mixed>|null */
    private function ajustePrevio(PedidoBma $pedido, string $solicitudId): ?array
    {
        foreach ((array) ($pedido->solicitud_preparacion_json['ajustes_nacionales'] ?? []) as $ajuste) {
            if (is_array($ajuste) && (string) ($ajuste['solicitud_id'] ?? '') === $solicitudId && $solicitudId !== '') {
                return $ajuste;
            }
        }

        return null;
    }

    private function complementoPrevio(PedidoBma $pedido, string $solicitudId): bool
    {
        foreach ((array) ($pedido->solicitud_preparacion_json['complementos_nacionales'] ?? []) as $item) {
            if (is_array($item) && (string) ($item['solicitud_id'] ?? '') === $solicitudId && $solicitudId !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<UploadedFile>  $archivos
     * @return list<PedidoBmaDocumento>
     */
    private function guardarArchivos(PedidoBma $pedido, array $archivos, string $tipo): array
    {
        $documentos = [];
        $orden = (int) $pedido->documentos()->max('orden');
        foreach ($archivos as $archivo) {
            if (! $archivo instanceof UploadedFile) {
                continue;
            }
            $ruta = $archivo->store("pedidos_bma/ajuste_nacional/{$pedido->id}", 'public');
            if (! is_string($ruta) || $ruta === '') {
                throw new \RuntimeException('No se pudo guardar el soporte del ajuste.');
            }
            $orden++;
            $documentos[] = $pedido->documentos()->create([
                'tipo' => $tipo === 'anexo' ? PedidoBmaDocumento::TIPO_ANEXO_PIEZAS : PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
                'ruta_archivo' => $ruta,
                'nombre_original' => $archivo->getClientOriginalName(),
                'mime_type' => $archivo->getMimeType(),
                'tamano_bytes' => $archivo->getSize(),
                'orden' => $orden,
            ]);
        }

        return $documentos;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return list<array{sku: string, descripcion: string, cantidad: int}>
     */
    private function piezas(array $datos): array
    {
        $salida = [];
        foreach ($datos['piezas'] ?? [] as $pieza) {
            if (! is_array($pieza)) {
                continue;
            }
            $sku = trim((string) ($pieza['sku'] ?? ''));
            $descripcion = trim((string) ($pieza['descripcion'] ?? ''));
            if ($sku === '' && $descripcion === '') {
                continue;
            }
            $salida[] = [
                'sku' => $sku,
                'descripcion' => $descripcion,
                'cantidad' => max(1, (int) ($pieza['cantidad'] ?? 1)),
            ];
        }

        return $salida;
    }
}
