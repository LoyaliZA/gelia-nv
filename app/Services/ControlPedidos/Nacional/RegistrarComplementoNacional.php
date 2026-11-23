<?php

namespace App\Services\ControlPedidos\Nacional;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Services\ControlPedidos\GenerarFolioPedidoBmaService;
use App\Support\ControlPedidos\Nacional\CapturaNacional;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Complemento nacional aparte. No copia la mercancía ya pagada ni abre un pedido de Tienda. */
class RegistrarComplementoNacional
{
    public function __construct(
        private GenerarFolioPedidoBmaService $folios,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     * @param  list<UploadedFile>  $archivos
     */
    public function ejecutar(PedidoBma $pedido, User $usuario, array $datos, array $archivos): PedidoBma
    {
        return DB::transaction(function () use ($pedido, $usuario, $datos, $archivos) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $solicitudId = (string) ($datos['solicitud_id'] ?? '');
            $previo = $this->previo($pedido, $solicitudId);
            if ($previo) {
                return $previo;
            }

            RevisionCapturaPedido::comprobar($pedido, $datos);
            $pedido->loadMissing('estatus');
            if (! CapturaNacional::es($pedido)) {
                throw new InvalidArgumentException('Este complemento solo aplica a un envío nacional de la captura actual.');
            }
            if ($pedido->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_CANCELADO) {
                throw new InvalidArgumentException('Un pedido cancelado no admite anexos.');
            }
            if (! $pedido->numero_remision && ! $pedido->pago_validado_at) {
                throw new InvalidArgumentException('Sin remisión ni pago, el ajuste sigue en el mismo pedido.');
            }

            $folioWizerp = trim((string) ($datos['folio_wizerp'] ?? ''));
            if ($folioWizerp === '') {
                throw new InvalidArgumentException('Capture el folio Wizerp del anexo.');
            }
            $piezas = $this->piezas($datos);
            $monto = round((float) ($datos['total_mercancia_nueva'] ?? 0), 2);
            if ($piezas === [] || $monto <= 0) {
                throw new InvalidArgumentException('Indique las piezas nuevas y su monto.');
            }
            $guardados = $this->archivosValidos($archivos);
            if ($guardados === []) {
                throw new InvalidArgumentException('Adjunte el soporte del complemento.');
            }

            $pedido->loadMissing('estatus');
            $antes = ! $this->yaSalio($pedido);
            $borrador = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_BORRADOR);
            if (! $borrador) {
                throw new \RuntimeException('No se encontró el estatus BORRADOR.');
            }

            $hijo = PedidoBma::query()->create([
                'folio' => $this->folios->ejecutarComplemento($pedido),
                'folio_remision' => $folioWizerp,
                'fecha' => now()->toDateString(),
                'vendedor_id' => $usuario->id,
                'cliente_id' => $pedido->cliente_id,
                'origen_id' => $pedido->origen_id,
                'almacen_id' => $pedido->almacen_id,
                'catalogo_estatus_pedido_id' => $borrador->id,
                'catalogo_paqueteria_id' => $pedido->catalogo_paqueteria_id,
                'cliente_direccion_id' => $pedido->cliente_direccion_id,
                // Independiente: CEDIS lo empaca y guía aparte; el vínculo vive en el JSON.
                'solicitud_preparacion_json' => [
                    'modalidad_entrega' => 'nacional',
                    'captura_nacional' => 'compartida',
                    'codigo_modalidad' => 'ENVIO_BODEGA_NORMAL',
                    'complemento_nacional' => [
                        'solicitud_id' => $solicitudId,
                        'pedido_origen_id' => $pedido->id,
                        'pedido_origen_folio' => $pedido->folio,
                        'nota' => trim((string) ($datos['nota'] ?? '')) ?: null,
                        'piezas' => [],
                        'antes_de_salida' => $antes,
                        'mercancia_nueva_por_cobrar' => 0,
                    ],
                ],
                'total_mercancia' => $monto,
                'costo_envio' => 0,
                'saldo_a_favor' => 0,
                'costo_seguro' => 0,
                'total_a_cobrar' => $monto,
                'aplica_seguro' => false,
                'es_resguardo' => false,
            ]);
            $this->guardar($hijo, $guardados);
            $piezasGuardadas = $this->registrarPiezas($hijo, $piezas);
            $jsonHijo = is_array($hijo->solicitud_preparacion_json) ? $hijo->solicitud_preparacion_json : [];
            $jsonHijo['complemento_nacional']['piezas'] = $piezasGuardadas;
            // CEDIS responde las piezas que Ventas identificó en `piezas_nacionales`, igual que en el alta.
            $jsonHijo['piezas_nacionales'] = $piezasGuardadas;
            $hijo->update(['solicitud_preparacion_json' => $jsonHijo]);

            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $lista = is_array($json['complementos_nacionales'] ?? null) ? array_values($json['complementos_nacionales']) : [];
            $lista[] = [
                'solicitud_id' => $solicitudId,
                'pedido_id' => $hijo->id,
                'antes_de_salida' => $antes,
            ];
            $json['complementos_nacionales'] = $lista;
            $pedido->update(['solicitud_preparacion_json' => $json]);
            $pedido->increment('revision_registro');

            return $hijo->fresh(['revisionesProducto', 'documentos']);
        });
    }

    private function previo(PedidoBma $pedido, string $solicitudId): ?PedidoBma
    {
        if ($solicitudId === '') {
            return null;
        }
        foreach ((array) ($pedido->solicitud_preparacion_json['complementos_nacionales'] ?? []) as $item) {
            if (is_array($item) && (string) ($item['solicitud_id'] ?? '') === $solicitudId) {
                return PedidoBma::query()->find((int) ($item['pedido_id'] ?? 0));
            }
        }

        return null;
    }

    private function yaSalio(PedidoBma $pedido): bool
    {
        $fase = $pedido->estatus?->fase_ciclo;
        if (in_array($fase, [
            CatalogoEstatusPedido::FASE_ENVIADO,
            CatalogoEstatusPedido::FASE_ENTREGADO,
            CatalogoEstatusPedido::FASE_EN_RUTA,
        ], true)) {
            return true;
        }

        return $pedido->cajas()->where('estatus_recoleccion', PedidoBmaCaja::ESTATUS_RECOLECTADA)->exists();
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return list<array{sku: string, descripcion: string, cantidad: int, evidencia: ?UploadedFile}>
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
            $archivo = ($pieza['evidencia'] ?? null) instanceof UploadedFile ? $pieza['evidencia'] : null;
            if ($sku === '' && $descripcion === '' && $archivo === null) {
                continue;
            }
            if ($descripcion === '') {
                throw new InvalidArgumentException('Indique la descripción de cada pieza.');
            }
            if ($sku === '' && $archivo === null) {
                throw new InvalidArgumentException('Adjunte la captura o el documento de la pieza, o indique su SKU.');
            }
            $salida[] = [
                'sku' => $sku,
                'descripcion' => $descripcion,
                'cantidad' => max(1, (int) ($pieza['cantidad'] ?? 1)),
                'evidencia' => $archivo,
            ];
        }

        return $salida;
    }

    /**
     * Mismo contrato que el alta ordinaria: las piezas viven en el JSON con un UUID y CEDIS crea sus revisiones al responder.
     *
     * @param  list<array{sku: string, descripcion: string, cantidad: int, evidencia: ?UploadedFile}>  $piezas
     * @return list<array{id: string, sku: ?string, descripcion: string, cantidad: int, documento_ids: list<int>}>
     */
    private function registrarPiezas(PedidoBma $hijo, array $piezas): array
    {
        $salida = [];
        foreach ($piezas as $pieza) {
            $documentoIds = [];
            if ($pieza['evidencia'] instanceof UploadedFile) {
                $orden = (int) $hijo->documentos()->max('orden') + 1;
                $documentoIds[] = $this->persistir($hijo, $pieza['evidencia'], $orden, true)->id;
            }
            $salida[] = [
                'id' => (string) Str::uuid(),
                'sku' => $pieza['sku'] !== '' ? $pieza['sku'] : null,
                'descripcion' => $pieza['descripcion'],
                'cantidad' => $pieza['cantidad'],
                'documento_ids' => $documentoIds,
            ];
        }

        return $salida;
    }

    /**
     * @param  list<mixed>  $archivos
     * @return list<UploadedFile>
     */
    private function archivosValidos(array $archivos): array
    {
        return array_values(array_filter(
            $archivos,
            fn ($archivo) => $archivo instanceof UploadedFile
        ));
    }

    /** @param  list<UploadedFile>  $archivos */
    private function guardar(PedidoBma $pedido, array $archivos): void
    {
        $orden = (int) $pedido->documentos()->max('orden');
        foreach ($archivos as $archivo) {
            $this->persistir($pedido, $archivo, ++$orden, false);
        }
    }

    private function persistir(PedidoBma $pedido, UploadedFile $archivo, int $orden, bool $deLaPieza): PedidoBmaDocumento
    {
        $ruta = $archivo->store("pedidos_bma/complemento_nacional/{$pedido->id}", 'public');
        if (! is_string($ruta) || $ruta === '') {
            throw new \RuntimeException('No se pudo guardar el soporte del complemento.');
        }

        return $pedido->documentos()->create([
            'tipo' => $deLaPieza ? PedidoBmaDocumento::TIPO_ANEXO_PIEZAS : PedidoBmaDocumento::TIPO_PDF_PEDIDO,
            'ruta_archivo' => $ruta,
            'nombre_original' => $archivo->getClientOriginalName(),
            'mime_type' => $archivo->getMimeType(),
            'tamano_bytes' => $archivo->getSize(),
            'orden' => $orden,
            'relacion_tipo' => $deLaPieza ? CapturaNacional::RELACION_PIEZA_SOLICITADA : null,
            'activo' => true,
        ]);
    }
}
