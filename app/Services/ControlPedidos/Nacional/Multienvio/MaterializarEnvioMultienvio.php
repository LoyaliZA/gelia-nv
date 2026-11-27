<?php

namespace App\Services\ControlPedidos\Nacional\Multienvio;

use App\Models\ControlPedidos\MultienvioNacional;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaDireccion;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaReferencia;
use App\Services\ControlPedidos\GenerarFolioPedidoBmaService;
use App\Support\ControlPedidos\Nacional\CapturaNacional;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Crea un envío (PedidoBma) de un multienvío a partir del origen y le mueve su caja.
 * Campos según el spike R1 (docs/superpowers/specs/2026-11-27-multienvio-r1-hallazgos.md §2-4).
 * Se ejecuta dentro de la transacción de ConfirmarMultienvio.
 */
class MaterializarEnvioMultienvio
{
    /** R1 A.1: identidad comercial, destino, modalidad y estado post-consulta. */
    public const COLUMNAS_COPIADAS = [
        'origen_solicitud', 'departamento_operacion_id', 'documento_inicial', 'contacto_nombre_snapshot',
        'contacto_telefono_snapshot', 'fecha', 'vendedor_id', 'cliente_id', 'origen_id', 'tipo_operacion_envio_id',
        'almacen_id', 'catalogo_banco_id', 'prioridad_md',
        'cliente_direccion_id', 'codigo_postal', 'domicilio_entrega', 'envia_otra_persona', 'envia_a_otra_persona',
        'sucursal_destino_id', 'catalogo_zona_id', 'catalogo_tipo_guia_id', 'catalogo_paqueteria_id',
        'catalogo_envio_tienda_id', 'envio_tienda_otro',
        'catalogo_estatus_pedido_id', 'estatus_envio', 'pesaje_solicitado_at', 'pesaje_respondido_at',
        'pesaje_respondido_por_id', 'consulta_cerrada_at', 'consulta_cerrada_por_id', 'consulta_actualizacion_pendiente',
        'estado_fisico_general', 'comentario_fisico_general', 'tiene_observaciones_fisicas', 'motivo_repesaje',
        'cliente_proporciona_guia', 'envio_por_cobrar', 'anexar_remision', 'es_resguardo', 'envio_solo_resguardos',
        'notas_resguardos_externos', 'comentarios_drive',
    ];

    /** R1 A.4: hechos del origen que no viajan al envío. */
    public const CLAVES_JSON_DEL_ORIGEN = [
        'cotizacion_nacional', 'pago_solo_mercancia_nacional', 'invalidacion_guia', 'vinculo_resguardo',
        'complementos_nacionales', 'complemento_nacional', 'respuesta_complemento_nacional', 'cambio_a_tienda',
        'cancelacion_nacional', 'ajustes_nacionales',
    ];

    /** @var list<array{disco: string, ruta: string}> copias físicas hechas en la confirmación en curso */
    private array $copias = [];

    public function __construct(private readonly GenerarFolioPedidoBmaService $folios) {}

    public function reiniciarCopias(): void
    {
        $this->copias = [];
    }

    /** Borra las copias físicas si la transacción se revirtió. */
    public function eliminarCopias(): void
    {
        foreach ($this->copias as $copia) {
            try {
                Storage::disk($copia['disco'])->delete($copia['ruta']);
            } catch (Throwable) {
                // Sin efecto sobre la reversión: a lo sumo queda un archivo huérfano.
            }
        }
        $this->copias = [];
    }

    /** Agregados de una sola caja (R1 A.3); la cotización se rehace por envío. */
    public static function agregadosDeCaja(PedidoBmaCaja $caja, float $mercancia, float $saldoAFavor, bool $aplicaSeguro): array
    {
        return [
            'numero_cajas' => 1,
            'peso_real_kg' => $caja->peso_real_kg,
            'peso_volumetrico_kg' => $caja->peso_volumetrico_kg,
            'peso_cobrado_guia_kg' => $caja->peso_cobrado_kg,
            'catalogo_tipo_caja_id' => $caja->catalogo_tipo_caja_id,
            'total_mercancia' => $mercancia,
            'costo_envio' => null,
            'costo_seguro' => 0,
            'aplica_seguro' => $aplicaSeguro,
            'total_a_cobrar' => PedidoBma::calcularTotal($mercancia, 0, false, 0, $saldoAFavor),
        ];
    }

