<?php

namespace App\Http\Controllers\Solicitudes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Solicitudes\StoreSolicitudRequest;
use App\Services\Solicitudes\CrearSolicitudService;
use App\Services\Solicitudes\ListarSolicitudesService;
use App\Services\Solicitudes\EliminarSolicitudService;
use Illuminate\Http\RedirectResponse;
use App\Models\SolicitudTag;
use App\Models\AuditoriaSolicitud;
use App\Models\Cliente;
use App\Models\User;
use App\Notifications\AlertaSolicitud;
use App\Models\CatalogoProceso;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoTipoCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;
use Rap2hpoutre\FastExcel\FastExcel;

class SolicitudController extends Controller
{
    public function index(Request $request, ListarSolicitudesService $listarService): Response
    {
        $solicitudes = $listarService->ejecutar(Auth::user(), $request->all());
        $procesos = CatalogoProceso::where('activo', true)->get();

        // Obtenemos los usuarios que pueden ser vendedores/colaboradores para el filtro
        $vendedores = User::whereHas('roles', function($q) {
            $q->whereIn('name', ['colaborador', 'Administrador', 'Super Admin']); // Ajusta según tus roles reales
        })->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Solicitudes/Index', [
            'solicitudes' => $solicitudes,
            'filtros' => $request->all(), // Pasamos los filtros actuales para mantener el estado en React
            'procesos' => $procesos,
            'listas' => CatalogoListaDescuento::where('activo', true)->get(),
            'tipos_cliente' => CatalogoTipoCliente::where('activo', true)->orderBy('nombre')->get(),
            'vendedores' => $vendedores, // Nueva variable enviada al frontend
        ]);
    }

    public function store(StoreSolicitudRequest $request, CrearSolicitudService $crearSolicitudService): RedirectResponse
    {
        $datosValidados = $request->validated();
        $crearSolicitudService->ejecutar($datosValidados, Auth::id());

        return redirect()->back()->with('success', 'Solicitud creada correctamente.');
    }

    public function confirmarPago(Request $request, SolicitudTag $solicitud)
    {
        // 1. Seguro Arquitectónico: Bloquear si la encargada no ha aprobado (Estado 2)
        if ($solicitud->catalogo_estado_solicitud_id != 2) {
            abort(403, 'Acción Bloqueada: El pago solo puede confirmarse después de que la encargada haya emitido una resolución aprobatoria.');
        }

        if ($solicitud->vendedor_id !== Auth::id() && !Auth::user()->can('solicitudes.confirmar_pago')) {
            abort(403, 'No tienes autorización para confirmar este pago.');
        }

        $request->validate([
            'monto_final_pagado' => 'required|numeric|min:0'
        ]);

        DB::transaction(function () use ($solicitud, $request) {
            $montoOriginal = $solicitud->monto_cotizado;
            $montoFinal = $request->monto_final_pagado;
            $estadoNuevoId = 2; // Mantenemos el estado aprobado por defecto
            $mensajeAuditoria = 'PAGO CONFIRMADO POR EL COLABORADOR';
            $esAlertaFaltaPago = false;

            if ($solicitud->catalogo_lista_descuento_id && $solicitud->cliente_id) {
                $cliente = Cliente::find($solicitud->cliente_id);
                $listaSolicitada = CatalogoListaDescuento::find($solicitud->catalogo_lista_descuento_id);

                if ($cliente && $listaSolicitada) {
                    // CÁLCULO DE PROYECCIÓN INDEPENDIENTE
                    // Se neutralizan balances negativos con max() para evitar que el pago real se absorba por un déficit histórico.
                    $montoHistoricoBase = max(0, $cliente->monto_venta_actual - $montoOriginal);
                    $totalProyectado = $montoHistoricoBase + $montoFinal;

                    if ($totalProyectado < $listaSolicitada->monto_requerido) {

                        $listaCalificada = CatalogoListaDescuento::where('activo', true)
                            ->where('nombre', 'not like', '%COLABORADOR%')
                            ->where('monto_requerido', '<=', $totalProyectado)
                            ->orderBy('monto_requerido', 'desc')
                            ->first();

                        $nombreListaCalificada = $listaCalificada ? $listaCalificada->nombre : 'Público General';
                        $mensajeAuditoria = "ALERTA DE PAGO: Pago final de $" . number_format($montoFinal, 2) . " es insuficiente para la lista {$listaSolicitada->nombre}. El cliente califica para: {$nombreListaCalificada}.";

                        $estadoNuevoId = 4;
                        $esAlertaFaltaPago = true;
                    }
                }
            }

            if ($esAlertaFaltaPago) {
                // ROLLBACK: El pago no cubre la cuota, revertimos todo el monto y quitamos la lista para revisión.
                $this->revertirBeneficiosCliente($solicitud);

                $solicitud->update([
                    'pago_confirmado' => false,
                    'monto_cotizado' => $montoFinal,
                    'catalogo_estado_solicitud_id' => $estadoNuevoId
                ]);
            } else {
                // ÉXITO: Ajustamos el Delta si la vendedora cobró una cantidad diferente a la cotizada
                $diferencia = $montoFinal - $montoOriginal;
                if ($diferencia != 0 && $solicitud->cliente_id) {
                    $clienteObj = Cliente::find($solicitud->cliente_id);
                    $clienteObj->monto_venta_actual = max(0, $clienteObj->monto_venta_actual + $diferencia);
                    $clienteObj->save();
                }

                $solicitud->update([
                    'pago_confirmado' => true,
                    'monto_cotizado' => $montoFinal,
                    'catalogo_estado_solicitud_id' => $estadoNuevoId
                ]);
            }

            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => 2,
                'estado_nuevo_id' => $estadoNuevoId,
                'motivo_reporte' => $mensajeAuditoria
            ]);

            $destinatarios = $this->obtenerDestinatariosDepartamentales($solicitud, false);
            if ($destinatarios->isNotEmpty()) {
                $tituloAlerta = $esAlertaFaltaPago ? 'alerta_pago_insuficiente' : 'pago_confirmado';
                Notification::send($destinatarios, new AlertaSolicitud(
                    $solicitud,
                    $tituloAlerta,
                    'El colaborador ' . Auth::user()->name . ($esAlertaFaltaPago ? ' intentó confirmar un pago insuficiente. Requiere ajuste de lista.' : ' ha confirmado el pago exitosamente.')
                ));
            }
        });

        return back()->with('success', 'Operación procesada.');
    }

    public function confirmarCambioLista(Request $request, SolicitudTag $solicitud)
    {
        Gate::authorize('solicitudes.confirmar_cambio_lista');

        DB::transaction(function () use ($solicitud) {
            $estadoNuevoId = 3;

            $cliente = Cliente::find($solicitud->cliente_id);
            if ($cliente) {
                $totalProyectado = ($cliente->monto_venta_actual ?? 0) + $solicitud->monto_cotizado;

                // Recalcular lista real a la que califica el cliente excluyendo casos especiales
                $listaCalificada = CatalogoListaDescuento::where('activo', true)
                    ->where('nombre', 'not like', '%COLABORADOR%')
                    ->where('nombre', 'not like', '%PLATAFORMAS%') // Exclusión de seguridad
                    ->where('monto_requerido', '<=', $totalProyectado)
                    ->orderBy('monto_requerido', 'desc')
                    ->first();

                if ($listaCalificada) {
                    $solicitud->catalogo_lista_descuento_id = $listaCalificada->id;
                }
            }

            $solicitud->update([
                'catalogo_estado_solicitud_id' => $estadoNuevoId,
                'catalogo_lista_descuento_id' => $solicitud->catalogo_lista_descuento_id
            ]);

            $this->aplicarBeneficiosCliente($solicitud);

            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => $solicitud->catalogo_estado_solicitud_id,
                'estado_nuevo_id' => $estadoNuevoId,
                'motivo_reporte' => 'CAMBIO DE LISTA CONFIRMADO Y APLICADO POR ADMINISTRACIÓN'
            ]);
        });

        return back()->with('success', 'Ajuste de lista confirmado. Los registros del cliente han sido actualizados.');
    }

    public function update(Request $request, SolicitudTag $solicitud)
    {
        if ($solicitud->vendedor_id !== Auth::id() || $solicitud->catalogo_estado_solicitud_id != 4) {
            abort(403, 'No tienes permiso para editar esta solicitud o no está en estado Incorrecto.');
        }

        $request->validate([
            'monto_cotizado' => 'required|numeric|min:0',
            'catalogo_proceso_id' => 'required|exists:catalogo_procesos,id',
            'catalogo_tipo_cliente_id' => 'nullable|exists:catalogo_tipo_clientes,id',
            'catalogo_lista_descuento_id' => 'nullable|exists:catalogo_listas_descuento,id',
            'evidencia' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);

        DB::transaction(function () use ($solicitud, $request) {
            $rutaEvidencia = $solicitud->evidencia_path;

            if ($request->hasFile('evidencia')) {
                if ($rutaEvidencia && Storage::disk('public')->exists($rutaEvidencia)) {
                    Storage::disk('public')->delete($rutaEvidencia);
                }
                $rutaEvidencia = $request->file('evidencia')->store('evidencias_solicitudes', 'public');
            }

            $solicitud->update([
                'monto_cotizado' => $request->monto_cotizado,
                'catalogo_proceso_id' => $request->catalogo_proceso_id,
                'catalogo_tipo_cliente_id' => $request->catalogo_tipo_cliente_id,
                'catalogo_lista_descuento_id' => $request->catalogo_lista_descuento_id,
                'evidencia_path' => $rutaEvidencia,
                'catalogo_estado_solicitud_id' => 1,
            ]);

            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => 4,
                'estado_nuevo_id' => 1,
                'motivo_reporte' => 'El colaborador corrigió la solicitud y subió nueva evidencia.',
                'datos_snapshot' => [
                    'monto_cotizado' => $request->monto_cotizado,
                    'proceso_id' => $request->catalogo_proceso_id,
                    'tipo_cliente_id' => $request->catalogo_tipo_cliente_id,
                    'lista_descuento_id' => $request->catalogo_lista_descuento_id,
                    'evidencia_path' => $rutaEvidencia,
                ]
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

        return back()->with('success', 'Solicitud corregida y enviada a revisión.');
    }

    public function actualizarEstado(Request $request, SolicitudTag $solicitud)
    {
        Gate::any(['solicitudes.verificar', 'solicitudes.reportar']);

        $request->validate([
            'catalogo_estado_solicitud_id' => 'required|exists:catalogo_estados_solicitud,id',
            'motivo' => 'nullable|string',
            'evidencia_respuesta' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);

        $estadoAnteriorId = $solicitud->catalogo_estado_solicitud_id;
        $estadoNuevoId = $request->catalogo_estado_solicitud_id;

        DB::transaction(function () use ($solicitud, $estadoAnteriorId, $estadoNuevoId, $request) {

            $rutaEvidencia = $solicitud->evidencia_respuesta_path;
            if ($request->hasFile('evidencia_respuesta')) {
                if ($rutaEvidencia && Storage::disk('public')->exists($rutaEvidencia)) {
                    Storage::disk('public')->delete($rutaEvidencia);
                }
                $rutaEvidencia = $request->file('evidencia_respuesta')->store('evidencias_respuestas', 'public');
            }

            $solicitud->update([
                'catalogo_estado_solicitud_id' => $estadoNuevoId,
                'evidencia_respuesta_path' => $rutaEvidencia,
            ]);

            // ==========================================
            // MOTOR FINANCIERO CORREGIDO (Estados 2 y 3)
            // ==========================================
            $estadosAprobatorios = [2, 3]; // 2 = Aprobada/Respondida, 3 = Verificada

            if (in_array($estadoNuevoId, $estadosAprobatorios) && !in_array($estadoAnteriorId, $estadosAprobatorios)) {
                // Suman beneficios solo la primera vez que se aprueba/verifica
                $this->aplicarBeneficiosCliente($solicitud);
            } elseif ($estadoNuevoId == 4 && in_array($estadoAnteriorId, $estadosAprobatorios)) {
                // Rollback: Si un admin regresa una solicitud ya aprobada a "Incorrecta", restamos el dinero.
                $this->revertirBeneficiosCliente($solicitud);
            }
            // ==========================================

            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => $estadoAnteriorId,
                'estado_nuevo_id' => $estadoNuevoId,
                'motivo_reporte' => $request->motivo ?: 'CAMBIO DE ESTADO OPERATIVO',
                'datos_snapshot' => [
                    'evidencia_respuesta_path' => $rutaEvidencia
                ]
            ]);

            $destinatarios = $this->obtenerDestinatariosDepartamentales($solicitud, true);

            if ($destinatarios->isNotEmpty()) {
                $tipoAlerta = $estadoNuevoId == 4 ? 'rechazada' : 'actualizacion';
                $mensaje = $estadoNuevoId == 4
                    ? 'Se ha reportado un error en tu solicitud. Revisa las observaciones.'
                    : 'El área administrativa ha emitido una resolución para tu solicitud.';

                Notification::send($destinatarios, new AlertaSolicitud(
                    $solicitud,
                    $tipoAlerta,
                    $mensaje
                ));
            }
        });

        return back()->with('success', 'El estado ha sido actualizado correctamente.');
    }

    public function exportar(Request $request, ListarSolicitudesService $listarService)
    {
        $solicitudes = $listarService->ejecutar(Auth::user(), $request->all(), false);
        $nombreArchivo = 'reporte_solicitudes_' . date('Y-m-d_H-i-s') . '.xlsx';

        return (new FastExcel($solicitudes))->download($nombreArchivo, function ($solicitud) {
            return [
                'Folio' => $solicitud->id,
                'Fecha Solicitud' => $solicitud->created_at->format('Y-m-d H:i'),
                'Vendedora' => $solicitud->vendedor->name ?? 'N/A',
                'No. Cliente' => $solicitud->cliente->numero_cliente ?? 'N/A',
                'Nombre Cliente' => $solicitud->cliente->nombre ?? 'N/A',
                'Tipo de Proceso' => $solicitud->proceso->nombre ?? 'N/A',
                'Tipo Cliente (Clasificación)' => $solicitud->tipoCliente->nombre ?? 'Normal',
                'Lista Solicitada' => $solicitud->listaDescuento->nombre ?? 'Mantener Actual',
                'Estado' => $solicitud->estado->nombre ?? 'N/A',
                'Monto Cotizado ($)' => $solicitud->monto_cotizado,
                'Pago Confirmado' => $solicitud->pago_confirmado ? 'Sí' : 'No',
                'Observaciones' => $solicitud->observaciones_vendedor ?? 'Ninguna',
            ];
        });
    }

    public function rechazarPago(Request $request, SolicitudTag $solicitud)
    {
        Gate::authorize('solicitudes.confirmar_pago');

        DB::transaction(function () use ($solicitud) {
            $estadoAnteriorId = $solicitud->catalogo_estado_solicitud_id;

            $solicitud->update([
                'catalogo_estado_solicitud_id' => 4, // Incorrecta
                'pago_confirmado' => false
            ]);

            // Evitar descuadres si ya estaba aprobada
            if (in_array($estadoAnteriorId, [2, 3])) {
                $this->revertirBeneficiosCliente($solicitud);
            }

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
                'estado_nuevo_id' => 4,
                'motivo_reporte' => 'PAGO RECHAZADO: Se aplicó la reversión automática de las propiedades del cliente.'
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

        return back()->with('success', 'Pago rechazado. La vendedora y auxiliares han sido notificados.');
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

        return $destinatarios->merge($verificadores)
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

    private function aplicarBeneficiosCliente(SolicitudTag $solicitud): void
    {
        if (!$solicitud->cliente_id) return;

        $cliente = Cliente::find($solicitud->cliente_id);
        if (!$cliente) return;

        $cliente->monto_venta_actual = ($cliente->monto_venta_actual ?? 0) + ($solicitud->monto_cotizado ?? 0);

        if ($solicitud->catalogo_lista_descuento_id) {
            $cliente->lista_actual_id = $solicitud->catalogo_lista_descuento_id;
        }

        if ($solicitud->catalogo_tipo_cliente_id) {
            $cliente->catalogo_tipo_cliente_id = $solicitud->catalogo_tipo_cliente_id;
        }

        $cliente->save();
    }

    private function revertirBeneficiosCliente(SolicitudTag $solicitud): void
    {
        if (!$solicitud->cliente_id) return;

        $cliente = Cliente::find($solicitud->cliente_id);
        if (!$cliente) return;

        // Restamos el monto, evitando que quede en negativo con max()
        $nuevoMonto = ($cliente->monto_venta_actual ?? 0) - ($solicitud->monto_cotizado ?? 0);
        $cliente->monto_venta_actual = max(0, $nuevoMonto);

        // Nota: Para un rollback de listas habría que hacer una lógica histórica más compleja, 
        // pero con restaurar el monto de venta es suficiente para corregir descuadres monetarios.

        $cliente->save();
    }
}
