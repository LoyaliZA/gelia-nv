<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\ActualizacionConsulta;
use App\Support\ControlPedidos\InstruccionEntrega;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use App\Support\ControlPedidos\RegistrarPedidos\DestinoRevisionRegistrarPedidos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Respuesta inicial por excepciones. No aparta, no traslada y no confirma pago. */
class ResponderDisponibilidadInicialService
{
    public const DECISION_EXCEPCIONES = 'La respuesta incluye piezas con excepción. El pedido se cierra con Cerrar consulta. Para cambiar piezas, registre un anexo o un retiro.';

    public const DECISION_TIENDA = 'Revisión sin excepciones. Falta el apartado físico de lo aceptado. No hay pago ni entrega.';

    public const DECISION_CEDIS = 'Consulta de disponibilidad sin excepciones. No hay traslado, pago ni entrega.';

    public const MENSAJE_INCOMPLETA = 'La revisión no está concluida. Confirme el pedido completo con evidencia o indique las piezas con excepción.';

    public const MENSAJE_FOTO = 'Adjunte una foto del conjunto físico. El PDF o la captura del pedido no la sustituyen.';

    public function __construct(
        private RegistrarHistorialPedidoService $historial,
        private NotificarPedidoBmaService $notificar,
        private RegistrarProductosPreparacionService $productos,
    ) {}

