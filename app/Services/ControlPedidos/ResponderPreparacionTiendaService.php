<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\ControlPedidos\PedidoBmaTareaProducto;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
    ): PedidoBmaTareaPreparacion {
        if (! $usuario->can('control_pedidos.tienda.responder')) {
            throw new \RuntimeException('No tiene permiso para responder preparación.');
        }

        $faltantes = $this->requisitosService->validarRespuesta($tarea, $productos, $datosExtra);
        if ($faltantes !== []) {
            throw ValidationException::withMessages(['requisitos' => $faltantes]);
        }

        return DB::transaction(function () use ($tarea, $usuario, $productos, $evidencias, $observaciones, $versionEsperada, $datosExtra) {
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

            $this->guardarEvidencias($tarea, $evidencias, $usuario->id);
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

            $esTraslado = (bool) $tarea->requiere_traslado_cedis;
            $esMunicipio = (bool) $tarea->modalidad?->esEnvioMunicipio();
            $destino = $esTraslado
                ? PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_TRASLADO
                : ($esMunicipio
                    ? PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA
                    : PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA);

            $accion = $esTraslado ? 'lista_para_traslado' : ($esMunicipio ? 'lista_para_caratula' : 'responder');
            $comentario = $esTraslado
                ? 'Preparación lista para traslado a CEDIS.'
                : ($esMunicipio
                    ? 'Preparación lista para generar carátula municipal.'
                    : 'Preparación respondida por Tienda.');

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

            if ($esTraslado) {
                $this->crearTraspasoService->ejecutar($tarea->fresh(['productos', 'pedido.cliente', 'pedido.vendedor', 'almacen']), $usuario);
                $this->copiarRevisionesProducto($tarea->fresh(['productos', 'pedido']), $usuario->id);
            } elseif (! $esMunicipio) {
                $this->sincronizarPedido($tarea, $usuario->id);
            }

            $pedido = $tarea->pedido()->with(['cliente', 'vendedor', 'estatus'])->first();
            $this->historialService->ejecutar(
                $pedido->id,
                $usuario->id,
                $pedido->estatus->id,
                $pedido->estatus->id,
                $esTraslado
                    ? 'Tienda marcó la preparación lista para traslado.'
                    : ($esMunicipio
                        ? 'Tienda dejó la preparación lista para carátula municipal.'
                        : 'Tienda respondió la preparación del pedido.'),
                AccionesHistorialPedidoBma::RESPUESTA_PREPARACION_TIENDA
            );

            $this->notificarService->ejecutar(
                $pedido,
                $esTraslado ? 'pedido_preparacion_tienda_lista_traslado' : ($esMunicipio ? 'pedido_preparacion_tienda_lista_caratula' : 'pedido_preparacion_tienda_respondida'),
                $esTraslado
                    ? 'Tienda dejó la mercancía lista para traslado a CEDIS.'
                    : ($esMunicipio
                        ? 'Tienda dejó la mercancía lista para generar e imprimir carátula.'
                        : 'Tienda respondió la preparación de tu pedido. Confirma con el cliente y cierra la consulta.'),
                $esTraslado ? ['control_pedidos.tienda.trasladar'] : ($esMunicipio ? ['control_pedidos.tienda.generar_caratula'] : []),
                $usuario->id,
                true,
                ['url' => $esMunicipio
                    ? '/control-pedidos/tienda/'.$tarea->id
                    : '/control-pedidos?q='.urlencode((string) ($pedido->folio_remision ?: $pedido->folio ?: $pedido->id))]
            );

            return $tarea->fresh(['modalidad', 'almacen', 'productos', 'documentos', 'pedido.cliente', 'solicitudTraspaso', 'paqueteria']);
        });
    }

    /** @param list<UploadedFile> $evidencias */
    private function guardarEvidencias(PedidoBmaTareaPreparacion $tarea, array $evidencias, int $usuarioId): void
    {
        $archivos = array_values(array_filter(
            $evidencias,
            fn ($f) => $f instanceof UploadedFile && $f->isValid()
        ));

        foreach ($archivos as $archivo) {
            $ruta = $archivo->store("pedidos_bma/tareas_preparacion/{$tarea->id}", 'public');
            $tarea->documentos()->create([
                'tipo_evidencia' => PedidoBmaTareaDocumento::TIPO_EVIDENCIA_GENERAL,
                'ruta_interna' => $ruta,
                'nombre_original' => $archivo->getClientOriginalName(),
                'mime_type' => $archivo->getMimeType(),
                'tamano_bytes' => $archivo->getSize(),
                'hash_sha256' => hash_file('sha256', $archivo->getRealPath()),
                'subido_por_id' => $usuarioId,
                'subido_at' => now(),
            ]);
        }
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
            for ($i = 0; $i < max(1, (int) $p->cantidad_encontrada); $i++) {
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
