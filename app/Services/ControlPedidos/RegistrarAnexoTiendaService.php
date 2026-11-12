<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\ActualizacionConsulta;
use App\Support\ControlPedidos\RegistrarPedidos\DestinoRevisionRegistrarPedidos;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Sin remisión documenta el ajuste. Con remisión abre un pedido relacionado y su propia cobertura. */
final class RegistrarAnexoTiendaService
{
    public function __construct(
        private GenerarFolioPedidoBmaService $folios,
        private RegistrarHistorialPedidoService $historial,
        private NotificarPedidoBmaService $notificar,
        private AdjuntosConsultaTienda $adjuntos,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $piezas
     * @param  list<UploadedFile>  $evidencias
     */
    public function ejecutar(
        PedidoBma $pedido,
        User $usuario,
        ?string $sku,
        ?string $descripcion,
        ?int $cantidad,
        ?string $condicion,
        ?UploadedFile $evidencia,
        array $piezas = [],
        string $entrada = 'catalogo',
        array $evidencias = [],
        ?string $solicitudId = null,
    ): PedidoBma {
        if (! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)) {
            throw ValidationException::withMessages([
                'permiso' => 'Quien registró el pedido solicita el anexo.',
            ]);
        }

        $lineas = $this->lineasDe($sku, $descripcion, $cantidad, $condicion, $piezas, $entrada);
        $archivos = $this->adjuntos->sinRepetir([], $this->archivos($evidencia, $evidencias));

        return DB::transaction(function () use ($pedido, $usuario, $lineas, $archivos, $solicitudId) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            if ($solicitudId) {
                $previo = $this->destinoDeSolicitud($pedido, $solicitudId);
                if ($previo instanceof PedidoBma) {
                    return $this->agregarArchivos($previo, $solicitudId, $archivos);
                }
            }
            if ($archivos === []) {
                throw ValidationException::withMessages(['evidencia' => 'Adjunte el PDF o la captura del anexo.']);
            }

            if ($pedido->tieneRemision() || filled($pedido->numero_remision)) {
                return $this->pedidoRelacionado($pedido, $usuario, $lineas, $archivos, $solicitudId);
            }

            $cerrada = $pedido->consulta_cerrada_at !== null;
            $ciclo = $this->adjuntos->ciclo($pedido, $cerrada);
            $this->adjuntos->asegurar($this->adjuntos->bytesUsados($pedido, $ciclo), $archivos);
            $docs = $this->guardar($pedido, $archivos, 'anexo', PedidoBmaDocumento::TIPO_ANEXO_PIEZAS, $this->comentarioDe($lineas));
            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $meta = $this->meta($docs, $ciclo, $solicitudId);
            $lineas = array_map(fn ($linea) => array_merge($linea, $meta), $lineas);
            if (is_array($json['revision_disponibilidad'] ?? null)) {
                $json['revision_disponibilidad']['aceptacion_vigente'] = false;
                $json['revision_disponibilidad']['ciclo_id'] = $ciclo;
            }
            $json['anexo_sin_remision'] = array_values(array_merge(
                is_array($json['anexo_sin_remision'] ?? null) ? $json['anexo_sin_remision'] : [],
                $lineas
            ));
            $json['anexo_integrado'] = false;
            ActualizacionConsulta::solicitar($json);
            $pedido->update(['solicitud_preparacion_json' => $json, 'consulta_cerrada_at' => null, 'consulta_cerrada_por_id' => null,
                'estatus_envio' => ($json['area_revision'] ?? '') === 'control_pedidos' ? PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE : $pedido->estatus_envio]);
            $this->nota($pedido, $usuario->id, $this->textoDe($lineas, false));
            $this->avisarEjecutor($pedido, $usuario, $lineas);

            return $pedido->fresh(['documentos', 'estatus']);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  list<UploadedFile>  $archivos
     */
    private function pedidoRelacionado(PedidoBma $pedido, User $usuario, array $lineas, array $archivos, ?string $solicitudId): PedidoBma
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
        $ciclo = $this->adjuntos->ciclo($nuevo, true);
        $this->adjuntos->asegurar(0, $archivos);
        $docs = $this->guardar($nuevo, $archivos, 'anexo', PedidoBmaDocumento::TIPO_ANEXO_PIEZAS, $this->comentarioDe($lineas));
        $preparacion = $nuevo->solicitud_preparacion_json;
        $preparacion['revision_disponibilidad']['ciclo_id'] = $ciclo;
        $preparacion['piezas_anexo'] = array_map(fn ($linea) => array_merge($linea, $this->meta($docs, $ciclo, $solicitudId)), $lineas);
        $nuevo->update(['solicitud_preparacion_json' => $preparacion]);

        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $relacionados = is_array($json['anexos_relacionados'] ?? null) ? $json['anexos_relacionados'] : [];
        $relacionados[] = $nuevo->id;
        $json['anexos_relacionados'] = array_values(array_unique($relacionados));
        if ($solicitudId) {
            $json['anexos_por_solicitud'][$solicitudId] = $nuevo->id;
        }
        $pedido->update(['solicitud_preparacion_json' => $json]);
        $this->nota($pedido, $usuario->id, $this->textoDe($lineas, true, $nuevo->folio));
        $this->avisarEjecutor($pedido, $usuario, $lineas);

        return $nuevo->fresh(['documentos', 'estatus']);
    }

    /**
     * @param  list<UploadedFile>  $evidencias
     * @return list<UploadedFile>
     */
    private function archivos(?UploadedFile $evidencia, array $evidencias): array
    {
        $lista = [];
        foreach ($evidencias as $archivo) {
            if ($archivo instanceof UploadedFile) {
                $lista[] = $archivo;
            }
        }
        if ($evidencia instanceof UploadedFile) {
            $lista[] = $evidencia;
        }

        return $lista;
    }

    private function destinoDeSolicitud(PedidoBma $pedido, string $solicitudId): ?PedidoBma
    {
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        foreach ((array) ($json['anexo_sin_remision'] ?? []) as $linea) {
            if (is_array($linea) && ($linea['solicitud_id'] ?? null) === $solicitudId) {
                return $pedido;
            }
        }
        $relacionado = $json['anexos_por_solicitud'][$solicitudId] ?? null;
        if (is_numeric($relacionado)) {
            return PedidoBma::query()->lockForUpdate()->find((int) $relacionado);
        }

        return null;
    }

    /**
     * @param  list<UploadedFile>  $archivos
     */
    private function agregarArchivos(PedidoBma $pedido, string $solicitudId, array $archivos): PedidoBma
    {
        $conocidas = [];
        $jsonInicial = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        foreach ((array) ($jsonInicial['anexo_sin_remision'] ?? []) as $linea) {
            if (is_array($linea) && ($linea['solicitud_id'] ?? null) === $solicitudId) {
                $conocidas = array_merge($conocidas, (array) ($linea['huellas'] ?? []));
            }
        }
        foreach ((array) ($jsonInicial['piezas_anexo'] ?? []) as $linea) {
            if (is_array($linea) && ($linea['solicitud_id'] ?? null) === $solicitudId) {
                $conocidas = array_merge($conocidas, (array) ($linea['huellas'] ?? []));
            }
        }
        $nuevos = $this->adjuntos->sinRepetir($conocidas, $archivos);
        if ($nuevos === []) {
            return $pedido->fresh(['documentos', 'estatus']);
        }
        $ciclo = $this->adjuntos->ciclo($pedido, false);
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $this->adjuntos->asegurar($this->adjuntos->bytesUsados($pedido, $ciclo), $nuevos);
        $docs = $this->guardar($pedido, $nuevos, 'anexo', PedidoBmaDocumento::TIPO_ANEXO_PIEZAS, 'Archivos adicionales del anexo');
        foreach (['anexo_sin_remision', 'piezas_anexo'] as $clave) {
            foreach ((array) ($json[$clave] ?? []) as $indice => $linea) {
                if (! is_array($linea) || ($linea['solicitud_id'] ?? null) !== $solicitudId) {
                    continue;
                }
                $documentos = array_values(array_merge((array) ($linea['documentos'] ?? []), $docs));
                $json[$clave][$indice]['documentos'] = $documentos;
                $json[$clave][$indice]['huellas'] = array_values(array_merge((array) ($linea['huellas'] ?? []), array_column($docs, 'huella')));
                $json[$clave][$indice]['tamano_bytes'] = (int) ($linea['tamano_bytes'] ?? 0) + array_sum(array_column($docs, 'tamano_bytes'));
                $json[$clave][$indice]['documento_id'] = $json[$clave][$indice]['documento_id'] ?? $docs[0]['id'];
                $json[$clave][$indice]['ciclo_id'] = $linea['ciclo_id'] ?? $ciclo;
            }
        }
        $pedido->update(['solicitud_preparacion_json' => $json]);

        return $pedido->fresh(['documentos', 'estatus']);
    }

    /**
     * @param  list<array<string, mixed>>  $docs
     * @return array<string, mixed>
     */
    private function meta(array $docs, string $ciclo, ?string $solicitudId): array
    {
        return [
            'pendiente_revision' => true,
            'documento_id' => $docs[0]['id'] ?? null,
            'documentos' => $docs,
            'huellas' => array_column($docs, 'huella'),
            'tamano_bytes' => array_sum(array_column($docs, 'tamano_bytes')),
            'ciclo_id' => $ciclo,
            ...($solicitudId ? ['solicitud_id' => $solicitudId] : []),
        ];
    }

    /**
     * @param  list<UploadedFile>  $archivos
     * @return list<array{id: int, nombre: string, mime_type: ?string, tamano_bytes: int, huella: string}>
     */
    private function guardar(PedidoBma $pedido, array $archivos, string $carpeta, string $tipo, string $comentario): array
    {
        $guardados = [];
        $rutas = [];
        $orden = (int) $pedido->documentos()->max('orden');
        try {
            foreach ($archivos as $archivo) {
                $orden++;
                $ruta = $archivo->store('pedidos_bma/'.$pedido->id.'/'.$carpeta, 'public');
                $rutas[] = $ruta;
                $documento = $pedido->documentos()->create([
                    'tipo' => $tipo,
                    'ruta_archivo' => $ruta,
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'mime_type' => $archivo->getMimeType(),
                    'tamano_bytes' => $archivo->getSize(),
                    'orden' => $orden,
                    'comentario' => $comentario,
                    'activo' => true,
                ]);
                $guardados[] = [
                    'id' => $documento->id,
                    'nombre' => $documento->nombre_original,
                    'mime_type' => $documento->mime_type,
                    'tamano_bytes' => (int) $documento->tamano_bytes,
                    'huella' => $this->adjuntos->huella($archivo),
                ];
            }
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($rutas);
            throw $e;
        }

        return $guardados;
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
            'pedido_consulta_actualizada',
            'Actualización de consulta: revise las piezas anexadas y envíe una nueva captura del pedido.',
            [$permiso],
            $usuario->id,
            false,
            ['url' => $area === 'control_pedidos' ? '/control-pedidos/cedis?q='.rawurlencode($pedido->folio) : '/control-pedidos/tienda?tarea='.$pedido->instruccionEntregaVigente?->id]
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
