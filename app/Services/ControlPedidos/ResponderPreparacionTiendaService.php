<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\ControlPedidos\PedidoBmaTareaProducto;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\DesgloseSkuPreparacion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ResponderPreparacionTiendaService
{
    public function __construct(
        private CalcularRequisitosPreparacionService $requisitosService,
        private TransicionEstadoTareaPreparacionService $transicionService,
        private CrearTraspasoDesdeTareaPreparacionService $crearTraspasoService,
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    /**
     * @param  list<array{id: int, cantidad_encontrada: int, estado_fisico: string, observacion?: string|null}>  $productos
     * @param  list<UploadedFile>  $evidencias
     * @param  array<int|string, UploadedFile>  $evidenciasProducto
     * @param  array{peso_real_kg?: mixed, peso_volumetrico_kg?: mixed, catalogo_tipo_caja_id?: mixed, observaciones_fisicas?: mixed}  $datosExtra
     */
    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        User $usuario,
        array $productos,
        array $evidencias = [],
        ?string $observaciones = null,
        ?int $versionEsperada = null,
        array $datosExtra = [],
        array $evidenciasProducto = [],
    ): PedidoBmaTareaPreparacion {
        if (! $usuario->can('control_pedidos.tienda.responder')) {
            throw new \RuntimeException('No tiene permiso para responder preparación.');
        }

        $rutasNuevas = [];

        try {
            return DB::transaction(function () use (
                $tarea,
                $usuario,
                $productos,
                $evidencias,
                $evidenciasProducto,
                $observaciones,
                $versionEsperada,
                $datosExtra,
                &$rutasNuevas,
            ) {
            $tarea = PedidoBmaTareaPreparacion::query()->lockForUpdate()->findOrFail($tarea->id);
            $tarea->loadMissing(['modalidad', 'productos']);

            if ($tarea->estado !== PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION) {
                throw ValidationException::withMessages([
                    'estado' => 'Solo puede responder una tarea en atención.',
                ]);
            }

            if ((int) $tarea->asignada_a_id !== (int) $usuario->id) {
                throw ValidationException::withMessages([
                    'tarea' => 'Debe ser el responsable asignado para responder.',
                ]);
            }

            $faltanteSku = DesgloseSkuPreparacion::mensaje($tarea);
            if ($faltanteSku !== null) {
                throw ValidationException::withMessages(['productos' => $faltanteSku]);
            }

            foreach ($productos as $input) {
                /** @var PedidoBmaTareaProducto|null $producto */
                $producto = $tarea->productos()->where('id', $input['id'])->first();
                if (! $producto) {
                    continue;
                }
                $producto->update([
                    'cantidad_encontrada' => (int) $input['cantidad_encontrada'],
                    'estado_fisico' => $input['estado_fisico'],
                    'observacion' => $input['observacion'] ?? null,
                ]);
            }

            $rutasNuevas = array_merge(
                $rutasNuevas,
                $this->guardarEvidencias($tarea, $evidencias, $usuario->id),
                $this->guardarEvidenciasProducto($tarea, $evidenciasProducto, $usuario->id),
            );

            $tarea->unsetRelation('documentos');
            $tarea->load('documentos');
            $faltantes = $this->requisitosService->validarRespuesta($tarea, $productos, $datosExtra);
            if ($faltantes !== []) {
                throw ValidationException::withMessages(['requisitos' => $faltantes]);
            }

            $tarea->documentos()->where('inmutable', false)->update(['inmutable' => true]);

            $updates = [
                'observaciones_respuesta' => $observaciones,
                'atendida_por_id' => $usuario->id,
                'atendida_at' => now(),
            ];
            if (array_key_exists('peso_real_kg', $datosExtra) && $datosExtra['peso_real_kg'] !== null && $datosExtra['peso_real_kg'] !== '') {
                $updates['peso_real_kg'] = (float) $datosExtra['peso_real_kg'];
            }
            if (array_key_exists('peso_volumetrico_kg', $datosExtra) && $datosExtra['peso_volumetrico_kg'] !== null && $datosExtra['peso_volumetrico_kg'] !== '') {
                $updates['peso_volumetrico_kg'] = (float) $datosExtra['peso_volumetrico_kg'];
            }
            if (! empty($datosExtra['catalogo_tipo_caja_id'])) {
                $updates['catalogo_tipo_caja_id'] = (int) $datosExtra['catalogo_tipo_caja_id'];
            }
            if (array_key_exists('observaciones_fisicas', $datosExtra)) {
                $updates['observaciones_fisicas'] = $datosExtra['observaciones_fisicas'];
            }
            $tarea->update($updates);

            $tarea->unsetRelation('productos');
            $tarea->load('productos');
            $requisitos = $this->requisitosService->efectivos($tarea);
            $esTraslado = (bool) ($requisitos['traslado_cedis'] ?? false);
            if ((bool) $tarea->requiere_traslado_cedis !== $esTraslado) {
                $tarea->update(['requiere_traslado_cedis' => $esTraslado]);
            }
            $piezasEncontradas = (int) $tarea->productos->sum(fn ($p) => (int) $p->cantidad_encontrada);
            $hayFaltante = $tarea->productos->contains(
                fn ($p) => (int) $p->cantidad_encontrada < (int) $p->cantidad_solicitada
            );
            $trasladoConPiezas = $esTraslado && $piezasEncontradas > 0;

            $esMunicipio = (bool) $tarea->modalidad?->esEnvioMunicipio();
            $destino = $trasladoConPiezas
                ? PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_TRASLADO
                : ($esMunicipio
                    ? PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA
                    : PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA);

            $accion = $trasladoConPiezas ? 'lista_para_traslado' : ($esMunicipio ? 'lista_para_caratula' : 'responder');
            $comentario = $trasladoConPiezas
                ? 'Preparación lista para traslado a CEDIS.'
                : ($esTraslado
                    ? 'Tienda no encontró unidades. La consulta queda respondida para Ventas.'
                    : ($esMunicipio
                        ? 'Preparación lista para generar carátula municipal.'
                        : 'Preparación respondida por Tienda.'));

            $tarea = $this->transicionService->ejecutar(
                $tarea,
                $destino,
                $usuario->id,
                $accion,
                $comentario,
                null,
                $versionEsperada,
                $usuario
            );

            if ($trasladoConPiezas) {
                $this->crearTraspasoService->ejecutar($tarea->fresh(['productos', 'pedido.cliente', 'pedido.vendedor', 'almacen']), $usuario);
                $this->copiarRevisionesProducto($tarea->fresh(['productos', 'pedido']), $usuario->id);
            } elseif (! $esMunicipio) {
                $this->sincronizarPedido($tarea, $usuario->id);
            }

            if ($esTraslado && $hayFaltante) {
                $pedidoFaltante = $tarea->pedido()->with(['cliente', 'vendedor'])->first();
                $this->notificarService->ejecutar(
                    $pedidoFaltante,
                    'pedido_preparacion_tienda_faltante',
                    $piezasEncontradas > 0
                        ? 'Tienda encontró solo parte de las piezas. Revise el faltante antes de continuar.'
                        : 'Tienda no encontró piezas. No se generó traslado.',
                    [],
                    $usuario->id,
                    true,
                    ['url' => '/control-pedidos?q='.urlencode((string) ($pedidoFaltante->folio ?: $pedidoFaltante->id))]
                );
            }

            $pedido = $tarea->pedido()->with(['cliente', 'vendedor', 'estatus'])->first();
            $this->historialService->ejecutar(
                $pedido->id,
                $usuario->id,
                $pedido->estatus->id,
                $pedido->estatus->id,
                $trasladoConPiezas
                    ? 'Tienda marcó la preparación lista para traslado.'
                    : ($esTraslado
                        ? 'Tienda respondió sin unidades para traslado.'
                        : ($esMunicipio
                            ? 'Tienda dejó la preparación lista para carátula municipal.'
                            : 'Tienda respondió la preparación del pedido.')),
                AccionesHistorialPedidoBma::RESPUESTA_PREPARACION_TIENDA
            );

            $this->notificarService->ejecutar(
                $pedido,
                $trasladoConPiezas ? 'pedido_preparacion_tienda_lista_traslado' : ($esMunicipio ? 'pedido_preparacion_tienda_lista_caratula' : 'pedido_preparacion_tienda_respondida'),
                $trasladoConPiezas
                    ? 'Tienda dejó la mercancía lista para traslado a CEDIS.'
                    : ($esMunicipio
                        ? 'Tienda dejó la mercancía lista para generar e imprimir carátula.'
                        : 'Tienda respondió la preparación de tu pedido. Confirma con el cliente y cierra la consulta.'),
                $trasladoConPiezas ? ['control_pedidos.tienda.trasladar'] : ($esMunicipio ? ['control_pedidos.tienda.generar_caratula'] : []),
                $usuario->id,
                true,
                ['url' => $esMunicipio
                    ? '/control-pedidos/tienda/'.$tarea->id
                    : '/control-pedidos?q='.urlencode((string) ($pedido->folio_remision ?: $pedido->folio ?: $pedido->id))]
            );

            return $tarea->fresh(['modalidad', 'almacen', 'productos', 'documentos', 'pedido.cliente', 'solicitudTraspaso', 'paqueteria']);
            });
        } catch (\Throwable $e) {
            if ($rutasNuevas !== []) {
                Storage::disk('public')->delete($rutasNuevas);
            }
            throw $e;
        }
    }

    /**
     * @param  list<UploadedFile>  $evidencias
     * @return list<string>
     */
    private function guardarEvidencias(PedidoBmaTareaPreparacion $tarea, array $evidencias, int $usuarioId): array
    {
        return $this->persistirArchivos($tarea, $evidencias, $usuarioId, PedidoBmaTareaDocumento::TIPO_EVIDENCIA_GENERAL, null);
    }

    /**
     * @param  array<int|string, UploadedFile>  $evidenciasProducto
     * @return list<string>
     */
    private function guardarEvidenciasProducto(PedidoBmaTareaPreparacion $tarea, array $evidenciasProducto, int $usuarioId): array
    {
        $rutas = [];
        foreach ($evidenciasProducto as $productoId => $archivo) {
            if (! $archivo instanceof UploadedFile || ! $archivo->isValid()) {
                continue;
            }
            $id = (int) $productoId;
            if (! $tarea->productos()->where('id', $id)->exists()) {
                continue;
            }
            $rutas = array_merge(
                $rutas,
                $this->persistirArchivos(
                    $tarea,
                    [$archivo],
                    $usuarioId,
                    PedidoBmaTareaDocumento::TIPO_EVIDENCIA_PRODUCTO,
                    $id,
                )
            );
        }

        return $rutas;
    }

    /**
     * @param  list<UploadedFile>  $evidencias
     * @return list<string>
     */
    private function persistirArchivos(
        PedidoBmaTareaPreparacion $tarea,
        array $evidencias,
        int $usuarioId,
        string $tipo,
        ?int $productoId,
    ): array {
        $archivos = array_values(array_filter(
            $evidencias,
            fn ($f) => $f instanceof UploadedFile && $f->isValid()
        ));
        $rutas = [];

        foreach ($archivos as $archivo) {
            $ruta = $archivo->store("pedidos_bma/tareas_preparacion/{$tarea->id}", 'public');
            $rutas[] = $ruta;
            $tarea->documentos()->create([
                'pedido_bma_tarea_producto_id' => $productoId,
                'tipo_evidencia' => $tipo,
                'ruta_interna' => $ruta,
                'nombre_original' => $archivo->getClientOriginalName(),
                'mime_type' => $archivo->getMimeType(),
                'tamano_bytes' => $archivo->getSize(),
                'hash_sha256' => hash_file('sha256', $archivo->getRealPath()),
                'subido_por_id' => $usuarioId,
                'subido_at' => now(),
            ]);
        }

        return $rutas;
    }

    private function sincronizarPedido(PedidoBmaTareaPreparacion $tarea, int $usuarioId): void
    {
        $this->aplicarSincronizacionPedido($tarea, $usuarioId);
    }

    public function aplicarSincronizacionPedido(PedidoBmaTareaPreparacion $tarea, int $usuarioId): void
    {
        $tarea->loadMissing(['productos', 'pedido.estatus']);
        $pedido = $tarea->pedido;

        $pedido->update([
            'pesaje_respondido_at' => now(),
            'pesaje_respondido_por_id' => $usuarioId,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PESAJE_LISTO,
        ]);

        $this->copiarRevisionesProducto($tarea, $usuarioId);
    }

    private function copiarRevisionesProducto(PedidoBmaTareaPreparacion $tarea, int $usuarioId): void
    {
        $tarea->loadMissing(['productos', 'pedido']);
        $pedido = $tarea->pedido;
        if (! $pedido) {
            return;
        }

        $orden = (int) $pedido->revisionesProducto()->max('orden');
        $pedido->revisionesProducto()->delete();

        foreach ($tarea->productos as $p) {
            $cantidad = (int) $p->cantidad_encontrada;
            if ($cantidad <= 0) {
                continue;
            }
            for ($i = 0; $i < $cantidad; $i++) {
                $pedido->revisionesProducto()->create([
                    'orden' => ++$orden,
                    'descripcion_producto' => $p->descripcion_snapshot,
                    'producto_id' => $p->producto_id,
                    'sku' => $p->sku,
                    'estado_fisico' => $p->estado_fisico,
                    'comentario' => $p->observacion,
                ]);
            }
        }

        $estados = $tarea->productos->pluck('estado_fisico')->filter()->values();
        if ($estados->isNotEmpty()) {
            $peor = $estados->contains(PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA)
                ? PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA
                : ($estados->contains(PedidoBmaRevisionProducto::ESTADO_DANADO)
                    ? PedidoBmaRevisionProducto::ESTADO_DANADO
                    : $estados->first());
            $pedido->update([
                'estado_fisico_general' => $peor,
                'tiene_observaciones_fisicas' => $estados->contains(fn ($e) => $e !== PedidoBmaRevisionProducto::ESTADO_BUENO),
            ]);
        }
    }
}
