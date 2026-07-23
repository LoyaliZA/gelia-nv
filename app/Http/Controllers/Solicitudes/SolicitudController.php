<?php

namespace App\Http\Controllers\Solicitudes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Solicitudes\StoreSolicitudRequest;
use App\Services\Solicitudes\CrearSolicitudService;
use App\Services\Solicitudes\CrearConsultaSolicitudService;
use App\Services\Solicitudes\ListarSolicitudesService;
use App\Services\Solicitudes\EliminarSolicitudService;
use App\Services\Solicitudes\ResponderConsultaSolicitudService;
use App\Services\Solicitudes\CancelarSolicitudService;
use App\Services\Solicitudes\SolicitarCancelacionSolicitudService;
use App\Services\Solicitudes\ExportarReporteSolicitudesService;
use App\Services\Clientes\RegistrarHistorialMontoClienteService;
use App\Services\Clientes\ReactivarClienteInactivoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use App\Models\SolicitudTag;
use App\Models\ConsultaSolicitud;
use App\Models\AuditoriaSolicitud;
use App\Models\Cliente;
use App\Models\User;
use App\Notifications\AlertaSolicitud;
use App\Models\CatalogoProceso;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoTipoCliente;
use App\Models\CatalogoBanco;
use App\Models\CatalogoEstadoSolicitud;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class SolicitudController extends Controller
{
    private function redirectToSolicitudesList(Request $request, string $message): RedirectResponse
    {
        $referer = $request->headers->get('referer');
        $listPath = parse_url(route('solicitudes.index', absolute: false), PHP_URL_PATH);

        if ($referer && $listPath) {
            $refererPath = parse_url($referer, PHP_URL_PATH);
            if ($refererPath === $listPath) {
                return redirect()->to($referer)->with('success', $message);
            }
        }

        return redirect()->route('solicitudes.index')->with('success', $message);
    }

    private function autorizarEmitirConsulta(): void
    {
        $usuario = Auth::user();
        if (!$usuario->hasAnyPermission(['solicitudes.emitir_consulta', 'solicitudes.consultar'])) {
            abort(403, 'No tienes permiso para emitir consultas.');
        }
    }

    private function autorizarResponderConsulta(): void
    {
        $usuario = Auth::user();
        if (!$usuario->hasAnyPermission(['solicitudes.responder_consulta', 'solicitudes.reportar'])) {
            abort(403, 'No tienes permiso para responder consultas.');
        }
    }

    public function index(Request $request, ListarSolicitudesService $listarService): Response
    {
        $solicitudes = $listarService->ejecutar(Auth::user(), $request->all());
        $procesos = CatalogoProceso::where('activo', true)
            ->where('categoria_flujo', '!=', CatalogoProceso::CATEGORIA_OPERATIVO)
            ->get();

        // Obtenemos los usuarios que pueden ser vendedores/colaboradores para el filtro
        $vendedores = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['colaborador', 'Administrador', 'Super Admin']); // Ajusta según tus roles reales
        })->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Solicitudes/Index', [
            'solicitudes' => $solicitudes,
            'filtros' => $request->all(), // Pasamos los filtros actuales para mantener el estado en React
            'procesos' => $procesos,
            'listas' => CatalogoListaDescuento::with('porcentajeEscalonamiento')->where('activo', true)->get(),
            'tipos_cliente' => CatalogoTipoCliente::where('activo', true)->orderBy('nombre')->get(),
            'vendedores' => $vendedores,
            'bancos' => CatalogoBanco::where('activo', true)->orderBy('nombre')->get(),
            'estados' => CatalogoEstadoSolicitud::orderBy('id')->get(['id', 'nombre']),
        ]);
    }

    public function store(StoreSolicitudRequest $request, CrearSolicitudService $crearSolicitudService): RedirectResponse
    {
        $crearSolicitudService->ejecutar($request->validated(), Auth::id());

        return $this->redirectToSolicitudesList($request, 'Solicitud creada correctamente.');
    }

    public function confirmarPago(Request $request, SolicitudTag $solicitud)
    {
        $solicitud->loadMissing('proceso');
        if ($solicitud->proceso?->esOperativo()) {
            abort(403, 'Las solicitudes operativas no requieren confirmación de pago.');
        }

        $idRespondida = CatalogoEstadoSolicitud::idDe('Respondida');
        $idIncorrecta = CatalogoEstadoSolicitud::idDe('Incorrecta');

        if ((int) $solicitud->catalogo_estado_solicitud_id !== (int) $idRespondida) {
            abort(403, 'Acción Bloqueada: El pago solo puede confirmarse después de que la encargada haya emitido una resolución aprobatoria.');
        }

        if ($solicitud->vendedor_id !== Auth::id() && !Auth::user()->can('solicitudes.confirmar_pago')) {
            abort(403, 'No tienes autorización para confirmar este pago.');
        }

        $modo = $request->input('modo', 'pago');
        if (!in_array($modo, ['pago', 'pago_sin_monto', 'atencion_gelia'], true)) {
            abort(422, 'Modo de confirmación inválido.');
        }

        if (in_array($modo, ['pago_sin_monto', 'atencion_gelia'], true) && !CrearSolicitudService::esFlujoTienda($solicitud)) {
            abort(422, 'Ese modo de confirmación solo aplica a solicitudes de compra en tienda.');
        }

        if ($modo === 'pago') {
            $request->validate([
                'monto_final_pagado' => 'required|numeric|min:0',
            ]);
        }

        if (CrearSolicitudService::modoValidacionSinMonto($modo)) {
            DB::transaction(function () use ($solicitud, $modo, $idRespondida) {
                $mensajeAuditoria = $modo === 'atencion_gelia'
                    ? 'ATENCIÓN GELIA CONFIRMADA. Solicitud validada sin modificar montos.'
                    : 'PAGO CONFIRMADO SIN MONTO (compra en tienda). No se agregó cantidad para evitar duplicar remisión ya cargada.';

                $solicitud->update([
                    'pago_confirmado' => true,
                ]);

                AuditoriaSolicitud::create([
                    'solicitud_id' => $solicitud->id,
                    'usuario_id' => Auth::id(),
                    'estado_anterior_id' => $idRespondida,
                    'estado_nuevo_id' => $idRespondida,
                    'motivo_reporte' => $mensajeAuditoria,
                    'datos_snapshot' => [
                        'modo_confirmacion' => $modo,
                        'compra_en_tienda' => (bool) $solicitud->compra_en_tienda,
                        'compra_en_tienda_solo_tag' => (bool) $solicitud->compra_en_tienda_solo_tag,
                        'monto_venta_sin_cambio' => true,
                    ],
                ]);

                $destinatarios = $this->obtenerDestinatariosDepartamentales($solicitud, false);
                if ($destinatarios->isNotEmpty()) {
                    $mensajeNotificacion = $modo === 'atencion_gelia'
                        ? 'El colaborador ' . Auth::user()->name . ' confirmó atención Gelia (sin montos).'
                        : 'El colaborador ' . Auth::user()->name . ' confirmó el pago sin agregar monto.';

                    Notification::send($destinatarios, new AlertaSolicitud(
                        $solicitud,
                        'pago_confirmado',
                        $mensajeNotificacion
                    ));
                }
            });

            return $this->redirectToSolicitudesList($request, 'Operación procesada.');
        }

        DB::transaction(function () use ($solicitud, $request, $idRespondida, $idIncorrecta) {
            $montoOriginal = $solicitud->monto_cotizado;
            $montoFinal = $request->monto_final_pagado;
            $estadoNuevoId = $idRespondida;
            $mensajeAuditoria = 'PAGO CONFIRMADO POR EL COLABORADOR';
            $esAlertaFaltaPago = false;
            $esAlertaAscenso = false;
            $beneficiosProvisionalAplicados = false;
            $montoHistoricoBase = 0.0;
            $totalProyectado = 0.0;
            $listaCalificada = null;

            // 1. Extraemos al cliente con su lista actual cargada para hacer la comparación real
            if ($solicitud->cliente_id) {
                $cliente = Cliente::with('listaDescuento')->find($solicitud->cliente_id);
                
                if ($cliente) {
                    $beneficiosProvisionalAplicados = $this->beneficiosProvisionalEstanAplicados($solicitud, $cliente);
                    $montoHistoricoBase = $beneficiosProvisionalAplicados
                        ? max(0, (float) $cliente->monto_venta_actual - (float) $montoOriginal)
                        : (float) $cliente->monto_venta_actual;
                    $totalProyectado = $montoHistoricoBase + (float) $montoFinal;

                    $listaCalificada = CatalogoListaDescuento::where('activo', true)
                        ->where('nombre', 'not like', '%COLABORADOR%')
                        ->where('nombre', 'not like', '%PLATAFORMAS%')
                        ->where('monto_requerido', '<=', $totalProyectado)
                        ->orderBy('monto_requerido', 'desc')
                        ->first();

                    if ($listaCalificada) {
                        // 2. Evaluación de Downgrade (Falta de Pago)
                        if ($solicitud->catalogo_lista_descuento_id) {
                            $listaSolicitada = CatalogoListaDescuento::find($solicitud->catalogo_lista_descuento_id);
                            if ($listaSolicitada && $totalProyectado < $listaSolicitada->monto_requerido) {
                                $mensajeAuditoria = "ALERTA DE PAGO: Pago final de $" . number_format($montoFinal, 2) . " es insuficiente para la lista {$listaSolicitada->nombre}. El cliente califica para: {$listaCalificada->nombre}.";
                                $estadoNuevoId = $idIncorrecta;
                                $esAlertaFaltaPago = true;
                            }
                        }

                        // 3. Evaluación de Upgrade (Ascenso)
                        if (!$esAlertaFaltaPago) {
                            $montoRequeridoActual = $cliente->listaDescuento ? $cliente->listaDescuento->monto_requerido : 0;
                            
                            if ($listaCalificada->monto_requerido > $montoRequeridoActual) {
                                $esAlertaAscenso = true;
                                $mensajeAuditoria = "ALERTA DE ASCENSO: El pago final de $" . number_format($montoFinal, 2) . " permite al cliente ascender de categoría a la lista: {$listaCalificada->nombre}.";
                                $solicitud->catalogo_lista_descuento_id = $listaCalificada->id;
                            }
                        }
                    }
                }
            }

            // 4. Persistencia y Control Monetario
            $snapshotDiff = [];
            if ($esAlertaFaltaPago) {
                $snapshotDiff = $beneficiosProvisionalAplicados
                    ? $this->revertirBeneficiosCliente($solicitud)
                    : $this->capturarSnapshotClienteSinCambioMonetario($solicitud);

                $solicitud->update([
                    'pago_confirmado' => false,
                    'monto_cotizado' => $montoFinal,
                    'catalogo_estado_solicitud_id' => $estadoNuevoId,
                    'motivo_incorrecta' => 'pago_insuficiente',
                ]);
            } else {
                if ($solicitud->cliente_id) {
                    $clienteObj = Cliente::with(['listaDescuento', 'vendedor', 'tipo'])->find($solicitud->cliente_id);
                    if ($clienteObj) {
                        $antes = $this->capturarSnapshotCliente($clienteObj);
                        $montoObjetivo = max(0, $montoHistoricoBase + (float) $montoFinal);
                        $this->registrarHistorialMontoSolicitud(
                            $clienteObj,
                            $montoObjetivo,
                            RegistrarHistorialMontoClienteService::ORIGEN_SOLICITUD_PAGO,
                            $solicitud,
                            (float) $montoFinal,
                            'Pago confirmado',
                        );
                        $clienteObj->monto_venta_actual = $montoObjetivo;

                        if ($esAlertaAscenso && isset($listaCalificada)) {
                            $clienteObj->lista_actual_id = $listaCalificada->id;
                        }

                        app(ReactivarClienteInactivoService::class)->ejecutar(
                            $clienteObj,
                            $montoObjetivo,
                            !($esAlertaAscenso && isset($listaCalificada)),
                        );

                        $clienteObj->save();
                        $clienteObj->refresh()->load(['listaDescuento', 'vendedor', 'tipo']);
                        $snapshotDiff = [
                            'antes' => $antes,
                            'despues' => $this->capturarSnapshotCliente($clienteObj),
                        ];
                    }
                }

                $solicitud->update([
                    'pago_confirmado' => true,
                    'monto_cotizado' => $montoFinal,
                    'catalogo_estado_solicitud_id' => $estadoNuevoId,
                    'catalogo_lista_descuento_id' => $solicitud->catalogo_lista_descuento_id
                ]);
            }

            // 5. Auditoría
            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => $idRespondida,
                'estado_nuevo_id' => $estadoNuevoId,
                'motivo_reporte' => $mensajeAuditoria,
                'datos_snapshot' => !empty($snapshotDiff) ? $snapshotDiff : null,
            ]);

            // 6. Notificaciones en Tiempo Real
            $destinatarios = $this->obtenerDestinatariosDepartamentales($solicitud, false);
            if ($destinatarios->isNotEmpty()) {
                
                $tituloAlerta = 'pago_confirmado';
                $mensajeNotificacion = 'El colaborador ' . Auth::user()->name . ' ha confirmado el pago exitosamente.';

                if ($esAlertaFaltaPago) {
                    $tituloAlerta = 'alerta_pago_insuficiente';
                    $mensajeNotificacion = 'El colaborador ' . Auth::user()->name . ' intentó confirmar un pago insuficiente. Requiere ajuste de lista.';
                } elseif ($esAlertaAscenso) {
                    $tituloAlerta = 'alerta_ascenso_lista';
                    $mensajeNotificacion = 'El colaborador ' . Auth::user()->name . ' confirmó un pago que permite ascender al cliente de categoría.';
                }

                Notification::send($destinatarios, new AlertaSolicitud(
                    $solicitud,
                    $tituloAlerta,
                    $mensajeNotificacion
                ));
            }
        });

        return $this->redirectToSolicitudesList($request, 'Operación procesada.');
    }

    public function confirmarCambioLista(Request $request, SolicitudTag $solicitud)
    {
        Gate::authorize('solicitudes.confirmar_cambio_lista');

        DB::transaction(function () use ($solicitud) {
            $estadoAnteriorId = $solicitud->catalogo_estado_solicitud_id;
            $estadoNuevoId = CatalogoEstadoSolicitud::idDe('Verificada');

            $cliente = Cliente::find($solicitud->cliente_id);
            if ($cliente) {
                $totalProyectado = ($cliente->monto_venta_actual ?? 0) + $solicitud->monto_cotizado;

                $listaCalificada = CatalogoListaDescuento::where('activo', true)
                    ->where('nombre', 'not like', '%COLABORADOR%')
                    ->where('nombre', 'not like', '%PLATAFORMAS%')
                    ->where('monto_requerido', '<=', $totalProyectado)
                    ->orderBy('monto_requerido', 'desc')
                    ->first();

                if ($listaCalificada) {
                    $solicitud->catalogo_lista_descuento_id = $listaCalificada->id;
                }
            }

            $solicitud->update([
                'catalogo_estado_solicitud_id' => $estadoNuevoId,
                'catalogo_lista_descuento_id' => $solicitud->catalogo_lista_descuento_id,
            ]);

            $snapshotDiff = $this->aplicarBeneficiosCliente($solicitud);

            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => $estadoAnteriorId,
                'estado_nuevo_id' => $estadoNuevoId,
                'motivo_reporte' => 'CAMBIO DE LISTA CONFIRMADO Y APLICADO POR ADMINISTRACIÓN',
                'datos_snapshot' => !empty($snapshotDiff) ? $snapshotDiff : null,
            ]);
        });

        return $this->redirectToSolicitudesList($request, 'Ajuste de lista confirmado. Los registros del cliente han sido actualizados.');
    }

    public function update(Request $request, SolicitudTag $solicitud)
    {
        $idIncorrecta = CatalogoEstadoSolicitud::idDe('Incorrecta');
        $idPendiente = CatalogoEstadoSolicitud::idDe('Pendiente');

        if ($solicitud->vendedor_id !== Auth::id() || (int) $solicitud->catalogo_estado_solicitud_id !== (int) $idIncorrecta) {
            abort(403, 'No tienes permiso para editar esta solicitud o no está en estado Incorrecto.');
        }

        if ($solicitud->motivo_incorrecta === 'vencimiento_pago') {
            abort(403, 'No se puede reparar una solicitud vencida por falta de pago. Debe iniciar una nueva solicitud.');
        }

        $proceso = CatalogoProceso::find($request->input('catalogo_proceso_id'));
        $compraSoloTag = CrearSolicitudService::esProcesoAsignarTagSolo($proceso)
            && CrearSolicitudService::flagCompraEnTiendaAplica(
                $proceso,
                $request->input('compra_en_tienda_solo_tag')
            );
        $compraEnTienda = !$compraSoloTag && CrearSolicitudService::flagCompraEnTiendaAplica(
            $proceso,
            $request->input('compra_en_tienda')
        );
        $flujoTienda = $compraEnTienda || $compraSoloTag;

        $request->validate([
            'monto_cotizado' => [$flujoTienda ? 'nullable' : 'required', 'numeric', 'min:0'],
            'catalogo_proceso_id' => 'required|exists:catalogo_procesos,id',
            'catalogo_tipo_cliente_id' => 'nullable|exists:catalogo_tipo_clientes,id',
            'catalogo_lista_descuento_id' => 'nullable|exists:catalogo_listas_descuento,id',
            'observaciones_vendedor' => 'nullable|string',
            'compra_en_tienda' => 'nullable|boolean',
            'compra_en_tienda_solo_tag' => 'nullable|boolean',
        ]);

        $listaDescuentoId = $request->catalogo_lista_descuento_id;
        $montoCotizado = (float) ($request->monto_cotizado ?? 0);

        if ($compraSoloTag) {
            $listaDescuentoId = null;
            $montoCotizado = 0;
        } elseif ($compraEnTienda) {
            $listaDescuentoId = CrearSolicitudService::buscarListaBronce()?->id;
            $montoCotizado = 0;
        }

        DB::transaction(function () use (
            $solicitud,
            $request,
            $idIncorrecta,
            $idPendiente,
            $compraEnTienda,
            $compraSoloTag,
            $listaDescuentoId,
            $montoCotizado
        ) {
            $solicitud->update([
                'monto_cotizado' => $montoCotizado,
                'catalogo_proceso_id' => $request->catalogo_proceso_id,
                'catalogo_tipo_cliente_id' => $request->catalogo_tipo_cliente_id,
                'catalogo_lista_descuento_id' => $listaDescuentoId,
                'observaciones_vendedor' => $request->observaciones_vendedor,
                'compra_en_tienda' => $compraEnTienda,
                'compra_en_tienda_solo_tag' => $compraSoloTag,
                'catalogo_estado_solicitud_id' => $idPendiente,
                'motivo_incorrecta' => null,
            ]);

            $clienteSnapshot = null;
            if ($solicitud->cliente_id) {
                $cliente = Cliente::with(['listaDescuento', 'vendedor', 'tipo'])->find($solicitud->cliente_id);
                $clienteSnapshot = ['antes' => $this->capturarSnapshotCliente($cliente)];
            }

            $motivoReparacion = $compraSoloTag
                ? 'El colaborador corrigió la solicitud. Compra en tienda reportada (solo TAG): sin lista ni cotización.'
                : ($compraEnTienda
                    ? 'El colaborador corrigió la solicitud. Compra en tienda: lista Bronce, sin cotización.'
                    : 'El colaborador corrigió la solicitud.');

            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => $idIncorrecta,
                'estado_nuevo_id' => $idPendiente,
                'motivo_reporte' => $motivoReparacion,
                'datos_snapshot' => array_merge([
                    'monto_cotizado' => $montoCotizado,
                    'proceso_id' => $request->catalogo_proceso_id,
                    'tipo_cliente_id' => $request->catalogo_tipo_cliente_id,
                    'lista_descuento_id' => $listaDescuentoId,
                    'observaciones_vendedor' => $request->observaciones_vendedor,
                    'compra_en_tienda' => $compraEnTienda,
                    'compra_en_tienda_solo_tag' => $compraSoloTag,
                    'lista_bronce_autoasignada' => $compraEnTienda,
                ], $clienteSnapshot ?? []),
            ]);

            $destinatarios = $this->obtenerDestinatariosDepartamentales($solicitud, false);

            if ($destinatarios->isNotEmpty()) {
                Notification::send($destinatarios, new AlertaSolicitud(
                    $solicitud,
                    'reparada',
                    'El colaborador ' . Auth::user()->name . ' ha reparado la solicitud FOL-' . $solicitud->id . '.'
                ));
            }
        });

        return $this->redirectToSolicitudesList($request, 'Solicitud corregida y enviada a revisión.');
    }

    public function actualizarEstado(Request $request, SolicitudTag $solicitud)
    {
        $usuario = Auth::user();
        $esVendedoraPropia = $solicitud->vendedor_id === $usuario->id;
        $puedeGestionarEstado = $usuario->hasAnyPermission(['solicitudes.verificar', 'solicitudes.reportar'])
            || $usuario->hasRole('Gerente')
            || $esVendedoraPropia;

        if (!$puedeGestionarEstado) {
            abort(403, 'No tienes permiso para actualizar el estado de esta solicitud.');
        }

        $request->validate([
            'catalogo_estado_solicitud_id' => 'required|exists:catalogo_estados_solicitud,id',
            'motivo' => 'nullable|string',
            'evidencia_respuesta' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);

        $estadoAnteriorId = (int) $solicitud->catalogo_estado_solicitud_id;
        $estadoNuevoId = (int) $request->catalogo_estado_solicitud_id;
        $idPendiente = (int) CatalogoEstadoSolicitud::idDe('Pendiente');
        $idRespondida = (int) CatalogoEstadoSolicitud::idDe('Respondida');
        $idVerificada = (int) CatalogoEstadoSolicitud::idDe('Verificada');
        $idIncorrecta = (int) CatalogoEstadoSolicitud::idDe('Incorrecta');

        if ($estadoNuevoId === $idRespondida) {
            if (!$usuario->can('solicitudes.reportar')) {
                abort(403, 'Solo las encargadas pueden aprobar procesos.');
            }
            $esCorreccionErrorReportado = $estadoAnteriorId === $idIncorrecta
                && $solicitud->motivo_incorrecta === 'error_reportado';
            if ($estadoAnteriorId !== $idPendiente && !$esCorreccionErrorReportado) {
                abort(422, 'Solo se puede aprobar una solicitud en estado Pendiente o corregir un error reportado.');
            }
        }

        // Solo Tag: al aprobar se marca pago_confirmado (vendedora no hace más), pero sigue pendiente de verificar.
        $aprobarSoloTag = $estadoNuevoId === $idRespondida && $solicitud->compra_en_tienda_solo_tag;

        if ($estadoNuevoId === $idVerificada) {
            if (!$usuario->can('solicitudes.verificar')) {
                abort(403, 'No tienes permiso para verificar solicitudes.');
            }
            if ($estadoAnteriorId !== $idRespondida) {
                abort(422, 'Solo se pueden verificar solicitudes respondidas.');
            }
            if (!$solicitud->pago_confirmado) {
                abort(422, 'No se puede verificar una solicitud con pago pendiente.');
            }
        }

        if ($estadoNuevoId === $idIncorrecta && $estadoAnteriorId === $idIncorrecta) {
            abort(422, 'Esta solicitud ya está marcada como incorrecta.');
        }

        $esStaffSolicitudes = $usuario->hasAnyPermission(['solicitudes.verificar', 'solicitudes.reportar'])
            || $usuario->hasRole('Gerente');

        if ($esVendedoraPropia && !$esStaffSolicitudes) {
            if ($estadoNuevoId !== $idIncorrecta) {
                abort(403, 'Como vendedora, solo puedes reportar un error en tu propia solicitud.');
            }
            if ($estadoAnteriorId !== $idRespondida) {
                abort(422, 'Solo puedes reportar error sobre una solicitud respondida.');
            }
        }

        DB::transaction(function () use (
            $solicitud,
            $estadoAnteriorId,
            $estadoNuevoId,
            $request,
            $idRespondida,
            $idVerificada,
            $idIncorrecta,
            $aprobarSoloTag
        ) {

            $rutaEvidencia = $solicitud->evidencia_respuesta_path;
            if ($request->hasFile('evidencia_respuesta')) {
                if ($rutaEvidencia && Storage::disk('public')->exists($rutaEvidencia)) {
                    Storage::disk('public')->delete($rutaEvidencia);
                }
                $rutaEvidencia = $request->file('evidencia_respuesta')->store('evidencias_respuestas', 'public');
            }

            $snapshotDiff = [];

            $datosUpdate = [
                'catalogo_estado_solicitud_id' => $estadoNuevoId,
                'evidencia_respuesta_path' => $rutaEvidencia,
                'motivo_incorrecta' => $estadoNuevoId === $idIncorrecta ? 'error_reportado' : null,
            ];
            if ($aprobarSoloTag) {
                $datosUpdate['pago_confirmado'] = true;
            }

            $solicitud->update($datosUpdate);

            $estadosAprobatorios = [$idRespondida, $idVerificada];
            $solicitud->loadMissing('proceso');
            $esFinanciero = $solicitud->proceso?->esFinanciero() ?? true;

            if ($esFinanciero && in_array($estadoNuevoId, $estadosAprobatorios, true) && !in_array($estadoAnteriorId, $estadosAprobatorios, true)) {
                $snapshotDiff = $this->aplicarBeneficiosCliente($solicitud);
            } elseif ($esFinanciero && $estadoNuevoId === $idIncorrecta && in_array($estadoAnteriorId, $estadosAprobatorios, true)) {
                $snapshotDiff = $this->revertirBeneficiosCliente($solicitud);
            }

            $motivoReporte = $request->motivo ?: 'CAMBIO DE ESTADO OPERATIVO';
            if ($aprobarSoloTag) {
                $motivoReporte = trim(
                    ($request->motivo ? $request->motivo.' · ' : '')
                    .'Aprobado (Compra en tienda: Solo Tag). Concluida para la vendedora — pendiente de verificar. Compra ya cargada en masivo.'
                );
            }

            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => $estadoAnteriorId,
                'estado_nuevo_id' => $estadoNuevoId,
                'motivo_reporte' => $motivoReporte,
                'datos_snapshot' => array_merge(
                    [
                        'evidencia_respuesta_path' => $rutaEvidencia,
                        'compra_en_tienda_solo_tag' => (bool) $solicitud->compra_en_tienda_solo_tag,
                        'pago_confirmado_auto_solo_tag' => $aprobarSoloTag,
                    ],
                    !empty($snapshotDiff) ? $snapshotDiff : []
                ),
            ]);

            $esVendedoraPropia = $solicitud->vendedor_id === Auth::id();
            $reportadoPorVendedora = $estadoNuevoId === $idIncorrecta && $esVendedoraPropia;
            $destinatarios = $this->obtenerDestinatariosDepartamentales($solicitud, !$reportadoPorVendedora);

            if ($destinatarios->isNotEmpty()) {
                $tipoAlerta = $estadoNuevoId === $idIncorrecta ? 'rechazada' : 'actualizacion';

                $mensaje = $estadoNuevoId === $idIncorrecta
                    ? ($reportadoPorVendedora
                        ? 'La vendedora ha reportado un error en la respuesta de su solicitud.'
                        : 'Se ha reportado un error en tu solicitud. Revisa las observaciones.')
                    : ($aprobarSoloTag
                        ? 'Tu solicitud Solo Tag fue aprobada y quedó concluida de tu lado. Queda pendiente de verificar.'
                        : 'El área administrativa ha emitido una resolución para tu solicitud.');

                Notification::send($destinatarios, new AlertaSolicitud(
                    $solicitud,
                    $tipoAlerta,
                    $mensaje,
                    $reportadoPorVendedora ? ['reportado_por_vendedora' => true] : []
                ));
            }
        });

        return $this->redirectToSolicitudesList($request, 'El estado ha sido actualizado correctamente.');
    }

    public function exportar(Request $request, ExportarReporteSolicitudesService $exportService)
    {
        Gate::authorize('solicitudes.exportar');

        $formato = strtolower($request->query('format', 'xlsx'));
        $filtros = $request->except(['format']);

        return match ($formato) {
            'pdf' => $exportService->descargarPdf(Auth::user(), $filtros),
            'csv' => $exportService->descargarCsv(Auth::user(), $filtros),
            default => $exportService->descargarExcel(Auth::user(), $filtros),
        };
    }

    public function rechazarPago(Request $request, SolicitudTag $solicitud)
    {
        Gate::authorize('solicitudes.confirmar_pago');

        DB::transaction(function () use ($solicitud) {
            $estadoAnteriorId = $solicitud->catalogo_estado_solicitud_id;
            $idRespondida = CatalogoEstadoSolicitud::idDe('Respondida');
            $idVerificada = CatalogoEstadoSolicitud::idDe('Verificada');
            $idIncorrecta = CatalogoEstadoSolicitud::idDe('Incorrecta');
            $snapshotDiff = [];

            if (in_array((int) $estadoAnteriorId, [(int) $idRespondida, (int) $idVerificada], true)) {
                $snapshotDiff = $this->revertirBeneficiosCliente($solicitud);
            }

            $solicitud->update([
                'catalogo_estado_solicitud_id' => $idIncorrecta,
                'pago_confirmado' => false,
                'motivo_incorrecta' => 'vencimiento_pago',
            ]);

            if ($solicitud->cliente_id) {
                $cliente = Cliente::find($solicitud->cliente_id);
                if ($cliente) {
                    $cliente->update(['catalogo_tipo_cliente_id' => null]);
                }
            }

            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => $estadoAnteriorId,
                'estado_nuevo_id' => $idIncorrecta,
                'motivo_reporte' => 'PAGO RECHAZADO: Se aplicó la reversión automática de las propiedades del cliente.',
                'datos_snapshot' => !empty($snapshotDiff) ? $snapshotDiff : null,
            ]);

            $destinatarios = $this->obtenerDestinatariosDepartamentales($solicitud, true);

            if ($destinatarios->isNotEmpty()) {
                Notification::send($destinatarios, new AlertaSolicitud(
                    $solicitud,
                    'pago_rechazado',
                    'Tu solicitud ha sido rebotada por falta de pago. Se revirtieron los cambios del cliente.'
                ));
            }
        });

        return $this->redirectToSolicitudesList($request, 'Pago rechazado. La vendedora y auxiliares han sido notificados.');
    }

    private function obtenerDestinatariosDepartamentales(SolicitudTag $solicitud, bool $incluirVendedor = false)
    {
        $destinatarios = collect();

        if ($incluirVendedor && $solicitud->vendedor) {
            $destinatarios->push($solicitud->vendedor);
        }

        $verificadores = User::permission(['solicitudes.verificar', 'solicitudes.reportar'])
            ->whereHas('departamentos', function ($query) use ($solicitud) {
                $query->where('departamentos.id', $solicitud->departamento_id);
            })
            ->get();

        // Misma regla que CrearSolicitudService / SolicitudOperativaController:
        // Super Admin y Administrador reciben alertas de actualización, no solo de alta.
        $adminsGlobales = User::role(['Super Admin', 'Administrador'])->get();

        return $destinatarios->merge($verificadores)
            ->merge($adminsGlobales)
            ->unique('id')
            ->reject(function ($usuario) {
                return $usuario->id === Auth::id();
            });
    }

    public function destroy(SolicitudTag $solicitud, Request $request, EliminarSolicitudService $eliminarService): RedirectResponse
    {
        if (!Auth::user()->can('solicitudes.eliminar')) {
            abort(403, 'No tienes los permisos administrativos necesarios para eliminar registros.');
        }

        $request->validate([
            'motivo' => 'required|string|min:10|max:255'
        ]);

        $eliminarService->ejecutar($solicitud, $request->motivo);

        return redirect()->route('solicitudes.index')->with('success', 'La solicitud ha sido eliminada y el evento ha sido auditado.');
    }

    public function restaurar($id, Request $request): RedirectResponse
    {
        if (!Auth::user()->can('solicitudes.eliminadas')) {
            abort(403, 'No tienes los permisos necesarios para restaurar registros.');
        }

        $solicitud = SolicitudTag::withTrashed()->findOrFail($id);
        $solicitud->restore();

        AuditoriaSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'usuario_id' => Auth::id(),
            'estado_anterior_id' => null,
            'estado_nuevo_id' => $solicitud->catalogo_estado_solicitud_id,
            'motivo_reporte' => 'RESTAURACIÓN DE REGISTRO (INFORMATIVO)',
            'datos_snapshot' => $solicitud->toArray()
        ]);

        return $this->redirectToSolicitudesList($request, 'La solicitud ha sido restaurada correctamente.');
    }

    public function storeConsulta(Request $request, SolicitudTag $solicitud, CrearConsultaSolicitudService $service): RedirectResponse
    {
        $this->autorizarEmitirConsulta();

        $solicitud->refresh();

        $request->validate([
            'consulta_tag' => 'nullable|boolean',
            'consulta_lista' => 'nullable|boolean',
            'comentario_vendedor' => 'nullable|string|max:1000',
        ]);

        $service->ejecutar($solicitud, $request->all());

        return $this->redirectToSolicitudesList($request, 'Consulta enviada al encargado.');
    }

    public function responderConsulta(Request $request, SolicitudTag $solicitud, ConsultaSolicitud $consulta, ResponderConsultaSolicitudService $service): RedirectResponse
    {
        $this->autorizarResponderConsulta();

        $request->validate([
            'respuesta_positiva' => 'required',
            'comentario_encargada' => 'nullable|string|max:1000',
            'evidencia_respuesta' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);

        $service->ejecutar($solicitud, $consulta, $request->all());

        return $this->redirectToSolicitudesList($request, 'Consulta respondida correctamente.');
    }

    public function marcarConsultaLeida(Request $request, SolicitudTag $solicitud, ConsultaSolicitud $consulta): RedirectResponse
    {
        if ($consulta->solicitud_id !== $solicitud->id) {
            abort(404);
        }

        if ((int) $solicitud->vendedor_id !== (int) Auth::id()) {
            abort(403, 'Solo la vendedora dueña puede marcar la consulta como leída.');
        }

        if ($consulta->estado !== 'respondida') {
            abort(422, 'La consulta aún no tiene respuesta.');
        }

        $consulta->update(['leido_vendedor_at' => now()]);

        return $this->redirectToSolicitudesList($request, 'Respuesta marcada como leída.');
    }

    public function solicitarCancelacion(Request $request, SolicitudTag $solicitud, SolicitarCancelacionSolicitudService $service): RedirectResponse
    {
        $solicitud->loadMissing('proceso');
        if ($solicitud->proceso?->esOperativo()) {
            abort(403, 'Las solicitudes operativas se cancelan desde el módulo Cancelaciones y Cotizaciones.');
        }

        $esCambioLista = str_contains(strtoupper($solicitud->proceso?->nombre ?? ''), 'LISTA');

        $request->validate([
            'motivo_cancelacion' => 'required|string|min:10|max:1000',
            'catalogo_lista_rebaja_id' => ($esCambioLista ? 'required' : 'nullable') . '|integer|exists:catalogo_listas_descuento,id',
        ]);

        $service->ejecutar(
            $solicitud,
            $request->motivo_cancelacion,
            $request->input('catalogo_lista_rebaja_id') ? (int) $request->catalogo_lista_rebaja_id : null,
        );

        return $this->redirectToSolicitudesList($request, 'Solicitud de cancelación enviada al área administrativa.');
    }

    public function cancelar(Request $request, SolicitudTag $solicitud, CancelarSolicitudService $service): RedirectResponse
    {
        Gate::authorize('solicitudes.cancelar');

        $solicitud->loadMissing('proceso');
        if ($solicitud->proceso?->esOperativo()) {
            abort(403, 'Las solicitudes operativas se cancelan desde el módulo Cancelaciones y Cotizaciones.');
        }

        $request->validate([
            'motivo_cancelacion' => 'nullable|string|max:1000',
        ]);

        $service->ejecutar($solicitud, $request->motivo_cancelacion);

        return $this->redirectToSolicitudesList($request, 'La solicitud ha sido cancelada correctamente.');
    }

    public function confirmarRollback(Request $request, SolicitudTag $solicitud): RedirectResponse
    {
        Gate::authorize('solicitudes.reportar');

        if ($solicitud->motivo_incorrecta !== 'vencimiento_pago') {
            abort(403, 'Esta solicitud no requiere confirmación de reversión.');
        }

        if ($solicitud->rollback_confirmado_at) {
            abort(422, 'La reversión ya fue confirmada.');
        }

        DB::transaction(function () use ($solicitud, $request) {
            $snapshotDiff = [];

            if ($solicitud->cliente_id) {
                $cliente = Cliente::with(['listaDescuento', 'vendedor', 'tipo'])->find($solicitud->cliente_id);
                $snapshotDiff['despues'] = $this->capturarSnapshotCliente($cliente);
            }

            $solicitud->update(['rollback_confirmado_at' => now()]);

            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => $solicitud->catalogo_estado_solicitud_id,
                'estado_nuevo_id' => $solicitud->catalogo_estado_solicitud_id,
                'motivo_reporte' => $request->input('motivo', 'REVERSIÓN CONFIRMADA POR ENCARGADA: Folio cerrado por vencimiento de pago.'),
                'datos_snapshot' => !empty($snapshotDiff) ? $snapshotDiff : null,
            ]);

            if ($solicitud->vendedor) {
                $solicitud->vendedor->notify(new AlertaSolicitud(
                    $solicitud,
                    'rollback_confirmado',
                    'La encargada confirmó la reversión del folio FOL-' . $solicitud->id . '. Debes iniciar una nueva solicitud.'
                ));
            }
        });

        return $this->redirectToSolicitudesList($request, 'Reversión confirmada. La vendedora ha sido notificada.');
    }

    private function capturarSnapshotCliente(?Cliente $cliente): array
    {
        if (!$cliente) {
            return [];
        }

        $cliente->loadMissing(['listaDescuento', 'vendedor', 'tipo']);

        return [
            'monto_venta' => $cliente->monto_venta_actual,
            'lista_id' => $cliente->lista_actual_id,
            'lista_nombre' => $cliente->listaDescuento?->nombre,
            'tag_vendedor_id' => $cliente->vendedor_id,
            'tag_vendedor_nombre' => $cliente->vendedor?->name,
            'tipo_cliente_id' => $cliente->catalogo_tipo_cliente_id,
            'tipo_cliente_nombre' => $cliente->tipo?->nombre,
        ];
    }

    private function aplicarBeneficiosCliente(SolicitudTag $solicitud): array
    {
        if (!$solicitud->cliente_id) {
            return [];
        }

        $cliente = Cliente::with(['listaDescuento', 'vendedor', 'tipo'])->find($solicitud->cliente_id);
        if (!$cliente) {
            return [];
        }

        $antes = $this->capturarSnapshotCliente($cliente);

        $montoNuevo = ($cliente->monto_venta_actual ?? 0) + ($solicitud->monto_cotizado ?? 0);
        $this->registrarHistorialMontoSolicitud(
            $cliente,
            $montoNuevo,
            RegistrarHistorialMontoClienteService::ORIGEN_SOLICITUD_APROBACION,
            $solicitud,
        );
        $cliente->monto_venta_actual = $montoNuevo;

        if ($solicitud->catalogo_lista_descuento_id) {
            $cliente->lista_actual_id = $solicitud->catalogo_lista_descuento_id;
        }

        if ($solicitud->catalogo_tipo_cliente_id) {
            $cliente->catalogo_tipo_cliente_id = $solicitud->catalogo_tipo_cliente_id;
        }

        $nombreProceso = strtoupper($solicitud->proceso?->nombre ?? '');
        if ($solicitud->vendedor_id && (
            str_contains($nombreProceso, 'ASIGNAR TAG')
            || str_contains($nombreProceso, 'ASIGNAR CLIENTE')
        )) {
            $cliente->vendedor_id = $solicitud->vendedor_id;
        }

        app(ReactivarClienteInactivoService::class)->ejecutar(
            $cliente,
            $montoNuevo,
            !$solicitud->catalogo_lista_descuento_id,
        );

        $cliente->save();
        $cliente->refresh()->load(['listaDescuento', 'vendedor', 'tipo']);

        return [
            'antes' => $antes,
            'despues' => $this->capturarSnapshotCliente($cliente),
        ];
    }

    private function revertirBeneficiosCliente(SolicitudTag $solicitud): array
    {
        if (!$solicitud->cliente_id) {
            return [];
        }

        $cliente = Cliente::with(['listaDescuento', 'vendedor', 'tipo'])->find($solicitud->cliente_id);
        if (!$cliente) {
            return [];
        }

        $antes = $this->capturarSnapshotCliente($cliente);

        $nuevoMonto = max(0, ($cliente->monto_venta_actual ?? 0) - ($solicitud->monto_cotizado ?? 0));
        $this->registrarHistorialMontoSolicitud(
            $cliente,
            $nuevoMonto,
            RegistrarHistorialMontoClienteService::ORIGEN_SOLICITUD_REVERSION,
            $solicitud,
        );
        $cliente->monto_venta_actual = $nuevoMonto;
        $this->recalcularListaCliente($cliente);
        $cliente->save();
        $cliente->refresh()->load(['listaDescuento', 'vendedor', 'tipo']);

        return [
            'antes' => $antes,
            'despues' => $this->capturarSnapshotCliente($cliente),
        ];
    }

    /**
     * Recalcula la lista de descuento del cliente basada estrictamente en su monto actual.
     * Excluye listas protegidas del cálculo general.
     */
    private function recalcularListaCliente(Cliente $cliente): void
    {
        $listaCalificada = CatalogoListaDescuento::where('activo', true)
            ->where('nombre', 'not like', '%COLABORADOR%')
            ->where('nombre', 'not like', '%PLATAFORMAS%')
            ->where('monto_requerido', '<=', $cliente->monto_venta_actual)
            ->orderBy('monto_requerido', 'desc')
            ->first();

        // Si no califica para ninguna por el monto (ej. monto 0), se asigna null o se maneja a Público General.
        $cliente->lista_actual_id = $listaCalificada ? $listaCalificada->id : null;
    }

    private function capturarSnapshotClienteSinCambioMonetario(SolicitudTag $solicitud): array
    {
        if (!$solicitud->cliente_id) {
            return [];
        }

        $cliente = Cliente::with(['listaDescuento', 'vendedor', 'tipo'])->find($solicitud->cliente_id);
        if (!$cliente) {
            return [];
        }

        $snapshot = $this->capturarSnapshotCliente($cliente);

        return [
            'antes' => $snapshot,
            'despues' => $snapshot,
        ];
    }

    private function beneficiosProvisionalEstanAplicados(SolicitudTag $solicitud, Cliente $cliente): bool
    {
        $montoCotizado = (float) ($solicitud->monto_cotizado ?? 0);
        if ($montoCotizado <= 0) {
            return false;
        }

        $auditoriaAprobacion = AuditoriaSolicitud::query()
            ->where('solicitud_id', $solicitud->id)
            ->where('estado_nuevo_id', CatalogoEstadoSolicitud::idDe('Respondida'))
            ->whereNotNull('datos_snapshot')
            ->orderByDesc('id')
            ->first();

        if (!$auditoriaAprobacion) {
            return false;
        }

        $snapshot = $auditoriaAprobacion->datos_snapshot;
        $montoAntesAprobacion = isset($snapshot['antes']['monto_venta'])
            ? (float) $snapshot['antes']['monto_venta']
            : null;
        $montoDespuesAprobacion = isset($snapshot['despues']['monto_venta'])
            ? (float) $snapshot['despues']['monto_venta']
            : null;

        if ($montoDespuesAprobacion === null || $montoAntesAprobacion === null) {
            return false;
        }

        $incrementoEsperado = round($montoDespuesAprobacion - $montoAntesAprobacion, 2);
        if ($incrementoEsperado <= 0) {
            return false;
        }

        $montoClienteActual = round((float) $cliente->monto_venta_actual, 2);

        return abs($montoClienteActual - $montoDespuesAprobacion) < 0.01
            || $montoClienteActual >= $montoDespuesAprobacion;
    }

    private function registrarHistorialMontoSolicitud(
        Cliente $cliente,
        float $montoNuevo,
        string $origen,
        SolicitudTag $solicitud,
        ?float $montoOperacion = null,
        ?string $notasExtra = null,
    ): void {
        $solicitud->loadMissing('vendedor');
        $notas = trim(
            ($notasExtra ? $notasExtra . ' — ' : '')
            . 'Solicitante: ' . ($solicitud->vendedor?->name ?? 'N/A')
        );

        app(RegistrarHistorialMontoClienteService::class)->registrar(
            $cliente,
            $montoNuevo,
            $origen,
            Auth::id(),
            null,
            $solicitud->id,
            $montoOperacion ?? (float) $solicitud->monto_cotizado,
            $notas,
        );
    }
}
