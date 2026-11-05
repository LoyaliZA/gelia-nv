<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\RegistrarPedidos\DestinoRevisionRegistrarPedidos;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Sin remisión documenta el ajuste. Con remisión abre un pedido relacionado y su propia cobertura. */
final class RegistrarAnexoTiendaService
{
    public function __construct(
        private GenerarFolioPedidoBmaService $folios,
        private RegistrarHistorialPedidoService $historial,
        private NotificarPedidoBmaService $notificar,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $piezas
     */
    public function ejecutar(
        PedidoBma $pedido,
        User $usuario,
        ?string $sku,
        ?string $descripcion,
        ?int $cantidad,
        ?string $condicion,
        UploadedFile $evidencia,
        array $piezas = [],
        string $entrada = 'catalogo',
    ): PedidoBma {
        if (! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)) {
            throw ValidationException::withMessages([
                'permiso' => 'Quien registró el pedido solicita el anexo.',
            ]);
        }

        $lineas = $this->lineasDe($sku, $descripcion, $cantidad, $condicion, $piezas, $entrada);

        return DB::transaction(function () use ($pedido, $usuario, $evidencia, $lineas) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);

            if ($pedido->tieneRemision() || filled($pedido->numero_remision)) {
                return $this->pedidoRelacionado($pedido, $usuario, $lineas, $evidencia);
            }

            $ruta = $evidencia->store('pedidos_bma/'.$pedido->id.'/anexo', 'public');
            $documento = $pedido->documentos()->create([
                'tipo' => PedidoBmaDocumento::TIPO_ANEXO_PIEZAS,
                'ruta_archivo' => $ruta,
                'nombre_original' => $evidencia->getClientOriginalName(),
                'mime_type' => $evidencia->getMimeType(),
                'tamano_bytes' => $evidencia->getSize(),
                'orden' => ((int) $pedido->documentos()->max('orden')) + 1,
                'comentario' => $this->comentarioDe($lineas),
                'activo' => true,
            ]);
            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $lineas = array_map(fn ($linea) => array_merge($linea, ['pendiente_revision' => true, 'documento_id' => $documento->id]), $lineas);
            if (is_array($json['revision_disponibilidad'] ?? null)) {
                $json['revision_disponibilidad']['aceptacion_vigente'] = false;
            }
            $json['anexo_sin_remision'] = array_values(array_merge(
                is_array($json['anexo_sin_remision'] ?? null) ? $json['anexo_sin_remision'] : [],
                $lineas
            ));
            $json['anexo_integrado'] = false;
            $pedido->update(['solicitud_preparacion_json' => $json, 'consulta_cerrada_at' => null, 'consulta_cerrada_por_id' => null,
                'estatus_envio' => ($json['area_revision'] ?? '') === 'control_pedidos' ? PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE : $pedido->estatus_envio]);
            $this->nota($pedido, $usuario->id, $this->textoDe($lineas, false));
            $this->avisarEjecutor($pedido, $usuario, $lineas);

            return $pedido->fresh(['documentos', 'estatus']);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function pedidoRelacionado(PedidoBma $pedido, User $usuario, array $lineas, UploadedFile $evidencia): PedidoBma
    {
        $estatus = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_BORRADOR);
        if (! $estatus) {
            throw new \RuntimeException('No se encontró el estatus de borrador.');
        }

        $nuevo = PedidoBma::query()->create([
            'folio' => $this->folios->ejecutarComplemento($pedido),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $pedido->vendedor_id,
            'cliente_id' => $pedido->cliente_id,
            'departamento_operacion_id' => $pedido->departamento_operacion_id,
            'origen_id' => $pedido->origen_id,
            'almacen_id' => $pedido->almacen_id,
            'sucursal_destino_id' => $pedido->sucursal_destino_id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'pedido_principal_id' => $pedido->id,
            'total_mercancia' => 0,
            'costo_envio' => 0,
            'saldo_a_favor' => 0,
            'costo_seguro' => 0,
            'total_a_cobrar' => 0,
            'aplica_seguro' => false,
            'es_resguardo' => false,
            'prioridad_md' => false,
            'solicitud_preparacion_json' => [
                'version' => 1,
                'modalidad_entrega' => 'tienda',
                'anexo_de_pedido_id' => $pedido->id,
                'anexo_integrado' => false,
                'piezas_anexo' => $lineas,
                'cobertura_tienda' => null,
            ],
        ]);
        $ruta = $evidencia->store('pedidos_bma/'.$nuevo->id.'/anexo', 'public');
        $nuevo->documentos()->create([
            'tipo' => PedidoBmaDocumento::TIPO_ANEXO_PIEZAS,
            'ruta_archivo' => $ruta,
            'nombre_original' => $evidencia->getClientOriginalName(),
            'mime_type' => $evidencia->getMimeType(),
            'tamano_bytes' => $evidencia->getSize(),
            'orden' => 1,
            'comentario' => $this->comentarioDe($lineas),
            'activo' => true,
        ]);

        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $relacionados = is_array($json['anexos_relacionados'] ?? null) ? $json['anexos_relacionados'] : [];
        $relacionados[] = $nuevo->id;
        $json['anexos_relacionados'] = array_values(array_unique($relacionados));
        $pedido->update(['solicitud_preparacion_json' => $json]);
        $this->nota($pedido, $usuario->id, $this->textoDe($lineas, true, $nuevo->folio));
        $this->avisarEjecutor($pedido, $usuario, $lineas);

        return $nuevo->fresh(['documentos', 'estatus']);
    }