    /**
     * @param  array{confirmacion_explicita?: bool, excepciones?: list<array<string, mixed>>}  $datos
     * @param  list<UploadedFile>  $evidencias
     * @return array{estado: string, decision: string, idempotente: bool}
     */
    public function ejecutar(PedidoBma $pedido, User $usuario, array $datos, array $evidencias, ?PedidoBmaTareaPreparacion $tarea = null): array
    {
        return DB::transaction(function () use ($pedido, $usuario, $datos, $evidencias, $tarea) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $pedido->loadMissing('estatus');
            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $area = (string) ($json['area_revision'] ?? '');
            $actualizacion = ActualizacionConsulta::pendiente($json);
            if ($actualizacion && ($pedido->pedidoPagadoORemisionado() || $pedido->cancelado_at || $pedido->empacado_at || InstruccionEntrega::mercanciaYaSalio($pedido))) {
                throw ValidationException::withMessages(['consulta' => 'Este pedido requiere su gestión documentada.']);
            }
            if ($actualizacion && ($json['revision_disponibilidad']['actualizacion_id'] ?? null) !== ($datos['actualizacion_id'] ?? null)) {
                throw ValidationException::withMessages(['consulta' => 'La consulta recibió otros cambios. Actualice antes de responder.']);
            }

            if ($tarea !== null) {
                $tarea = InstruccionEntrega::bloquearParaEjecutar($tarea);
                if ((int) $tarea->pedido_bma_id !== (int) $pedido->id) {
                    throw ValidationException::withMessages(['tarea' => 'La revisión no corresponde a este pedido.']);
                }
                if ((int) $tarea->asignada_a_id !== (int) $usuario->id
                    || ! in_array($tarea->estado, ['EN_ATENCION', 'RESPONDIDA'], true)) {
                    throw ValidationException::withMessages(['tarea' => 'Tome la revisión antes de responder.']);
                }
                if ($area !== DestinoRevisionRegistrarPedidos::PREPARACION_TIENDA) {
                    throw ValidationException::withMessages(['area_revision' => 'Esta tarea no es la revisión de tienda.']);
                }
            } elseif ($area !== DestinoRevisionRegistrarPedidos::CONTROL_PEDIDOS
                || (! in_array($pedido->estatus_envio, [PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE, PedidoBma::ESTATUS_ENVIO_PESAJE_LISTO], true))) {
                throw ValidationException::withMessages(['area_revision' => 'Este pedido no está pendiente de consulta de disponibilidad.']);
            }

            $guardada = is_array($json['revision_disponibilidad'] ?? null) ? $json['revision_disponibilidad'] : null;
            if (! $actualizacion && is_array($guardada) && isset($guardada['estado'])) {
                return $this->resultado((string) $guardada['estado'], $area, true);
            }

            // Una actualización revisa el conjunto: solo se recapturan sus nuevas excepciones.
            $excepciones = $this->excepciones($datos['excepciones'] ?? [], $tarea === null || $actualizacion);
            if ($actualizacion) {
                $fisica = collect($json['compromisos_inventario'] ?? [])->contains(fn ($c) => $c['activo'] ?? false)
                    || in_array($tarea?->cumplimientoFisico?->estado, ['SEPARADA', 'APARTADO_CONFIRMADO', 'LISTA_PARA_SALIDA', 'DESPACHADA', 'ENTREGADA'], true);
                foreach ($json['retiro_piezas'] ?? [] as $retiro) {
                    if (($retiro['estado'] ?? 'pendiente') === 'pendiente' && $fisica) {
                        throw ValidationException::withMessages(['retiro' => 'Aplique el retiro físico antes de enviar la nueva respuesta.']);
                    }
                }
            }

            if ($tarea === null || $actualizacion) {
                foreach ($excepciones as $indice => $excepcion) {
                    if ($excepcion['condicion'] === 'detalle' && $excepcion['evidencias'] === []) {
                        throw ValidationException::withMessages(["excepciones.{$indice}.evidencias" => 'Adjunte una foto del producto con detalle.']);
                    }
                }
            }
            $confirmada = $actualizacion ? $excepciones === [] : (bool) ($datos['confirmacion_explicita'] ?? false);
            $cambiosRevisados = [];
            foreach ($datos['cambios_revisados'] ?? [] as $cambio) {
                $clave = $cambio['tipo'] === 'anexo' ? 'anexo_sin_remision' : 'retiro_piezas';
                $indice = (int) $cambio['indice'];
                if (! $actualizacion || ! isset($json[$clave][$indice])) {
                    throw ValidationException::withMessages(['cambios_revisados' => 'La solicitud cambió. Actualice la consulta.']);
                }
                if ($cambio['resultado'] === 'incidencia' && $excepciones === []) {
                    throw ValidationException::withMessages(['cambios_revisados' => 'Registre las piezas con detalle antes de enviar.']);
                }
                $cambiosRevisados[$cambio['tipo'].':'.$indice] = $cambio['resultado'];
            }
            if ($actualizacion && array_key_exists('cambios_revisados', $datos)) {
                foreach ($json['anexo_sin_remision'] ?? [] as $indice => $anexo) {
                    if (empty($anexo['respondida_at']) && ! isset($cambiosRevisados['anexo:'.$indice])) {
                        throw ValidationException::withMessages(['cambios_revisados' => 'Revise las capturas y piezas solicitadas antes de enviar.']);
                    }
                }
            }
            $archivos = array_values(array_filter($evidencias, fn ($archivo) => $archivo instanceof UploadedFile));
            $imagenesNuevas = array_values(array_filter(
                $archivos,
                fn (UploadedFile $archivo) => $this->esImagen($archivo->getMimeType(), $archivo->getClientOriginalName())
            ));
            $guardadas = $tarea !== null && ! $actualizacion
                ? $this->fotosGuardadas($tarea, $datos['evidencias_guardadas'] ?? [])
                : [];
            if ($excepciones !== [] && $confirmada) {
                throw ValidationException::withMessages([
                    'confirmacion_explicita' => 'La confirmación sin excepciones no admite piezas afectadas.',
                ]);
            }
            if ($excepciones === [] && ! $confirmada) {
                throw ValidationException::withMessages(['confirmacion_explicita' => self::MENSAJE_INCOMPLETA]);
            }
            if ($tarea !== null && $imagenesNuevas === [] && $guardadas === []) {
                throw ValidationException::withMessages(['evidencias' => self::MENSAJE_FOTO]);
            }
            if ($tarea === null && $archivos === []) {
                throw ValidationException::withMessages(['evidencias' => 'Adjunte la evidencia de la revisión.']);
            }

            if ($actualizacion && $archivos === []) {
                throw ValidationException::withMessages(['evidencias' => 'Adjunte una nueva captura del pedido actualizado.']);
            }
            $documentosAntes = $pedido->documentos()->pluck('id')->all();
            $estado = $excepciones === [] && ! $pedido->revisionesProducto()->whereNull('resolucion')->whereIn('estado_fisico', ['regular', 'malo', 'danado', 'sin_existencia'])->exists() ? 'sin_excepciones' : 'excepciones_pendientes';
            $comentario = $tarea === null ? 'Captura del pedido' : ($estado === 'sin_excepciones' ? 'Evidencia del pedido completo' : 'Evidencia de la excepción');
            foreach ($archivos as $orden => $archivo) {
                $ruta = $archivo->store('pedidos_bma/'.$pedido->id.'/disponibilidad', 'public');
                $pedido->documentos()->create([
                    'tipo' => PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
                    'ruta_archivo' => $ruta,
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'mime_type' => $archivo->getMimeType(),
                    'tamano_bytes' => $archivo->getSize(),
                    'orden' => $orden + 1,
                    'comentario' => $comentario,
                    'relacion_tipo' => PedidoBmaDocumento::RELACION_REVISION_GENERAL,
                    'relacion_id' => $tarea?->id,
                    'activo' => true,
                ]);
            }
            if ($tarea !== null && $imagenesNuevas !== []) {
                $this->productos->guardarGenerales($tarea, $usuario, $imagenesNuevas);
            }
            foreach ($guardadas as $orden => $documento) {
                $yaExiste = $pedido->documentos()
                    ->where('ruta_archivo', $documento->ruta_interna)
                    ->where('relacion_id', $tarea->id)
                    ->where('activo', true)
                    ->exists();
                if ($yaExiste) {
                    continue;
                }
                $pedido->documentos()->create([
                    'tipo' => PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
                    'ruta_archivo' => $documento->ruta_interna,
                    'nombre_original' => $documento->nombre_original,
                    'mime_type' => $documento->mime_type,
                    'tamano_bytes' => $documento->tamano_bytes,
                    'orden' => count($archivos) + $orden + 1,
                    'comentario' => $comentario,
                    'relacion_tipo' => PedidoBmaDocumento::RELACION_REVISION_GENERAL,
                    'relacion_id' => $tarea->id,
                    'activo' => true,
                ]);
            }

            foreach ($excepciones as $orden => $excepcion) {
                $revision = $pedido->revisionesProducto()->create([
                    'orden' => (int) $pedido->revisionesProducto()->max('orden') + 1,
                    'descripcion_producto' => $excepcion['descripcion'],
                    'sku' => $excepcion['sku'],
                    'cantidad_solicitada' => $excepcion['cantidad'],
                    'cantidad_encontrada' => $excepcion['condicion'] === 'faltante' ? 0 : $excepcion['cantidad'],
                    'estado_fisico' => $excepcion['condicion'] === 'faltante'
                        ? PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA
                        : PedidoBmaRevisionProducto::ESTADO_REGULAR,
                    'comentario' => $excepcion['detalle'],
                    'unica_pieza' => $excepcion['unica_pieza'],
                    'mejor_ejemplar' => $excepcion['mejor_ejemplar'],
                    'resolucion' => null,
                ]);
                foreach ($excepcion['evidencias'] as $indice => $archivo) {
                    $ruta = $archivo->store('pedidos_bma/'.$pedido->id.'/disponibilidad', 'public');
                    $pedido->documentos()->create([
                        'tipo' => PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
                        'ruta_archivo' => $ruta,
                        'nombre_original' => $archivo->getClientOriginalName(),
                        'mime_type' => $archivo->getMimeType(),
                        'tamano_bytes' => $archivo->getSize(),
                        'orden' => $indice + 1,
                        'comentario' => 'Foto del defecto: '.$excepcion['descripcion'],
                        'relacion_tipo' => PedidoBmaDocumento::RELACION_REVISION_PRODUCTO,
                        'relacion_id' => $revision->id,
                        'activo' => true,
                    ]);
                }
            }

            if ($actualizacion) {
                foreach ($json['anexo_sin_remision'] ?? [] as $indice => $anexo) {
                    if (! empty($anexo['respondida_at'])) {
                        continue;
                    }
                    $json['anexo_sin_remision'][$indice] = array_merge($anexo, [
                        'pendiente_identificacion' => false, 'pendiente_revision' => false,
                        'respuesta_cambio' => $cambiosRevisados['anexo:'.$indice] ?? ($excepciones === [] ? 'correcto' : 'incidencia'),
                        'estado' => 'revisado_conjunto', 'respondida_at' => now()->toIso8601String(),
                        'respondida_por_id' => $usuario->id, 'respondida_por' => $usuario->name,
                        'resultado' => 'Revisado en el conjunto actualizado. Consulte los detalles y la nueva captura.',
                    ]);
                }
                foreach ($json['retiro_piezas'] ?? [] as $indice => $retiro) {
                    if (($retiro['estado'] ?? 'pendiente') !== 'pendiente') {
                        continue;
                    }
                    $json['retiro_piezas'][$indice] = array_merge($retiro, ['estado' => 'aplicada',
                        'atendida_at' => now()->toIso8601String(), 'atendida_por_id' => $usuario->id,
                        'resultado' => 'Retiro documental revisado; sin liberación de inventario.']);
                }
            }
            $historialRespuestas = $guardada['historial_respuestas'] ?? [];
            if ($guardada) {
                $anterior = $guardada;
                unset($anterior['historial_respuestas']);
                $historialRespuestas[] = $anterior;
            }
            $evidenciaActual = $pedido->documentos()->whereNotIn('id', $documentosAntes)
                ->where('relacion_tipo', PedidoBmaDocumento::RELACION_REVISION_GENERAL)->pluck('id')->all();
            $json['revision_disponibilidad'] = [
                'respuesta_id' => (string) Str::uuid(),
                'actualizacion_pendiente' => false,
                'aceptacion_vigente' => true,
                'evidencia_actual_ids' => $evidenciaActual,
                'historial_respuestas' => $historialRespuestas,
                'cambios_revisados' => $cambiosRevisados,
                'estado' => $estado,
                'confirmada' => $estado === 'sin_excepciones',
                'respondida_at' => now()->toIso8601String(),
                'respondida_por_id' => $usuario->id,
            ];
            $cambios = [
                'solicitud_preparacion_json' => $json,
                'tiene_observaciones_fisicas' => $estado === 'excepciones_pendientes',
            ];
            if ($estado === 'sin_excepciones' && $area === DestinoRevisionRegistrarPedidos::CONTROL_PEDIDOS) {
                $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO);
                if (! $estatusNuevo) {
                    throw new \RuntimeException('No se encontró el estatus de consulta respondida.');
                }
                MaquinaEstadosPedidoBma::assertTransicion(
                    $pedido->estatus?->fase_ciclo,
                    CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO
                );
                $cambios['catalogo_estatus_pedido_id'] = $estatusNuevo->id;
                $cambios['estatus_envio'] = PedidoBma::ESTATUS_ENVIO_PESAJE_LISTO;
                $cambios['pesaje_respondido_at'] = now();
                $cambios['pesaje_respondido_por_id'] = $usuario->id;
            }
            $estatusAnterior = $pedido->estatus?->id;
            $pedido->update($cambios);
            $pedido->refresh();

            if ($tarea !== null) {
                $tarea->update([
                    'observaciones_respuesta' => $this->decision($estado, $area),
                ]);
            }

            $estatusNuevoId = (int) ($pedido->catalogo_estatus_pedido_id ?: $estatusAnterior);
            if ($estatusAnterior && $estatusNuevoId) {
                $this->historial->ejecutar(
                    $pedido->id,
                    $usuario->id,
                    $estatusAnterior,
                    $estatusNuevoId,
                    $this->decision($estado, $area),
                    AccionesHistorialPedidoBma::RESPUESTA_DISPONIBILIDAD
                );
            }
            $this->notificar->ejecutar(
                $pedido->fresh(['cliente', 'vendedor']),
                'pedido_disponibilidad_respondida',
                $this->decision($estado, $area),
                ['control_pedidos.crear'],
                $usuario->id,
                true
            );

            return $this->resultado($estado, $area, false);
        });
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<PedidoBmaTareaDocumento>
     */
    private function fotosGuardadas(PedidoBmaTareaPreparacion $tarea, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $consulta = $tarea->documentos()
            ->where('tipo_evidencia', PedidoBmaTareaDocumento::TIPO_EVIDENCIA_GENERAL)
            ->where('mime_type', 'like', 'image/%');
        if ($ids !== []) {
            $encontradas = $consulta->whereIn('id', $ids)->get();
            if ($encontradas->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'evidencias_guardadas' => 'La evidencia guardada no es una foto del conjunto de esta revisión.',
                ]);
            }

            return $encontradas->all();
        }

        return $consulta->get()->all();
    }

    private function esImagen(?string $mime, ?string $nombre): bool
    {
        $mime = strtolower((string) $mime);
        $nombre = strtolower((string) $nombre);
        if (str_contains($mime, 'pdf') || str_ends_with($nombre, '.pdf')) {
            return false;
        }

        return str_starts_with($mime, 'image/') || preg_match('/\.(jpe?g|png|webp)$/', $nombre) === 1;
    }

    /**
     * @param  list<array<string, mixed>>  $entrada
     * @return list<array{descripcion: string, cantidad: int, condicion: string, detalle: string, sku: ?string, evidencias: list<UploadedFile>, unica_pieza: bool, mejor_ejemplar: bool}>
     */
    private function excepciones(array $entrada, bool $cedis = false): array
    {
        $salida = [];
        foreach ($entrada as $indice => $fila) {
            if (! is_array($fila)) {
                continue;
            }
            $descripcion = trim((string) ($fila['descripcion'] ?? ''));
            $detalle = trim((string) ($fila['detalle'] ?? ''));
            $condicion = (string) ($fila['condicion'] ?? '');
            $cantidad = (int) ($fila['cantidad'] ?? ($cedis ? 1 : 0));
            $unica = (bool) ($fila['unica_pieza'] ?? false);
            $mejor = (bool) ($fila['mejor_ejemplar'] ?? false);
            if ($descripcion === '' || $cantidad < 1 || ! in_array($condicion, ['detalle', 'faltante'], true)) {
                throw ValidationException::withMessages([
                    'excepciones' => 'Cada excepción necesita un producto identificado, una cantidad válida y su estado.',
                ]);
            }
            if ($cedis && $condicion === 'faltante' && $detalle === '') {
                $detalle = 'Sin existencias';
            }
            if ($detalle === '' && (! $cedis || (! $unica && ! $mejor))) {
                throw ValidationException::withMessages(["excepciones.{$indice}.detalle" => 'Describa el defecto de la unidad reportada.']);
            }
            $fotos = array_values(array_filter($fila['evidencias'] ?? [], fn ($archivo) => $archivo instanceof UploadedFile));
            if ($condicion === 'faltante' && $fotos !== []) {
                throw ValidationException::withMessages(["excepciones.{$indice}.evidencias" => 'Una pieza sin existencias no admite fotos del defecto.']);
            }
            if (count($fotos) > 5) {
                throw ValidationException::withMessages(["excepciones.{$indice}.evidencias" => 'Adjunte hasta 5 fotos por producto.']);
            }
            if ($condicion === 'faltante' && ($unica || $mejor)) {
                throw ValidationException::withMessages(["excepciones.{$indice}.condicion" => 'Sin existencias no admite atributos de una pieza disponible.']);
            }
            $sku = trim((string) ($fila['sku'] ?? ''));
            $salida[] = [
                'descripcion' => $descripcion,
                'cantidad' => $cantidad,
                'condicion' => $condicion,
                'detalle' => $detalle,
                'sku' => $sku !== '' ? $sku : null,
                'evidencias' => $fotos,
                'unica_pieza' => $unica,
                'mejor_ejemplar' => $mejor,
            ];
        }

        return $salida;
    }

    /**
     * @return array{estado: string, decision: string, idempotente: bool}
     */
    private function resultado(string $estado, string $area, bool $idempotente): array
    {
        return [
            'estado' => $estado,
            'decision' => $this->decision($estado, $area),
            'idempotente' => $idempotente,
        ];
    }

    private function decision(string $estado, string $area): string
    {
        if ($estado === 'excepciones_pendientes') {
            return self::DECISION_EXCEPCIONES;
        }

        return $area === DestinoRevisionRegistrarPedidos::PREPARACION_TIENDA
            ? self::DECISION_TIENDA
            : self::DECISION_CEDIS;
    }
}