    /** @param  array{folio_remision: string, mercancia: float}  $entrada */
    public function crear(PedidoBma $origen, MultienvioNacional $grupo, PedidoBmaCaja $caja, array $entrada, int $orden, bool $aplicaSeguro, int $usuarioId): PedidoBma
    {
        $datos = [];
        foreach (self::COLUMNAS_COPIADAS as $columna) {
            $datos[$columna] = $origen->getRawOriginal($columna);
        }

        $miembro = PedidoBma::query()->create(array_merge($datos, self::agregadosDeCaja($caja, $entrada['mercancia'], 0, $aplicaSeguro), [
            'folio' => $this->folios->ejecutar(),
            'folio_remision' => $entrada['folio_remision'],
            'saldo_a_favor' => 0,
            'solicitud_preparacion_json' => $this->jsonDelEnvio($origen->solicitud_preparacion_json ?? []),
            'multienvio_nacional_id' => $grupo->id,
            'multienvio_orden' => $orden,
        ]));

        $this->moverCaja($origen, $caja, $miembro);
        $this->duplicarSoporte($origen, $miembro);
        $this->duplicarDireccionManual($origen, $miembro);
        PedidoBmaReferencia::registrar($miembro, PedidoBmaReferencia::TIPO_PEDIDO, $entrada['folio_remision'], $usuarioId);

        return $miembro;
    }

    /** @param  array<string, mixed>  $json */
    private function jsonDelEnvio(array $json): array
    {
        foreach (self::CLAVES_JSON_DEL_ORIGEN as $clave) {
            unset($json[$clave]);
        }
        // Las piezas no están ligadas a la caja (R1 §3.3): se quedan en el origen y el envío no las vuelve a exigir.
        $json['piezas_nacionales'] = [];
        $json['piezas_nacionales_vigentes'] = [];
        $json['respuestas_piezas_nacionales'] = [];

        return $json;
    }

    /** UPDATE directo: no pasa por SincronizarCajas (que retiraría las demás). caja_remisiones la siguen por FK. */
    private function moverCaja(PedidoBma $origen, PedidoBmaCaja $caja, PedidoBma $miembro): void
    {
        PedidoBmaCaja::query()->whereKey($caja->id)->update(['pedido_bma_id' => $miembro->id, 'orden' => 0]);

        PedidoBmaDocumento::query()
            ->where('pedido_bma_id', $origen->id)
            ->where(fn (Builder $q) => $q->where('pedido_bma_caja_id', $caja->id)
                ->orWhere(fn (Builder $q) => $q->where('relacion_tipo', PedidoBmaDocumento::RELACION_ENVIO_CAJA)->where('relacion_id', $caja->id)))
            ->update(['pedido_bma_id' => $miembro->id]);
    }

    /**
     * R1 §3.2: el envío necesita su propio soporte. Se copia también el archivo físico, porque varios
     * servicios borran el archivo al sustituir o eliminar un documento y no deben afectar a los demás envíos.
     */
    private function duplicarSoporte(PedidoBma $origen, PedidoBma $miembro): void
    {
        $soporte = PedidoBmaDocumento::query()
            ->where('pedido_bma_id', $origen->id)
            ->where('activo', true)
            ->where(fn (Builder $q) => $q->whereIn('tipo', [PedidoBmaDocumento::TIPO_PDF_PEDIDO, PedidoBmaDocumento::TIPO_COTIZACION])
                ->orWhere('relacion_tipo', CapturaNacional::RELACION_PIEZA_SOLICITADA))
            ->orderBy('id')
            ->get();

        foreach ($soporte as $documento) {
            $copia = $documento->replicate(['reemplaza_documento_id', 'pedido_bma_caja_id']);
            $copia->pedido_bma_id = $miembro->id;
            $copia->ruta_archivo = $this->copiarArchivo($documento, $miembro);
            $copia->save();
        }
    }

    private function copiarArchivo(PedidoBmaDocumento $documento, PedidoBma $miembro): string
    {
        $disco = $documento->discoArchivo();
        $origen = (string) $documento->ruta_archivo;
        $destino = 'pedidos_bma/multienvio/'.$miembro->id.'/'.$documento->id.'-'.basename($origen);

        try {
            $copiado = $origen !== '' && Storage::disk($disco)->copy($origen, $destino);
        } catch (Throwable $e) {
            throw MultienvioNacionalException::soporteNoDisponible((int) $documento->id, $e);
        }
        if (! $copiado) {
            throw MultienvioNacionalException::soporteNoDisponible((int) $documento->id);
        }
        $this->copias[] = ['disco' => $disco, 'ruta' => $destino];

        return $destino;
    }

    /** R1 §4: sin dirección de cliente, el envío necesita su propio snapshot manual vigente. */
    private function duplicarDireccionManual(PedidoBma $origen, PedidoBma $miembro): void
    {
        if ($origen->cliente_direccion_id) {
            return;
        }
        $vigente = PedidoBmaDireccion::query()->where('pedido_bma_id', $origen->id)->where('es_vigente', true)->latest('id')->first();
        if ($vigente === null) {
            return;
        }
        $copia = $vigente->replicate();
        $copia->pedido_bma_id = $miembro->id;
        $copia->version_snapshot = 1;
        $copia->save();
    }
}