    /**
     * @param  list<array<string, mixed>>  $piezas
     * @return list<array<string, mixed>>
     */
    private function lineasDe(?string $sku, ?string $descripcion, ?int $cantidad, ?string $condicion, array $piezas, string $entrada): array
    {
        if ($entrada === 'documento') {
            return [[
                'entrada' => 'documento',
                'pendiente_identificacion' => true,
                'solicitado_at' => now()->toIso8601String(),
            ]];
        }

        $fuente = $piezas !== [] ? $piezas : [[
            'sku' => $sku,
            'descripcion' => $descripcion,
            'cantidad' => $cantidad,
            'condicion' => $condicion,
            'producto_id' => null,
        ]];
        $salida = [];
        foreach ($fuente as $fila) {
            if (! is_array($fila)) {
                throw ValidationException::withMessages(['piezas' => 'Seleccione el producto y la cantidad. No se inventa un SKU.']);
            }
            $skuFila = trim((string) ($fila['sku'] ?? ''));
            $descripcionFila = trim((string) ($fila['descripcion'] ?? ''));
            $cantidadFila = (int) ($fila['cantidad'] ?? 0);
            $condicionFila = trim((string) ($fila['condicion'] ?? $condicion ?? ''));
            if ($skuFila === '' || $descripcionFila === '' || $cantidadFila < 1) {
                throw ValidationException::withMessages([
                    'piezas' => 'Seleccione el producto y la cantidad. No se inventa un SKU.',
                ]);
            }
            $salida[] = [
                'entrada' => 'catalogo',
                'sku' => $skuFila,
                'descripcion' => $descripcionFila,
                'cantidad' => $cantidadFila,
                'condicion' => $condicionFila !== '' ? $condicionFila : 'Pendiente de revisión',
                'producto_id' => isset($fila['producto_id']) && (int) $fila['producto_id'] > 0 ? (int) $fila['producto_id'] : null,
                'pendiente_identificacion' => false,
            ];
        }

        return $salida;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function comentarioDe(array $lineas): string
    {
        if (($lineas[0]['entrada'] ?? '') === 'documento') {
            return 'Anexo documental pendiente de identificación';
        }

        return collect($lineas)->map(fn (array $linea) => trim($linea['descripcion'].' × '.$linea['cantidad']))->implode('; ');
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function textoDe(array $lineas, bool $conRemision, ?string $folio = null): string
    {
        if (($lineas[0]['entrada'] ?? '') === 'documento') {
            return $conRemision
                ? "Anexo documental con remisión: pedido relacionado {$folio}. Falta identificar las piezas, su cobertura y la integración. Adjuntar el archivo no confirma disponibilidad."
                : 'Anexo documental pendiente de identificación. Quien prepara reconoce las piezas. Adjuntar el archivo no confirma disponibilidad ni se cobra como mercancía ya cubierta.';
        }

        return $conRemision
            ? "Anexo con remisión: pedido relacionado {$folio}. La remisión y los pagos del principal se conservan. Falta cobertura, recepción e integración física."
            : 'Anexo con productos seleccionados. Quien prepara lo revisa. No se cobra como mercancía ya cubierta ni confirma disponibilidad.';
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function avisarEjecutor(PedidoBma $pedido, User $usuario, array $lineas): void
    {
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $area = (string) ($json['area_revision'] ?? '');
        $permiso = $area === DestinoRevisionRegistrarPedidos::CONTROL_PEDIDOS
            ? 'control_pedidos.cedis'
            : 'control_pedidos.tienda.ver';
        $this->notificar->ejecutar(
            $pedido,
            'pedido_anexo_solicitado',
            $this->textoDe($lineas, false),
            [$permiso],
            $usuario->id,
            false
        );
    }

    private function nota(PedidoBma $pedido, int $usuarioId, string $texto): void
    {
        $estatusId = $pedido->catalogo_estatus_pedido_id;
        if ($estatusId) {
            $this->historial->ejecutar($pedido->id, $usuarioId, $estatusId, $estatusId, $texto, AccionesHistorialPedidoBma::COMPLEMENTO);
        }
    }
}
