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
use Illuminate\Support\Str;

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
                $lote = (int) ($datos['lote'] ?? 0);
                if ($lote > 0 && ! in_array($lote, $previo['lotes_adjuntos'] ?? [], true)) {
                    $this->anexarArchivosAlAjuste($pedido, $solicitudId, $archivos, $previo['tipo'], $lote);
                }

                return ['pedido' => $pedido->fresh(), 'idempotente' => true];
            }

            $tipo = (string) ($datos['tipo'] ?? '');
            if ($tipo === 'anexo' && ($pedido->numero_remision || $pedido->pago_validado_at || isset($pedido->solicitud_preparacion_json['pago_solo_mercancia_nacional']))) {
                if (! CapturaNacional::es($pedido)) {
                    throw new \InvalidArgumentException('Este ajuste solo aplica a un envío nacional de la captura actual.');
                }
                throw new \InvalidArgumentException('Con remisión o pago, use «Anexo extraordinario»: el anexo es un minipedido relacionado con su propia consulta y pago.');
            }

            RevisionCapturaPedido::comprobar($pedido, $datos);
            if (! CapturaNacional::es($pedido)) {
                throw new \InvalidArgumentException('Este ajuste solo aplica a un envío nacional de la captura actual.');
            }
            if ($pedido->numero_remision || $pedido->pago_validado_at || isset($pedido->solicitud_preparacion_json['pago_solo_mercancia_nacional'])) {
                throw new \InvalidArgumentException('La mercancía ya tiene remisión o pago. El complemento se registra aparte y no modifica este pedido.');
            }
            $motivo = match ($tipo) {
                'anexo' => PedidoBma::MOTIVO_REPESAJE_ANEXO_PIEZAS,
                'retiro' => PedidoBma::MOTIVO_REPESAJE_QUITA_PIEZAS,
                default => throw new \InvalidArgumentException('Indique si el ajuste anexa o retira piezas.'),
            };
            if (! $pedido->puedeSolicitarRepesaje() || $pedido->consultaCerrada()) {
                throw new \InvalidArgumentException('El pedido no admite ajustes en el estado actual de la consulta.');
            }
            $piezas = $this->piezas($datos);
            if (! $pedido->tieneSoporteSolicitud()) {
                throw new \InvalidArgumentException('Adjunte el documento del pedido antes de modificar la consulta.');
            }
            $vigentes = $this->aplicarRetiro($pedido, $tipo === 'retiro' ? $piezas : []);
            $guardados = $this->guardarArchivos($pedido, $archivos, $tipo);
            if ($guardados === [] && ! ($tipo === 'retiro' && $piezas !== [])) {
                throw new \InvalidArgumentException('Adjunte el soporte del ajuste.');
            }

            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $ajustes = is_array($json['ajustes_nacionales'] ?? null) ? array_values($json['ajustes_nacionales']) : [];
            $ajustes[] = [
                'solicitud_id' => $solicitudId,
                'tipo' => $tipo,
                'motivo' => trim((string) ($datos['motivo'] ?? $datos['condicion'] ?? '')),
                'piezas' => $piezas,
                'documento_ids' => array_map(fn (PedidoBmaDocumento $documento) => $documento->id, $guardados),
            ];
            if ($tipo === 'anexo') {
                $i = array_key_last($ajustes);
                foreach ($piezas as $pieza) {
                    $id = (string) Str::uuid();
                    $ajustes[$i]['pieza_ids'][] = $id;
                    $vigentes[] = $pieza + [
                        'id' => $id,
                        'documento_ids' => $ajustes[$i]['documento_ids'],
                    ];
                }
            }
            $json['ajustes_nacionales'] = $ajustes;
            $json['piezas_nacionales_vigentes'] = $vigentes;
            $pedido->update(['solicitud_preparacion_json' => $json]);

            $actualizado = $this->actualizarConsulta->ejecutar($pedido->fresh(), $usuario->id, $motivo);
            $actualizado->increment('revision_registro');

            return ['pedido' => $actualizado->fresh(), 'idempotente' => false];
        });
    }

    private function aplicarRetiro(PedidoBma $pedido, array $retiros): array
    {
        $vigentes = collect(CapturaNacional::piezasVigentes($pedido))->keyBy('id');
        $respuestas = $pedido->solicitud_preparacion_json['respuestas_piezas_nacionales'] ?? [];
        $revisiones = $pedido->revisionesProducto()->whereNull('retirada_at')->get()->keyBy('id');
        foreach ($retiros as $retiro) {
            $revisionId = $retiro['revision_id'] ?? null;
            if ($revisionId && ! $revisiones->has($revisionId)) {
                throw new \InvalidArgumentException('Elija una pieza vigente de la consulta.');
            }
            $id = $revisionId ? array_search($revisionId, $respuestas, true) : false;
            if ($id === false && ! $revisionId) {
                $coincidencias = $vigentes->filter(fn ($pieza) => ! empty($retiro['sku'])
                    ? $pieza['sku'] === $retiro['sku'] : $pieza['descripcion'] === $retiro['descripcion']);
                if ($coincidencias->count() > 1) {
                    throw new \InvalidArgumentException('Seleccione la pieza exacta en la respuesta de CEDIS.');
                }
                $id = $coincidencias->keys()->first() ?? false;
            }
            if ($id === false || ! $vigentes->has($id)) {
                continue;
            }
            $pieza = $vigentes->get($id);
            if ($retiro['cantidad'] > $pieza['cantidad']) {
                throw new \InvalidArgumentException('El retiro supera la cantidad solicitada.');
            }
            $pieza['cantidad'] -= $retiro['cantidad'];
            $vigentes->put($id, $pieza);
        }

        return $vigentes->where('cantidad', '>', 0)->values()->all();
    }

    /** @param  list<UploadedFile>  $archivos */
    private function anexarArchivosAlAjuste(PedidoBma $pedido, string $solicitudId, array $archivos, string $tipo, int $lote): void
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
            $ajustes[$indice]['lotes_adjuntos'][] = $lote;
            $json['piezas_nacionales_vigentes'] = array_map(
                fn ($pieza) => in_array($pieza['id'], $ajuste['pieza_ids'] ?? [], true)
                    ? array_replace($pieza, ['documento_ids' => $ids]) : $pieza,
                CapturaNacional::piezasVigentes($pedido),
            );
            break;
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
                ...(! empty($pieza['revision_id']) ? ['revision_id' => (int) $pieza['revision_id']] : []),
                'sku' => $sku,
                'descripcion' => $descripcion,
                'cantidad' => max(1, (int) ($pieza['cantidad'] ?? 1)),
            ];
        }

        return $salida;
    }
}
