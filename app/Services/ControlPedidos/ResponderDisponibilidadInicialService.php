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
use App\Support\ControlPedidos\ConsultaMunicipal;
use App\Support\ControlPedidos\RegistrarPedidos\DestinoRevisionRegistrarPedidos;
use App\Support\ControlPedidos\UnidadesRevisionConsulta;
use App\Support\ControlPedidos\RespuestaPreparacionTienda;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        private PersistenciaAdjuntosConsulta $persistencia,
    ) {}

    /**
     * @param  array{confirmacion_explicita?: bool, excepciones?: list<array<string, mixed>>}  $datos
     * @param  list<UploadedFile>  $evidencias
     * @return array{estado: string, decision: string, idempotente: bool}
     */
    public function ejecutar(PedidoBma $pedido, User $usuario, array $datos, array $evidencias, ?PedidoBmaTareaPreparacion $tarea = null): array
    {
        $nuevos = [];
        try {
            return DB::transaction(function () use ($pedido, $usuario, $datos, $evidencias, $tarea, &$nuevos) {
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

            $esMunicipal = ConsultaMunicipal::es($pedido);
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
            } elseif ($esMunicipal) {
                abort_unless(\App\Support\ControlPedidos\AlcanceConsultaMunicipal::puedeResponder($usuario, $pedido), 403);
                if (ConsultaMunicipal::estado($pedido) === ConsultaMunicipal::CERRADA) {
                    throw ValidationException::withMessages(['consulta' => 'La consulta ya fue cerrada por Ventas.']);
                }
            } elseif ($area !== DestinoRevisionRegistrarPedidos::CONTROL_PEDIDOS
                || (! in_array($pedido->estatus_envio, [PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE, PedidoBma::ESTATUS_ENVIO_PESAJE_LISTO], true))) {
                throw ValidationException::withMessages(['area_revision' => 'Este pedido no está pendiente de consulta de disponibilidad.']);
            }

            if ($esMunicipal) {
                foreach (array_merge($evidencias, ...array_map(fn ($fila) => is_array($fila) ? ($fila['evidencias'] ?? []) : [], $datos['excepciones'] ?? [])) as $archivo) {
                    if (! $archivo instanceof UploadedFile || ! $archivo->isValid() || $archivo->getSize() > 10 * 1024 * 1024
                        || ! in_array($archivo->getMimeType(), ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
                        throw ValidationException::withMessages(['evidencias' => 'Adjunte imágenes o PDF válidos de hasta 10 MB.']);
                    }
                }
            }

            $guardada = is_array($json['revision_disponibilidad'] ?? null) ? $json['revision_disponibilidad'] : null;
            if (! empty($datos['persistir_unidad'])) {
                return $this->guardarUnidadBloqueada($pedido, $usuario, $datos, $json, $nuevos, $area);
            }
            if (! $actualizacion && is_array($guardada) && isset($guardada['estado'])) {
                return $this->resultado((string) $guardada['estado'], $area, true);
            }

            // Una actualización revisa el conjunto: solo se recapturan sus nuevas excepciones.
            $excepciones = $this->excepciones($datos['excepciones'] ?? [], true);
            // Las unidades de Tienda ya guardadas se publican una sola vez. El envío
            // directo con piezas sigue usando el mismo contrato y las mismas validaciones.
            if ($actualizacion && $area === DestinoRevisionRegistrarPedidos::PREPARACION_TIENDA) {
                $porUnidad = collect($datos['excepciones'] ?? [])->filter(fn ($fila) => ! empty($fila['origen_unidad_id']))->groupBy('origen_unidad_id');
                foreach ($porUnidad as $unidadId => $filas) {
                    $cambio = collect($datos['cambios_revisados'] ?? [])->firstWhere('unidad_id', $unidadId);
                    if (! $cambio || ($cambio['resultado'] ?? '') !== 'incidencia') {
                        throw ValidationException::withMessages(['cambios_revisados' => 'La pieza necesita el resultado de su unidad.']);
                    }
                    $this->guardarUnidadBloqueada($pedido, $usuario, array_merge($datos, ['excepciones' => $filas->all(), 'cambios_revisados' => [$cambio]]), $json, $nuevos, $area);
                    $json = $pedido->solicitud_preparacion_json;
                }
                $excepciones = array_values(array_filter($excepciones, fn ($fila) => empty($fila['origen_unidad_id'])));
            }
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
            $json = UnidadesRevisionConsulta::materializar($json);
            $cambiosRevisados = [];
            if ($actualizacion) {
                foreach ($datos['cambios_revisados'] ?? [] as $cambio) {
                    $unidadId = (string) ($cambio['unidad_id'] ?? '');
                    $hallada = $unidadId !== '' ? UnidadesRevisionConsulta::localizar($json, $unidadId) : null;
                    $hallada ??= $this->unidadPorIndice($json, $cambio);
                    if ($hallada === null) {
                        throw ValidationException::withMessages(['cambios_revisados' => 'La unidad no pertenece a esta consulta.']);
                    }
                    $unidadId = (string) ($hallada['unidad']['unidad_id'] ?? $unidadId);
                    $this->asegurarDocumentoDeUnidad($pedido, $hallada, $cambio);
                    if (($cambio['resultado'] ?? '') === 'incidencia') {
                        $deUnidad = array_values(array_filter($excepciones, fn ($ex) => ($ex['origen_unidad_id'] ?? '') === $unidadId));
                        if ($deUnidad === [] && ($hallada['unidad']['resultado'] ?? null) !== 'incidencia' && $excepciones === []) {
                            throw ValidationException::withMessages(['cambios_revisados' => 'Registre las piezas con detalle antes de enviar.']);
                        }
                    }
                    $cambiosRevisados[$unidadId] = $cambio['resultado'];
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
            $capturaVigente = $actualizacion && $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION)->where('relacion_tipo', PedidoBmaDocumento::RELACION_REVISION_GENERAL)->where('activo', true)->exists();
            if ($tarea !== null && $imagenesNuevas === [] && $guardadas === [] && ! $capturaVigente) {
                throw ValidationException::withMessages(['evidencias' => self::MENSAJE_FOTO]);
            }
            if ($tarea === null && $archivos === []) {
                throw ValidationException::withMessages(['evidencias' => 'Adjunte la evidencia de la revisión.']);
            }

            if ($actualizacion && $archivos === [] && ActualizacionConsulta::requiereRespuesta($json)
                && ! $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION)->where('relacion_tipo', PedidoBmaDocumento::RELACION_REVISION_GENERAL)->where('activo', true)->exists()) {
                throw ValidationException::withMessages(['evidencias' => 'Adjunte una nueva captura del pedido actualizado.']);
            }
            $documentosAntes = $pedido->documentos()->pluck('id')->all();
            $estado = $excepciones === [] && ! $pedido->revisionesProducto()->whereNotIn('id', RespuestaPreparacionTienda::revisionesNoEnviadas($json, false))->whereNull('resolucion')->whereIn('estado_fisico', ['regular', 'malo', 'danado', 'sin_existencia'])->exists() ? 'sin_excepciones' : 'excepciones_pendientes';
            $comentario = $tarea === null ? 'Captura del pedido' : ($estado === 'sin_excepciones' ? 'Evidencia del pedido completo' : 'Evidencia de la excepción');
            if ($archivos !== []) {
                $docsConjunto = $this->persistencia->guardar($pedido, $archivos, [
                    'carpeta' => 'disponibilidad',
                    'tipo' => PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
                    'comentario' => $comentario,
                    'relacion_tipo' => PedidoBmaDocumento::RELACION_REVISION_GENERAL,
                    'relacion_id' => $tarea?->id,
                    'orden' => 0,
                ]);
                $nuevos = array_merge($nuevos, array_column($docsConjunto, 'ruta'));
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

            $origenes = is_array($json['revision_origenes'] ?? null) ? $json['revision_origenes'] : [];
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
                if ($excepcion['evidencias'] !== []) {
                    $docsExcepcion = $this->persistencia->guardar($pedido, $excepcion['evidencias'], [
                        'carpeta' => 'disponibilidad',
                        'tipo' => PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
                        'comentario' => 'Foto del defecto: '.$excepcion['descripcion'],
                        'relacion_tipo' => PedidoBmaDocumento::RELACION_REVISION_PRODUCTO,
                        'relacion_id' => $revision->id,
                        'orden' => 0,
                    ]);
                    $nuevos = array_merge($nuevos, array_column($docsExcepcion, 'ruta'));
                }
                if (! empty($excepcion['origen_unidad_id'])) {
                    $origenes[(string) $revision->id] = $excepcion['origen_unidad_id'];
                    $halladaOrigen = UnidadesRevisionConsulta::localizar($json, $excepcion['origen_unidad_id']);
                    if ($halladaOrigen !== null) {
                        $ids = $halladaOrigen['unidad']['revision_ids'] ?? [];
                        $ids[] = $revision->id;
                        $json[$halladaOrigen['clave']][$halladaOrigen['indice']] = UnidadesRevisionConsulta::aplicar(
                            $json[$halladaOrigen['clave']][$halladaOrigen['indice']],
                            $excepcion['origen_unidad_id'],
                            ['resultado' => 'incidencia', 'revision_ids' => array_values(array_unique($ids))],
                            $usuario->id,
                            $usuario->name
                        );
                    }
                }
            }
            $json['revision_origenes'] = $origenes;

            if ($actualizacion) {
                foreach ($datos['cambios_revisados'] ?? [] as $cambio) {
                    $unidadId = (string) ($cambio['unidad_id'] ?? '');
                    $hallada = ($unidadId !== '' ? UnidadesRevisionConsulta::localizar($json, $unidadId) : null) ?? $this->unidadPorIndice($json, $cambio);
                    if ($hallada === null) {
                        continue;
                    }
                    $unidadId = (string) ($hallada['unidad']['unidad_id'] ?? $unidadId);
                    $json[$hallada['clave']][$hallada['indice']] = UnidadesRevisionConsulta::aplicar(
                        $json[$hallada['clave']][$hallada['indice']],
                        $unidadId,
                        ['resultado' => $cambio['resultado']],
                        $usuario->id,
                        $usuario->name
                    );
                }
                foreach (['anexo_sin_remision' => 'anexo'] as $clave => $tipo) {
                    foreach ($json[$clave] ?? [] as $indice => $linea) {
                        if (! is_array($linea) || UnidadesRevisionConsulta::lineaYaAtendida($tipo, $linea)) {
                            continue;
                        }
                        $unidades = is_array($linea['unidades'] ?? null) ? $linea['unidades'] : UnidadesRevisionConsulta::derivar($tipo, $linea);
                        foreach ($unidades as $unidad) {
                            if (empty($unidad['resultado'])) {
                                throw ValidationException::withMessages(['cambios_revisados' => 'Revise cada captura y pieza solicitada antes de enviar.']);
                            }
                        }
                        if ($unidades === [] || ! collect($unidades)->every(fn ($unidad) => ! empty($unidad['resultado']))) {
                            continue;
                        }
                        $incidencias = collect($unidades)->contains(fn ($unidad) => ($unidad['resultado'] ?? '') === 'incidencia');
                        $json[$clave][$indice]['respuesta_cambio'] = $incidencias ? 'incidencia' : 'correcto';
                        $json[$clave][$indice]['respondida_at'] = now()->toIso8601String();
                        $json[$clave][$indice]['respondida_por_id'] = $usuario->id;
                        $json[$clave][$indice]['respondida_por'] = $usuario->name;
                        if ($clave === 'anexo_sin_remision') {
                            $json[$clave][$indice]['pendiente_identificacion'] = false;
                            $json[$clave][$indice]['pendiente_revision'] = false;
                            $json[$clave][$indice]['estado'] = 'revisado_conjunto';
                        }
                    }
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
            if ($evidenciaActual === [] && $actualizacion) {
                $evidenciaActual = $guardada['evidencia_actual_ids'] ?? [];
            }
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
            if ($esMunicipal) {
                $json = ConsultaMunicipal::aplicarEstado($json, $estado);
            }
            $cambios = [
                'solicitud_preparacion_json' => $json,
                'tiene_observaciones_fisicas' => $estado === 'excepciones_pendientes',
                'consulta_actualizacion_pendiente' => false,
            ];
            if ($esMunicipal && $estado === 'sin_excepciones' && $pedido->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE) {
                $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO);
                if ($estatusNuevo) {
                    MaquinaEstadosPedidoBma::assertTransicion(
                        $pedido->estatus?->fase_ciclo,
                        CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO
                    );
                    $cambios['catalogo_estatus_pedido_id'] = $estatusNuevo->id;
                }
            } elseif (! $esMunicipal && $estado === 'sin_excepciones' && $area === DestinoRevisionRegistrarPedidos::CONTROL_PEDIDOS) {
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
        } catch (\Throwable $e) {
            $this->persistencia->limpiar($nuevos);
            throw $e;
        }
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
     * @param  array{tipo: string, indice: int, linea: array<string, mixed>, unidad: array<string, mixed>, clave: string}  $hallada
     * @param  array<string, mixed>  $cambio
     */
    private function asegurarDocumentoDeUnidad(PedidoBma $pedido, array $hallada, array $cambio): void
    {
        $documentoId = (int) ($cambio['documento_id'] ?? $hallada['unidad']['documento_id'] ?? 0);
        if ($documentoId < 1) {
            return;
        }
        $documentoUnidad = (int) ($hallada['unidad']['documento_id'] ?? 0);
        $documento = $pedido->documentos()->whereKey($documentoId)->first();
        if (! $documento || ! UnidadesRevisionConsulta::documentoDeLaLinea($hallada['linea'], $documentoId)
            || ($documentoUnidad > 0 && $documentoId !== $documentoUnidad)) {
            throw ValidationException::withMessages(['documento_id' => 'El documento no pertenece a esta unidad.']);
        }
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  array<string, mixed>  $cambio
     * @return array{tipo: string, indice: int, linea: array<string, mixed>, unidad: array<string, mixed>, clave: string}|null
     */
    private function unidadPorIndice(array $json, array $cambio): ?array
    {
        $tipo = ($cambio['tipo'] ?? 'anexo') === 'retiro' ? 'retiro' : 'anexo';
        $clave = $tipo === 'retiro' ? 'retiro_piezas' : 'anexo_sin_remision';
        if (! array_key_exists('indice', $cambio) || ! isset($json[$clave][(int) $cambio['indice']])) {
            return null;
        }
        $indice = (int) $cambio['indice'];
        $documentoId = (int) ($cambio['documento_id'] ?? 0);
        foreach ($json[$clave][$indice]['unidades'] ?? [] as $unidad) {
            if ($documentoId > 0 && (int) ($unidad['documento_id'] ?? 0) === $documentoId) {
                return UnidadesRevisionConsulta::localizar($json, (string) $unidad['unidad_id']);
            }
        }
        $unidades = is_array($json[$clave][$indice]['unidades'] ?? null) ? $json[$clave][$indice]['unidades'] : [];
        if ($documentoId === 0 && count($unidades) === 1 && isset($unidades[0]['unidad_id'])) {
            return UnidadesRevisionConsulta::localizar($json, (string) $unidades[0]['unidad_id']);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $json
     * @param  list<string>  $nuevos
     * @return array{estado: string, decision: string, idempotente: bool}
     */
    private function guardarUnidadBloqueada(PedidoBma $pedido, User $usuario, array $datos, array $json, array &$nuevos, string $area): array
    {
        if (! ActualizacionConsulta::pendiente($json)) {
            throw ValidationException::withMessages(['consulta' => 'No hay una actualización pendiente.']);
        }
        $cambio = $datos['cambios_revisados'][0] ?? null;
        $unidadId = is_array($cambio) ? (string) ($cambio['unidad_id'] ?? '') : '';
        if ($unidadId === '') {
            throw ValidationException::withMessages(['unidad_id' => 'Indique la unidad a guardar.']);
        }
        $json = UnidadesRevisionConsulta::materializar($json);
        // Una línea aún sin solicitud_id/unidades (p. ej. la alternativa pedida por Ventas) no tiene id canónico
        // hasta materializarse: se resuelve por su posición y documento, nunca por otra unidad.
        $hallada = UnidadesRevisionConsulta::localizar($json, $unidadId) ?? $this->unidadPorIndice($json, $cambio);
        if ($hallada === null) {
            throw ValidationException::withMessages(['unidad_id' => 'La unidad no pertenece a esta consulta.']);
        }
        $unidadId = (string) ($hallada['unidad']['unidad_id'] ?? $unidadId);
        $tienda = $area === DestinoRevisionRegistrarPedidos::PREPARACION_TIENDA;
        if ($tienda && UnidadesRevisionConsulta::lineaYaAtendida($hallada['tipo'], $hallada['linea'])) {
            throw ValidationException::withMessages(['unidad_id' => 'Esta unidad ya pertenece a una respuesta enviada.']);
        }
        $this->asegurarDocumentoDeUnidad($pedido, $hallada, is_array($cambio) ? $cambio : []);
        $resultado = (string) ($cambio['resultado'] ?? '');
        if (! in_array($resultado, ['correcto', 'incidencia'], true)) {
            throw ValidationException::withMessages(['resultado' => 'Indique el resultado de la unidad.']);
        }
        $excepciones = $this->excepciones($datos['excepciones'] ?? [], true);
        if ($tienda && collect($excepciones)->contains(fn ($fila) => $fila['condicion'] === 'detalle' && $fila['evidencias'] === [])) {
            throw ValidationException::withMessages(['excepciones' => 'Adjunte una foto del producto con detalle.']);
        }
        foreach ($excepciones as $excepcion) {
            if (($excepcion['origen_unidad_id'] ?? '') !== $unidadId) {
                throw ValidationException::withMessages(['excepciones' => 'Las piezas deben corresponder a la unidad que se guarda.']);
            }
        }
        if ($resultado === 'incidencia' && $excepciones === [] && $hallada['tipo'] !== 'retiro') {
            throw ValidationException::withMessages(['cambios_revisados' => 'Registre las piezas con detalle antes de guardar.']);
        }
        if (($hallada['unidad']['resultado'] ?? null) === $resultado
            && ($tienda ? $this->avanceIgual($pedido, $hallada['unidad'], $excepciones) : $excepciones === [])) {
            $pedido->update(['solicitud_preparacion_json' => $json]);

            return $this->resultado((string) ($json['revision_disponibilidad']['estado'] ?? 'excepciones_pendientes'), $area, true);
        }
        $revisionIds = $tienda ? [] : ($hallada['unidad']['revision_ids'] ?? []);
        $origenes = is_array($json['revision_origenes'] ?? null) ? $json['revision_origenes'] : [];
        if ($tienda && ! empty($hallada['unidad']['resultado'])) {
            // Solo se sustituyen avances de esta unidad abierta. Las respuestas enviadas
            // no entran aquí; conservar el contenido y documentos anteriores en su historial.
            $anteriores = $pedido->revisionesProducto()->whereIn('id', $hallada['unidad']['revision_ids'] ?? [])->get();
            foreach ($json[$hallada['clave']][$hallada['indice']]['unidades'] as &$unidad) {
                if ($unidad['unidad_id'] !== $unidadId) continue;
                $unidad['historial'][] = [
                    'resultado' => $unidad['resultado'], 'revision_ids' => $unidad['revision_ids'] ?? [],
                    'por_id' => $unidad['respondida_por_id'] ?? null, 'por' => $unidad['respondida_por'] ?? null,
                    'at' => $unidad['respondida_at'] ?? null,
                    'revisiones' => $anteriores->map(fn ($r) => array_merge($r->toArray(), [
                        'documento_ids' => $pedido->documentos()->where('relacion_tipo', PedidoBmaDocumento::RELACION_REVISION_PRODUCTO)->where('relacion_id', $r->id)->pluck('id')->all(),
                    ]))->all(),
                ];
            }
            unset($unidad);
            $pedido->documentos()->where('relacion_tipo', PedidoBmaDocumento::RELACION_REVISION_PRODUCTO)->whereIn('relacion_id', $anteriores->pluck('id'))->update(['activo' => false]);
            $pedido->revisionesProducto()->whereIn('id', $anteriores->pluck('id'))->delete();
        }
        foreach ($excepciones as $excepcion) {
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
            $revisionIds[] = $revision->id;
            $origenes[(string) $revision->id] = $unidadId;
            if ($excepcion['evidencias'] !== []) {
                $docsExcepcion = $this->persistencia->guardar($pedido, $excepcion['evidencias'], [
                    'carpeta' => 'disponibilidad',
                    'tipo' => PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
                    'comentario' => 'Foto del defecto: '.$excepcion['descripcion'],
                    'relacion_tipo' => PedidoBmaDocumento::RELACION_REVISION_PRODUCTO,
                    'relacion_id' => $revision->id,
                    'orden' => 0,
                ]);
                $nuevos = array_merge($nuevos, array_column($docsExcepcion, 'ruta'));
            }
        }
        $json[$hallada['clave']][$hallada['indice']] = UnidadesRevisionConsulta::aplicar(
            $json[$hallada['clave']][$hallada['indice']],
            $unidadId,
            ['resultado' => $resultado, 'revision_ids' => array_values(array_unique($revisionIds))],
            $usuario->id,
            $usuario->name
        );
        $json['revision_origenes'] = $origenes;
        $pedido->update(['solicitud_preparacion_json' => $json]);

        return $this->resultado((string) ($json['revision_disponibilidad']['estado'] ?? 'excepciones_pendientes'), $area, false);
    }

    private function avanceIgual(PedidoBma $pedido, array $unidad, array $excepciones): bool
    {
        $ids = ($unidad['resultado'] ?? '') === 'incidencia' ? ($unidad['revision_ids'] ?? []) : [];
        if (count($ids) !== count($excepciones)) return false;
        foreach ($excepciones as $indice => $fila) {
            $revision = $pedido->revisionesProducto()->find($ids[$indice]);
            if (! $revision || $revision->descripcion_producto !== $fila['descripcion'] || $revision->sku !== $fila['sku']
                || (int) $revision->cantidad_solicitada !== $fila['cantidad'] || $revision->comentario !== $fila['detalle']
                || $revision->estado_fisico !== ($fila['condicion'] === 'faltante' ? 'sin_existencia' : 'regular')
                || (bool) $revision->unica_pieza !== $fila['unica_pieza'] || (bool) $revision->mejor_ejemplar !== $fila['mejor_ejemplar']) return false;
            $documentos = $pedido->documentos()->where('activo', true)->where('relacion_tipo', PedidoBmaDocumento::RELACION_REVISION_PRODUCTO)->where('relacion_id', $revision->id)->orderBy('orden')->get();
            if ($documentos->count() !== count($fila['evidencias'])) return false;
            foreach ($fila['evidencias'] as $i => $archivo) {
                $ruta = $documentos[$i]->ruta_archivo;
                if (! Storage::disk('public')->exists($ruta)
                    || hash('sha256', Storage::disk('public')->get($ruta)) !== hash_file('sha256', $archivo->getRealPath())) return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $entrada
     * @return list<array{descripcion: string, cantidad: int, condicion: string, detalle: string, sku: ?string, evidencias: list<UploadedFile>, unica_pieza: bool, mejor_ejemplar: bool, origen_unidad_id: ?string}>
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
            $origenUnidad = trim((string) ($fila['origen_unidad_id'] ?? ''));
            $salida[] = [
                'descripcion' => $descripcion,
                'cantidad' => $cantidad,
                'condicion' => $condicion,
                'detalle' => $detalle,
                'sku' => $sku !== '' ? $sku : null,
                'evidencias' => $fotos,
                'unica_pieza' => $unica,
                'mejor_ejemplar' => $mejor,
                'origen_unidad_id' => $origenUnidad !== '' ? $origenUnidad : null,
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
