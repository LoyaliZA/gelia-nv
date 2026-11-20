<?php

namespace App\Services\ControlPedidos\Nacional;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use App\Models\User;
use App\Services\ControlPedidos\GenerarFolioPedidoBmaService;
use App\Support\ControlPedidos\Nacional\CapturaNacional;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
            if (! CapturaNacional::es($pedido)) {
                throw new InvalidArgumentException('Este complemento solo aplica a un envío nacional de la captura actual.');
            }
            if (! $pedido->numero_remision && ! $pedido->pago_validado_at) {
                throw new InvalidArgumentException('Sin remisión ni pago, el ajuste sigue en el mismo pedido.');
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
                'folio' => $antes ? $this->folios->ejecutarComplemento($pedido) : $this->folios->ejecutar(),
                'folio_remision' => 'WIZ-'.uniqid(),
                'fecha' => now()->toDateString(),
                'vendedor_id' => $usuario->id,
                'cliente_id' => $pedido->cliente_id,
                'origen_id' => $pedido->origen_id,
                'almacen_id' => $pedido->almacen_id,
                'catalogo_estatus_pedido_id' => $borrador->id,
                'catalogo_paqueteria_id' => $pedido->catalogo_paqueteria_id,
                'pedido_principal_id' => $antes ? $pedido->id : null,
                'solicitud_preparacion_json' => [
                    'modalidad_entrega' => 'nacional',
                    'captura_nacional' => 'compartida',
                    'codigo_modalidad' => 'ENVIO_BODEGA_NORMAL',
                    'complemento_nacional' => [
                        'solicitud_id' => $solicitudId,
                        'pedido_origen_id' => $pedido->id,
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
                'pago_validado_at' => now(),
                'pago_validado_por_id' => $usuario->id,
            ]);
            $this->guardar($hijo, $guardados);
            $piezasGuardadas = $this->registrarPiezas($hijo, $piezas);
            $jsonHijo = is_array($hijo->solicitud_preparacion_json) ? $hijo->solicitud_preparacion_json : [];
            $jsonHijo['complemento_nacional']['piezas'] = $piezasGuardadas;
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
     * @param  list<array{sku: string, descripcion: string, cantidad: int, evidencia: ?UploadedFile}>  $piezas
     * @return list<array{sku: string, descripcion: string, cantidad: int, documento_ids: list<int>, revision_id: int}>
     */
    private function registrarPiezas(PedidoBma $hijo, array $piezas): array
    {
        $salida = [];
        $orden = (int) $hijo->revisionesProducto()->max('orden');
        foreach ($piezas as $pieza) {
            $orden++;
            $revision = $hijo->revisionesProducto()->create([
                'sku' => $pieza['sku'],
                'descripcion_producto' => $pieza['descripcion'],
                'cantidad_solicitada' => $pieza['cantidad'],
                'estado_fisico' => PedidoBmaRevisionProducto::ESTADO_BUENO,
                'orden' => $orden,
            ]);
            $documentoIds = [];
            if ($pieza['evidencia'] instanceof UploadedFile) {
                $documento = $this->guardarUno($hijo, $pieza['evidencia'], $revision);
                $documentoIds[] = $documento->id;
            }
            $salida[] = [
                'sku' => $pieza['sku'],
                'descripcion' => $pieza['descripcion'],
                'cantidad' => $pieza['cantidad'],
                'documento_ids' => $documentoIds,
                'revision_id' => $revision->id,
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
            $this->persistir($pedido, $archivo, ++$orden, null);
        }
    }

    private function guardarUno(PedidoBma $pedido, UploadedFile $archivo, PedidoBmaRevisionProducto $revision): PedidoBmaDocumento
    {
        $orden = (int) $pedido->documentos()->max('orden') + 1;

        return $this->persistir($pedido, $archivo, $orden, $revision);
    }

    private function persistir(PedidoBma $pedido, UploadedFile $archivo, int $orden, ?PedidoBmaRevisionProducto $revision): PedidoBmaDocumento
    {
        $ruta = $archivo->store("pedidos_bma/complemento_nacional/{$pedido->id}", 'public');
        if (! is_string($ruta) || $ruta === '') {
            throw new \RuntimeException('No se pudo guardar el soporte del complemento.');
        }

        return $pedido->documentos()->create([
            'tipo' => $revision
                ? PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION
                : PedidoBmaDocumento::TIPO_ANEXO_PIEZAS,
            'ruta_archivo' => $ruta,
            'nombre_original' => $archivo->getClientOriginalName(),
            'mime_type' => $archivo->getMimeType(),
            'tamano_bytes' => $archivo->getSize(),
            'orden' => $orden,
            'relacion_tipo' => $revision ? PedidoBmaDocumento::RELACION_REVISION_PRODUCTO : null,
            'relacion_id' => $revision?->id,
        ]);
    }
}
