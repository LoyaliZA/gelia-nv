<?php

namespace App\Http\Controllers\Escalonamiento;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Escalonamiento\DocumentoVenta;
use App\Models\Escalonamiento\EscalonamientoAplicacionDevolucion;
use App\Models\Escalonamiento\EscalonamientoConciliacionSolicitud;
use App\Models\Escalonamiento\EscalonamientoImportacion;
use App\Models\Escalonamiento\EscalonamientoIncidencia;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;
use App\Models\SolicitudTag;
use App\Services\Escalonamiento\AbrirPeriodoEscalonamiento;
use App\Services\Escalonamiento\AsegurarPeriodoHistoricoEscalonamiento;
use App\Services\Escalonamiento\MetricasPeriodoEscalonamiento;
use App\Services\Escalonamiento\EscalonamientoAutoridadConfig;
use App\Services\Escalonamiento\ConciliacionSolicitudEscalonamiento;
use App\Services\Escalonamiento\ArmarFichaClienteEscalonamiento;
use App\Services\Escalonamiento\ConsultarAcumuladoCliente;
use App\Services\Escalonamiento\Excepciones\AcumuladoNegativoException;
use App\Services\Escalonamiento\Excepciones\CapacidadRemisionException;
use App\Services\Escalonamiento\Excepciones\PeriodoNoAbiertoException;
use App\Services\Escalonamiento\Excepciones\VinculoDevolucionException;
use App\Services\Escalonamiento\ImportarDocumentosEscalonamiento;
use App\Services\Escalonamiento\ListarCandidatasRemisionVinculada;
use App\Services\Escalonamiento\ListarComparacionClientesEscalonamiento;
use App\Services\Escalonamiento\ListasPeriodoEscalonamiento;
use App\Services\Escalonamiento\AplicarCierreEscalonamiento;
use App\Services\Escalonamiento\AutorizarCierreEscalonamiento;
use App\Services\Escalonamiento\CancelarSimulacionCierreEscalonamiento;
use App\Services\Escalonamiento\Excepciones\CierreEscalonamientoException;
use App\Services\Escalonamiento\RegistrarAplicacionExternaErp;
use App\Services\Escalonamiento\RevertirAplicacionDevolucion;
use App\Services\Escalonamiento\RevisionCierrePrevioDiasUnoDos;
use App\Services\Escalonamiento\SimularCierreEscalonamiento;
use App\Services\Escalonamiento\VincularDevolucionARemision;
use App\Models\Escalonamiento\EscalonamientoCierre;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class EscalonamientoController extends Controller
{
    public function __construct(
        private AbrirPeriodoEscalonamiento $abrirPeriodo,
        private ConsultarAcumuladoCliente $consultarAcumulado,
    ) {}

    public function index(Request $request, ListarComparacionClientesEscalonamiento $comparacion, MetricasPeriodoEscalonamiento $metricas, ListasPeriodoEscalonamiento $listasPeriodo): Response
    {
        $periodo = $this->periodoVista($request);
        $clienteFiltroId = (int) $request->get('cliente_id', 0);
        $clienteSeleccionado = $this->etiquetaClienteFiltro($clienteFiltroId);
        $periodoOperativo = $this->periodoAbierto();

        $documentos = 0;
        $acumulado = '0.00';
        $resumenPeriodo = null;
        $clientesComparacion = ['data' => [], 'paginacion' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 0, 'from' => null, 'to' => null]];
        $mensajeDocumentos = null;

        if ($periodo) {
            $documentos = $periodo->documentos()->count();
            $acumulado = (string) $periodo->resumenes()->sum('acumulado');
            $metricasPeriodo = $metricas->paraPeriodo($periodo);
            $resumenPeriodo = [
                'ventas_elegibles' => $metricasPeriodo['compras'],
                'devoluciones_aplicadas' => $metricasPeriodo['devoluciones_aplicadas'],
                'venta_neta' => $metricasPeriodo['venta_neta'],
                'clientes_con_cambios_propuestos' => $this->conteoCambiosPropuestos($periodo),
                'incidencias_abiertas' => $metricasPeriodo['incidencias_abiertas'],
                'pendientes_vinculo' => $metricasPeriodo['pendientes_vinculo'],
                'documentos_registrados' => $metricasPeriodo['documentos_total'],
            ];
            $clientesComparacion = $comparacion->listar($periodo, $request);
            $mensajeDocumentos = $this->mensajeCoberturaDocumentos($periodo, $metricasPeriodo);
        }

        return Inertia::render('Escalonamiento/Index', [
            'periodo' => $periodo ? [
                'id' => $periodo->id,
                'anio' => $periodo->anio,
                'mes' => $periodo->mes,
                'estado' => $periodo->estado,
                'zona_horaria' => $periodo->zona_horaria,
                'corte' => $periodo->fecha_corte?->toDateTimeString(),
                'corte_legible' => $periodo->fecha_corte?->locale('es')->isoFormat('D MMM., HH:mm'),
                'documentos' => $documentos,
                'acumulado' => $acumulado,
                'consulta_historial' => in_array($periodo->estado, [
                    EscalonamientoPeriodo::ESTADO_HISTORIAL,
                    EscalonamientoPeriodo::ESTADO_CERRADO,
                ], true),
            ] : null,
            'resumenPeriodo' => $resumenPeriodo,
            'mensajeDocumentos' => $mensajeDocumentos,
            'clientes' => $clientesComparacion,
            'documentos' => $this->documentosPeriodo($periodo, $request),
            'filtrosClientes' => [
                'q' => (string) $request->get('q', ''),
                'orden' => (string) $request->get('orden', 'numero'),
                'direccion' => (string) $request->get('direccion', 'asc'),
                'filtro_rapido' => (string) $request->get('filtro_rapido', ''),
                'per_page' => (int) $request->get('per_page', 25),
                'tab' => (string) $request->get('tab', 'clientes'),
                'q_doc' => (string) $request->get('q_doc', ''),
                'ocultar_inactivos' => $request->boolean('ocultar_inactivos'),
                'lista_vigente_id' => (string) $request->get('lista_vigente_id', ''),
                'lista_calculada_id' => (string) $request->get('lista_calculada_id', ''),
                'ventas_min' => (string) $request->get('ventas_min', ''),
                'ventas_max' => (string) $request->get('ventas_max', ''),
                'devoluciones_min' => (string) $request->get('devoluciones_min', ''),
                'devoluciones_max' => (string) $request->get('devoluciones_max', ''),
                'resultado' => $this->resultadoFiltroDesdeRequest($request),
                'cliente_id' => $clienteFiltroId > 0 ? $clienteFiltroId : '',
            ],
            'opcionesListas' => $periodo
                ? $listasPeriodo->listasParticipantes($periodo)->map(fn ($lista) => [
                    'id' => $lista->id,
                    'nombre' => $lista->nombre,
                ])->values()->all()
                : [],
            'clienteSeleccionado' => $clienteSeleccionado,
            'puedeOperar' => $request->user()?->can('escalonamiento.operar') ?? false,
            'puedeOperarDocumentos' => $periodoOperativo?->permiteOperacionDocumentos() && ($request->user()?->can('escalonamiento.operar') ?? false),
            'periodoOperativo' => $periodoOperativo ? [
                'id' => $periodoOperativo->id,
                'anio' => $periodoOperativo->anio,
                'mes' => $periodoOperativo->mes,
                'estado' => $periodoOperativo->estado,
            ] : null,
            'periodos' => $this->listarPeriodosResumen(),
            'revisionCierrePrevio' => app(RevisionCierrePrevioDiasUnoDos::class)->banner(),
            'sugerencia' => [
                'anio' => (int) now()->year,
                'mes' => (int) now()->month,
            ],
            'incidencias' => $this->incidencias($request, $periodo, accionables: true),
            'exclusionesInformativas' => $this->incidencias($request, $periodo, accionables: false, soloExclusion: true),
            'filtros_incidencias' => [
                'tipo' => (string) $request->get('tipo_incidencia', ''),
                'estado' => (string) $request->get('estado_incidencia', 'abierta'),
                'gravedad' => (string) $request->get('gravedad_incidencia', ''),
                'cliente_id' => $clienteFiltroId > 0 ? $clienteFiltroId : '',
            ],
            'opcionesIncidencias' => $this->opcionesFiltroIncidencias($periodo),
            'previsualizacion' => $this->previsualizacion($request, $periodoOperativo),
            'devolucionesPendientes' => $this->devolucionesPendientes($periodo),
            'aplicacionesActivas' => $this->aplicacionesActivas($periodo),
        ]);
    }

    public function conciliacion(Request $request, ConciliacionSolicitudEscalonamiento $conciliacion): Response
    {
        $periodo = EscalonamientoPeriodo::query()
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->first();

        return Inertia::render('Escalonamiento/Conciliacion', [
            'periodo' => $periodo ? [
                'id' => $periodo->id,
                'anio' => $periodo->anio,
                'mes' => $periodo->mes,
                'estado' => $periodo->estado,
            ] : null,
            'solicitudes' => $periodo ? $conciliacion->listarSolicitudesPeriodo($periodo) : [],
            'puedeOperar' => $request->user()?->can('escalonamiento.operar') ?? false,
        ]);
    }

    public function sugerirConciliacion(SolicitudTag $solicitud, ConciliacionSolicitudEscalonamiento $conciliacion): JsonResponse
    {
        $periodo = $this->periodoParaConciliacion();
        if (! $periodo) {
            return response()->json(['message' => 'No hay período disponible.'], 422);
        }

        return response()->json([
            'sugerencias' => $conciliacion->sugerir($solicitud, $periodo),
            'importe_objetivo' => $conciliacion->importeObjetivo($solicitud),
        ]);
    }

    public function asignarConciliacion(Request $request, ConciliacionSolicitudEscalonamiento $conciliacion): RedirectResponse
    {
        $datos = $request->validate([
            'solicitud_tag_id' => ['required', 'integer'],
            'documento_venta_id' => ['required', 'integer'],
            'importe_asignado' => ['required', 'numeric', 'gt:0'],
            'evidencia' => ['nullable', 'string', 'max:2000'],
        ]);

        $periodo = $this->periodoParaConciliacion();
        if (! $periodo) {
            return back()->with('error', 'No hay período disponible.');
        }

        $solicitud = SolicitudTag::query()->findOrFail($datos['solicitud_tag_id']);
        $documento = DocumentoVenta::query()->findOrFail($datos['documento_venta_id']);

        try {
            $conciliacion->asignar(
                $periodo,
                $solicitud,
                $documento,
                (string) $datos['importe_asignado'],
                $datos['evidencia'] ?? null,
                $request->user(),
            );
        } catch (\InvalidArgumentException $excepcion) {
            return back()->with('error', $excepcion->getMessage());
        }

        return redirect()
            ->route('escalonamiento.conciliacion')
            ->with('success', 'Documento asignado a la solicitud para cotejo.');
    }

    public function quitarConciliacion(
        EscalonamientoConciliacionSolicitud $conciliacionFila,
        ConciliacionSolicitudEscalonamiento $conciliacion,
    ): RedirectResponse {
        $conciliacion->quitar($conciliacionFila);

        return redirect()
            ->route('escalonamiento.conciliacion')
            ->with('success', 'Asignación de conciliación eliminada.');
    }

    public function fichaCliente(Request $request, Cliente $cliente, ArmarFichaClienteEscalonamiento $armar): Response
    {
        $periodo = $this->periodoVista($request);

        $ficha = $armar->armar($cliente, $periodo);

        return Inertia::render('Escalonamiento/FichaCliente', [
            'cliente' => [
                'id' => $cliente->id,
                'numero_cliente' => $cliente->numero_cliente,
                'nombre' => $cliente->nombre,
            ],
            'periodo' => $periodo ? [
                'id' => $periodo->id,
                'anio' => $periodo->anio,
                'mes' => $periodo->mes,
                'estado' => $periodo->estado,
                'corte' => $periodo->fecha_corte?->toDateTimeString(),
            ] : null,
            'ficha' => $ficha,
            'historialMeses' => $this->historialMesesCliente($cliente),
            'puedeOperar' => request()->user()?->can('escalonamiento.operar') ?? false,
        ]);
    }

    public function metricas(Request $request, MetricasPeriodoEscalonamiento $metricas): Response
    {
        $periodo = $this->periodoVista($request);
        $datos = $periodo ? $metricas->paraPeriodo($periodo) : null;

        return Inertia::render('Escalonamiento/Metricas', [
            'periodo' => $periodo ? [
                'id' => $periodo->id,
                'anio' => $periodo->anio,
                'mes' => $periodo->mes,
                'estado' => $periodo->estado,
            ] : null,
            'periodos' => $this->listarPeriodosResumen(),
            'metricas' => $datos,
            'tareas' => $metricas->tareasOperativas($periodo),
        ]);
    }

    public function exportarClientes(Request $request, ListarComparacionClientesEscalonamiento $comparacion): StreamedResponse
    {
        $periodo = $this->periodoVista($request);
        if (! $periodo) {
            abort(404);
        }

        return $comparacion->exportarCsv($periodo, $request);
    }

    public function buscarClientes(Request $request): JsonResponse
    {
        $busqueda = trim((string) $request->get('q', ''));
        if (mb_strlen($busqueda) < 2) {
            return response()->json(['clientes' => []]);
        }

        $escapado = addcslashes($busqueda, '%_\\');
        $clientes = Cliente::query()
            ->where(function ($q) use ($escapado) {
                $q->where('numero_cliente', 'like', '%'.$escapado.'%')
                    ->orWhere('nombre', 'like', '%'.$escapado.'%');
            })
            ->orderBy('numero_cliente')
            ->limit(15)
            ->get(['id', 'numero_cliente', 'nombre']);

        return response()->json([
            'clientes' => $clientes->map(fn (Cliente $c) => [
                'id' => $c->id,
                'numero_cliente' => $c->numero_cliente,
                'nombre' => $c->nombre,
                'etiqueta' => trim($c->numero_cliente.' · '.$c->nombre),
            ])->values()->all(),
        ]);
    }

    public function exportarMetricas(Request $request, MetricasPeriodoEscalonamiento $metricas): StreamedResponse
    {
        $periodo = $this->periodoVista($request);
        if (! $periodo) {
            abort(404);
        }

        $datos = $metricas->paraPeriodo($periodo);
        $nombre = 'escalonamiento-metricas-'.$periodo->etiquetaMes().'.csv';

        return response()->streamDownload(function () use ($datos) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['clave', 'valor']);
            foreach ($datos as $clave => $valor) {
                if (is_array($valor)) {
                    $valor = json_encode($valor, JSON_UNESCAPED_UNICODE);
                }
                fputcsv($out, [$clave, $valor]);
            }
            fclose($out);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pendientes(Request $request): JsonResponse
    {
        return response()->json([
            'devoluciones' => $this->devolucionesPendientes($this->periodoAbierto()),
        ]);
    }

    public function candidatas(DocumentoVenta $documento, ListarCandidatasRemisionVinculada $listar): JsonResponse
    {
        try {
            $candidatas = $listar->listar($documento);
        } catch (VinculoDevolucionException $excepcion) {
            return response()->json(['message' => $excepcion->getMessage()], 422);
        }

        return response()->json([
            'devolucion' => [
                'id' => $documento->id,
                'folio' => $documento->folio,
                'total' => (string) $documento->total,
                'fecha_emision' => $documento->fecha_emision?->toDateString(),
                'remision_original' => $documento->remision_original,
            ],
            'candidatas' => $candidatas,
        ]);
    }

    public function vincular(Request $request, DocumentoVenta $documento, VincularDevolucionARemision $vincular): RedirectResponse
    {
        $datos = $request->validate([
            'remision_vinculada_id' => ['required', 'integer'],
            'evidencia' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $remision = DocumentoVenta::query()->find($datos['remision_vinculada_id']);
        if (! $remision) {
            return back()->with('error', 'No se encontró la remisión seleccionada.');
        }

        try {
            $vincular->vincular($documento, $remision, $datos['evidencia'], $request->user());
        } catch (PeriodoNoAbiertoException|VinculoDevolucionException|CapacidadRemisionException|AcumuladoNegativoException $excepcion) {
            return back()->with('error', $excepcion->getMessage());
        }

        return redirect()
            ->route('escalonamiento.index')
            ->with('success', 'Devolución vinculada. El acumulado descuenta ese importe una sola vez.');
    }

    public function revertir(Request $request, EscalonamientoAplicacionDevolucion $aplicacion, RevertirAplicacionDevolucion $revertir): RedirectResponse
    {
        try {
            $revertir->revertir($aplicacion, $request->user());
        } catch (PeriodoNoAbiertoException|VinculoDevolucionException|AcumuladoNegativoException $excepcion) {
            return back()->with('error', $excepcion->getMessage());
        }

        return redirect()
            ->route('escalonamiento.index')
            ->with('success', 'Vínculo revertido. La devolución volvió a quedar pendiente y la capacidad de la remisión quedó libre.');
    }

    public function resolverIncidencia(Request $request, EscalonamientoIncidencia $incidencia): RedirectResponse
    {
        $datos = $request->validate([
            'resolucion' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        if ($incidencia->estado !== 'abierta') {
            return back()->with('error', 'La incidencia ya estaba resuelta.');
        }

        $incidencia->estado = 'resuelta';
        $incidencia->resolucion = trim($datos['resolucion']);
        $incidencia->resuelto_en = now();
        $incidencia->resuelto_por_user_id = $request->user()?->id;
        $incidencia->save();

        return redirect()
            ->route('escalonamiento.index')
            ->with('success', 'Incidencia resuelta.');
    }

    public function previsualizar(Request $request, ImportarDocumentosEscalonamiento $importar): RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'max:10240'],
            'tipo' => ['required', 'in:remision,devolucion'],
        ]);

        $periodo = $this->periodoAbierto();
        if (! $periodo) {
            return back()->withErrors(['archivo' => 'No hay un período abierto.']);
        }

        try {
            $importacion = $importar->previsualizar(
                $periodo,
                $request->file('archivo'),
                $request->user(),
                (string) $request->input('tipo'),
            );
        } catch (InvalidArgumentException|PeriodoNoAbiertoException $excepcion) {
            return back()->withErrors(['archivo' => $excepcion->getMessage()]);
        }

        return redirect()->route('escalonamiento.index', ['importacion' => $importacion->id]);
    }

    public function confirmar(Request $request, ImportarDocumentosEscalonamiento $importar): RedirectResponse
    {
        $datos = $request->validate([
            'importacion_id' => ['required', 'integer'],
        ]);

        try {
            $resultado = $importar->confirmar((int) $datos['importacion_id'], $request->user());
        } catch (PeriodoNoAbiertoException $excepcion) {
            return back()->with('error', $excepcion->getMessage());
        }

        return redirect()
            ->route('escalonamiento.index')
            ->with('success', $resultado['mensaje']);
    }

    public function capturar(Request $request, ImportarDocumentosEscalonamiento $importar): RedirectResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', 'in:remision,devolucion'],
            'folio' => ['required', 'string', 'max:100'],
            'serie' => ['nullable', 'string', 'max:50'],
            'sucursal' => ['nullable', 'string', 'max:50'],
            'numero_cliente' => ['required', 'string', 'max:50'],
            'moneda' => ['nullable', 'string', 'max:8'],
            'total' => ['required', 'numeric', 'gt:0'],
            'fecha_emision' => ['required', 'date'],
            'estado' => ['nullable', 'in:activo,cancelado'],
            'remision_original' => ['nullable', 'string', 'max:100'],
        ]);

        $periodo = $this->periodoAbierto();
        if (! $periodo) {
            return back()->with('error', 'No hay un período abierto.');
        }

        try {
            $resultado = $importar->capturar($periodo, $datos, $request->user());
        } catch (PeriodoNoAbiertoException $excepcion) {
            return back()->with('error', $excepcion->getMessage());
        }

        $texto = match ($resultado['resultado']) {
            'alta' => 'Remisión registrada en el acumulado.',
            'identico' => 'Ese documento ya estaba cargado. El acumulado no cambió.',
            'revision' => 'Se aplicó la diferencia del documento.',
            'pendiente' => 'Devolución guardada sin descontar el acumulado.',
            default => $resultado['motivo'] ?? 'La captura quedó como incidencia.',
        };

        return redirect()
            ->route('escalonamiento.index')
            ->with($resultado['resultado'] === 'incidencia' ? 'error' : 'success', $texto);
    }

    public function cierre(Request $request): Response
    {
        $periodo = $this->periodoParaCierre($request);

        $cierre = null;
        if ($periodo?->escalonamiento_cierre_vigente_id) {
            $cierre = EscalonamientoCierre::query()
                ->with(['detalles.cliente', 'detalles.listaSiguiente'])
                ->find($periodo->escalonamiento_cierre_vigente_id);
        }

        return Inertia::render('Escalonamiento/Cierre', [
            'periodo' => $periodo ? [
                'id' => $periodo->id,
                'anio' => $periodo->anio,
                'mes' => $periodo->mes,
                'estado' => $periodo->estado,
                'corte' => $periodo->fecha_corte?->toDateTimeString(),
            ] : null,
            'periodos' => $this->listarPeriodosResumen(),
            'cierre' => $cierre ? $this->serializarCierre($cierre) : null,
            'puedeOperar' => $request->user()?->can('escalonamiento.operar') ?? false,
            'puedeAutorizar' => $request->user()?->can('escalonamiento.autorizar') ?? false,
            'autoridad' => app(EscalonamientoAutoridadConfig::class)->estado(),
        ]);
    }

    public function actualizarAutoridad(Request $request, EscalonamientoAutoridadConfig $autoridadConfig): RedirectResponse
    {
        $validated = $request->validate([
            'autoridad_activa' => 'required|boolean',
            'periodo_oficial_id' => 'nullable|integer|exists:escalonamiento_periodos,id',
        ]);

        $autoridadConfig->guardar(
            (bool) $validated['autoridad_activa'],
            isset($validated['periodo_oficial_id']) ? (int) $validated['periodo_oficial_id'] : null,
        );

        $mensaje = $validated['autoridad_activa']
            ? 'Autoridad del módulo activada: monto y lista operativa se publican desde escalonamiento.'
            : 'Autoridad del módulo desactivada: coexistencia con importación y solicitudes (modo legado).';

        return back()->with('success', $mensaje);
    }

    public function simularCierre(Request $request, SimularCierreEscalonamiento $simular): RedirectResponse
    {
        $periodo = $this->periodoParaCierre($request);
        if (! $periodo) {
            return back()->with('error', 'No hay período disponible para cerrar.');
        }

        try {
            $resultado = $simular->simular($periodo, $request->user());
        } catch (CierreEscalonamientoException $excepcion) {
            return back()->with('error', $excepcion->getMessage());
        }

        $advertencias = $resultado['advertencias'];
        $mensaje = 'Simulación de cierre lista para revisión.';
        if ($advertencias !== []) {
            $mensaje .= ' Advertencias: '.implode(' ', array_slice($advertencias, 0, 3));
        }

        return redirect()
            ->route('escalonamiento.cierre', ['periodo_id' => $periodo->id])
            ->with('success', $mensaje);
    }

    public function autorizarCierre(Request $request, AutorizarCierreEscalonamiento $autorizar): RedirectResponse
    {
        $periodo = $this->periodoParaCierre($request);
        if (! $periodo) {
            return back()->with('error', 'No hay período disponible.');
        }

        try {
            $autorizar->autorizar($periodo, $request->user());
        } catch (CierreEscalonamientoException $excepcion) {
            return back()->with('error', $excepcion->getMessage());
        }

        return redirect()
            ->route('escalonamiento.cierre', ['periodo_id' => $periodo->id])
            ->with('success', 'Cierre autorizado. Ya puede aplicarse internamente.');
    }

    public function aplicarCierre(Request $request, AplicarCierreEscalonamiento $aplicar): RedirectResponse
    {
        $periodo = $this->periodoParaCierre($request);
        if (! $periodo) {
            return back()->with('error', 'No hay período disponible.');
        }

        try {
            $resultado = $aplicar->aplicar($periodo, $request->user());
        } catch (CierreEscalonamientoException $excepcion) {
            return back()->with('error', $excepcion->getMessage());
        }

        if ($resultado['reconstruccion']) {
            return redirect()
                ->route('escalonamiento.cierre', ['periodo_id' => $periodo->id])
                ->with('success', sprintf(
                    'Cierre de %04d-%02d aplicado. El período operativo no cambió y la lista base del mes siguiente quedó registrada.',
                    $periodo->anio,
                    $periodo->mes,
                ));
        }

        $siguiente = $resultado['periodo_siguiente'];

        return redirect()
            ->route('escalonamiento.index')
            ->with('success', $siguiente
                ? sprintf(
                    'Cierre aplicado. Período %04d-%02d abierto en acumulado cero.',
                    $siguiente->anio,
                    $siguiente->mes,
                )
                : 'Cierre aplicado.');
    }

    public function cancelarCierre(Request $request, CancelarSimulacionCierreEscalonamiento $cancelar): RedirectResponse
    {
        $periodo = $this->periodoParaCierre($request);
        if (! $periodo) {
            return back()->with('error', 'No hay período disponible.');
        }

        try {
            $cancelar->cancelar($periodo);
        } catch (CierreEscalonamientoException $excepcion) {
            return back()->with('error', $excepcion->getMessage());
        }

        $periodo->refresh();

        return redirect()
            ->route('escalonamiento.cierre', ['periodo_id' => $periodo->id])
            ->with('success', $periodo->estado === EscalonamientoPeriodo::ESTADO_HISTORIAL
                ? 'Simulación cancelada. El período volvió a historial.'
                : 'Simulación cancelada. El período volvió a abierto.');
    }

    public function descargarReporte(EscalonamientoCierre $cierre): StreamedResponse
    {
        if (! $cierre->reporte_ruta || ! Storage::disk('local')->exists($cierre->reporte_ruta)) {
            abort(404);
        }

        return Storage::disk('local')->download($cierre->reporte_ruta);
    }

    public function registrarAplicacionExterna(
        Request $request,
        EscalonamientoCierre $cierre,
        RegistrarAplicacionExternaErp $registrar,
    ): RedirectResponse {
        $datos = $request->validate([
            'evidencia' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        try {
            $registrar->registrar($cierre, $datos['evidencia']);
        } catch (CierreEscalonamientoException $excepcion) {
            return back()->with('error', $excepcion->getMessage());
        }

        return back()->with('success', 'Aplicación manual en ERP registrada.');
    }

    public function abrir(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $this->abrirPeriodo->abrir((int) $datos['anio'], (int) $datos['mes']);

        return redirect()
            ->route('escalonamiento.index')
            ->with('success', 'Período abierto. El acumulado parte de los documentos, sin copiar el monto de venta del cliente.');
    }

    public function abrirHistorico(Request $request, AsegurarPeriodoHistoricoEscalonamiento $asegurar): RedirectResponse
    {
        $datos = $request->validate([
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        try {
            $periodo = $asegurar->asegurar((int) $datos['anio'], (int) $datos['mes']);
        } catch (InvalidArgumentException $excepcion) {
            return back()->with('error', $excepcion->getMessage());
        }

        return redirect()
            ->route('escalonamiento.index', ['periodo_id' => $periodo->id])
            ->with('success', 'Período histórico '.$periodo->etiquetaMes().' listo para carga retroactiva.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function consultaPorNumero(Request $request, ?EscalonamientoPeriodo $periodo): ?array
    {
        $numero = trim((string) $request->get('numero', ''));
        if ($numero === '') {
            return null;
        }

        $cliente = Cliente::query()->where('numero_cliente', $numero)->first();
        if (! $cliente) {
            return [
                'no_encontrado' => true,
                'numero_cliente' => $numero,
            ];
        }

        return $this->consultarAcumulado->consultar($cliente, $periodo);
    }

    private function periodoAbierto(): ?EscalonamientoPeriodo
    {
        return EscalonamientoPeriodo::query()
            ->where('estado', EscalonamientoPeriodo::ESTADO_ABIERTO)
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->first();
    }

    private function periodoVista(Request $request): ?EscalonamientoPeriodo
    {
        $id = (int) $request->get('periodo_id', 0);
        if ($id > 0) {
            return EscalonamientoPeriodo::query()->find($id);
        }

        return $this->periodoAbierto()
            ?? EscalonamientoPeriodo::query()
                ->orderByDesc('anio')
                ->orderByDesc('mes')
                ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarPeriodosResumen(): array
    {
        return EscalonamientoPeriodo::query()
            ->withCount(['documentos', 'resumenes'])
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->limit(36)
            ->get()
            ->map(fn (EscalonamientoPeriodo $periodo) => [
                'id' => $periodo->id,
                'anio' => $periodo->anio,
                'mes' => $periodo->mes,
                'estado' => $periodo->estado,
                'etiqueta' => $periodo->etiquetaMes(),
                'documentos' => $periodo->documentos_count,
                'resumenes' => $periodo->resumenes_count,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function historialMesesCliente(Cliente $cliente): array
    {
        return EscalonamientoResumenCliente::query()
            ->with(['periodo:id,anio,mes,estado', 'clasificacionMes:id,nombre', 'listaVigente:id,nombre'])
            ->where('cliente_id', $cliente->id)
            ->whereHas('periodo')
            ->get()
            ->sortByDesc(fn (EscalonamientoResumenCliente $r) => $r->periodo?->anio * 100 + $r->periodo?->mes)
            ->values()
            ->map(fn (EscalonamientoResumenCliente $resumen) => [
                'periodo_id' => $resumen->escalonamiento_periodo_id,
                'etiqueta' => $resumen->periodo?->etiquetaMes(),
                'estado' => $resumen->periodo?->estado,
                'acumulado' => (string) $resumen->acumulado,
                'clasificacion_mes' => $resumen->clasificacionMes?->nombre,
                'lista_vigente' => $resumen->listaVigente?->nombre,
            ])
            ->all();
    }

    private function periodoParaCierre(Request $request): ?EscalonamientoPeriodo
    {
        $id = (int) $request->input('periodo_id', 0);
        if ($id > 0) {
            return EscalonamientoPeriodo::query()->find($id);
        }

        return EscalonamientoPeriodo::query()
            ->whereIn('estado', [
                EscalonamientoPeriodo::ESTADO_ABIERTO,
                EscalonamientoPeriodo::ESTADO_EN_REVISION,
                EscalonamientoPeriodo::ESTADO_AUTORIZADO,
                EscalonamientoPeriodo::ESTADO_APLICACION_PENDIENTE,
            ])
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->first()
            ?? EscalonamientoPeriodo::query()
                ->where('estado', EscalonamientoPeriodo::ESTADO_HISTORIAL)
                ->orderByDesc('anio')
                ->orderByDesc('mes')
                ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarCierre(EscalonamientoCierre $cierre): array
    {
        return [
            'id' => $cierre->id,
            'version' => $cierre->version,
            'estado' => $cierre->estado,
            'reconstruccion' => (bool) $cierre->reconstruccion,
            'hash_snapshot' => $cierre->hash_snapshot,
            'simulado_en' => $cierre->simulado_en?->toDateTimeString(),
            'autorizado_en' => $cierre->autorizado_en?->toDateTimeString(),
            'aplicado_en' => $cierre->aplicado_en?->toDateTimeString(),
            'reporte_disponible' => $cierre->reporte_ruta && Storage::disk('local')->exists($cierre->reporte_ruta),
            'aplicacion_externa_declarada' => (bool) $cierre->aplicacion_externa_declarada,
            'detalles' => $cierre->detalles->map(fn ($detalle) => [
                'cliente_id' => $detalle->cliente_id,
                'numero_cliente' => $detalle->cliente?->numero_cliente,
                'nombre' => $detalle->cliente?->nombre,
                'neto' => (string) $detalle->neto,
                'lista_siguiente' => $detalle->listaSiguiente?->nombre,
                'motivo' => $detalle->motivo,
                'aplica_cambio_lista' => (bool) $detalle->aplica_cambio_lista,
                'propone_inactivo' => (bool) $detalle->propone_inactivo,
                'meses_sin_compra' => $detalle->meses_sin_compra,
            ])->values()->all(),
        ];
    }

    private function periodoParaConciliacion(): ?EscalonamientoPeriodo
    {
        return EscalonamientoPeriodo::query()
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function incidencias(
        Request $request,
        ?EscalonamientoPeriodo $periodo,
        bool $accionables = true,
        bool $soloExclusion = false,
    ): array {
        if (! $periodo) {
            return [];
        }

        $tipo = trim((string) $request->get('tipo_incidencia', ''));
        $estado = trim((string) $request->get('estado_incidencia', 'abierta'));
        $gravedad = trim((string) $request->get('gravedad_incidencia', ''));
        $clienteId = (int) $request->get('cliente_id', 0);

        return EscalonamientoIncidencia::query()
            ->with('cliente:id,numero_cliente,nombre')
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->when($clienteId > 0, fn ($q) => $q->where('cliente_id', $clienteId))
            ->when($soloExclusion, fn ($q) => $q->where('codigo', 'exclusion_lealtad'))
            ->when($accionables && ! $soloExclusion, fn ($q) => $q->where('codigo', '!=', 'exclusion_lealtad'))
            ->when($estado !== '' && $estado !== 'todas', fn ($q) => $q->where('estado', $estado))
            ->when($tipo !== '', fn ($q) => $q->where('codigo', $tipo))
            ->when($gravedad !== '', fn ($q) => $q->where('gravedad', $gravedad))
            ->orderByRaw("case gravedad when 'bloquea' then 0 when 'aviso' then 1 else 2 end")
            ->orderBy('created_at')
            ->limit(80)
            ->get()
            ->map(fn (EscalonamientoIncidencia $incidencia) => [
                'id' => $incidencia->id,
                'gravedad' => $incidencia->gravedad,
                'codigo' => $incidencia->codigo,
                'tipo_legible' => $this->etiquetaTipoIncidencia($incidencia->codigo),
                'estado' => $incidencia->estado,
                'motivo' => $incidencia->motivo,
                'numero_cliente' => $incidencia->cliente?->numero_cliente,
                'nombre' => $incidencia->cliente?->nombre,
                'cliente_id' => $incidencia->cliente_id,
                'es_informativa' => $incidencia->codigo === 'exclusion_lealtad',
            ])
            ->all();
    }

    /**
     * @return array{tipos: list<array{codigo: string, etiqueta: string}>, gravedades: list<string>}
     */
    private function opcionesFiltroIncidencias(?EscalonamientoPeriodo $periodo): array
    {
        if (! $periodo) {
            return ['tipos' => [], 'gravedades' => []];
        }

        $codigos = EscalonamientoIncidencia::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('codigo', '!=', 'exclusion_lealtad')
            ->distinct()
            ->orderBy('codigo')
            ->pluck('codigo');

        return [
            'tipos' => $codigos->map(fn (string $codigo) => [
                'codigo' => $codigo,
                'etiqueta' => $this->etiquetaTipoIncidencia($codigo),
            ])->values()->all(),
            'gravedades' => EscalonamientoIncidencia::query()
                ->where('escalonamiento_periodo_id', $periodo->id)
                ->distinct()
                ->orderBy('gravedad')
                ->pluck('gravedad')
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<string>
     */
    private function resultadoFiltroDesdeRequest(Request $request): array
    {
        $raw = $request->get('resultado');
        if (is_string($raw) && $raw !== '') {
            return array_values(array_filter(explode(',', $raw)));
        }
        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw)));
        }

        return [];
    }

    /**
     * @return array{id: int, numero_cliente: string, nombre: string, etiqueta: string}|null
     */
    private function etiquetaClienteFiltro(int $clienteId): ?array
    {
        if ($clienteId <= 0) {
            return null;
        }

        $cliente = Cliente::query()->find($clienteId, ['id', 'numero_cliente', 'nombre']);
        if (! $cliente) {
            return null;
        }

        return [
            'id' => $cliente->id,
            'numero_cliente' => $cliente->numero_cliente,
            'nombre' => $cliente->nombre,
            'etiqueta' => trim($cliente->numero_cliente.' · '.$cliente->nombre),
        ];
    }

    private function etiquetaTipoIncidencia(?string $codigo): string
    {
        return match ($codigo) {
            'cliente_no_identificado' => 'Cliente no identificado',
            'fila_invalida' => 'Fila inválida',
            'fecha_futura' => 'Fecha futura',
            'documento_anticipado' => 'Documento anticipado',
            'documento_tardio' => 'Documento fuera de período',
            'datos_incompatibles' => 'Datos incompatibles',
            'moneda_no_soportada' => 'Moneda no soportada',
            'acumulado_negativo' => 'Acumulado negativo',
            'pendiente_vinculacion' => 'Pendiente de vínculo',
            'exclusion_lealtad' => 'Exclusión de lealtad',
            'divergencia_lista' => 'Divergencia de lista',
            default => $codigo ? str_replace('_', ' ', $codigo) : 'Incidencia',
        };
    }

    private function conteoCambiosPropuestos(EscalonamientoPeriodo $periodo): int
    {
        $cierre = $periodo->cierreVigente;
        if ($cierre && $cierre->detalles()->where('aplica_cambio_lista', true)->exists()) {
            return $cierre->detalles()->where('aplica_cambio_lista', true)->count();
        }

        return EscalonamientoResumenCliente::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->whereHas('cliente', function ($q) {
                $q->whereColumn(
                    'clientes.lista_actual_id',
                    '!=',
                    'escalonamiento_resumenes_cliente.lista_vigente_id',
                );
            })
            ->count();
    }

    /**
     * @param  array<string, mixed>  $metricasPeriodo
     */
    private function mensajeCoberturaDocumentos(EscalonamientoPeriodo $periodo, array $metricasPeriodo): ?string
    {
        $documentos = (int) ($metricasPeriodo['documentos_total'] ?? 0);
        $compras = (float) ($metricasPeriodo['compras'] ?? 0);

        if ($documentos > 0 && $compras <= 0) {
            return 'Hay documentos registrados, pero no aportan al acumulado de escalonamiento. Consulta las exclusiones y la configuración de listas.';
        }

        if ($documentos === 0 && $periodo->estaAbierto()) {
            return 'Sin documentos en el período. El resumen aparece cuando se registran remisiones.';
        }

        return null;
    }

    /**
     * @return array{data: list<array<string, mixed>>, paginacion: array<string, mixed>}
     */
    private function documentosPeriodo(?EscalonamientoPeriodo $periodo, Request $request): array
    {
        $vacio = [
            'data' => [],
            'paginacion' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 25,
                'total' => 0,
                'from' => null,
                'to' => null,
            ],
        ];

        if (! $periodo || (string) $request->get('tab', 'clientes') !== 'documentos') {
            return $vacio;
        }

        $busqueda = trim((string) $request->get('q_doc', ''));
        $clienteId = (int) $request->get('cliente_id', 0);
        $consulta = DocumentoVenta::query()
            ->with('cliente:id,numero_cliente,nombre')
            ->withExists(['movimiento as aporta_acumulado' => fn ($q) => $q->where('efecto', '>', 0)])
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->when($clienteId > 0, fn ($q) => $q->where('cliente_id', $clienteId))
            ->when($busqueda !== '', function ($q) use ($busqueda) {
                $q->where(function ($filtro) use ($busqueda) {
                    $filtro->where('folio', 'like', '%'.$busqueda.'%')
                        ->orWhereHas('cliente', function ($cliente) use ($busqueda) {
                            $cliente->where('numero_cliente', 'like', '%'.$busqueda.'%')
                                ->orWhere('nombre', 'like', '%'.$busqueda.'%');
                        });
                });
            })
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id');

        $pagina = $consulta->paginate(25, ['*'], 'doc_page')->withQueryString();

        return [
            'data' => $pagina->getCollection()->map(fn (DocumentoVenta $documento) => [
                'id' => $documento->id,
                'folio' => $documento->folio,
                'tipo' => $documento->tipo,
                'fecha' => $documento->fecha_emision?->toDateString(),
                'sucursal' => $documento->sucursal,
                'moneda' => $documento->moneda,
                'total' => (string) $documento->total,
                'estado' => $documento->estado,
                'origen' => $documento->origen,
                'numero_cliente' => $documento->cliente?->numero_cliente,
                'nombre' => $documento->cliente?->nombre,
                'aporta_acumulado' => (bool) $documento->aporta_acumulado,
            ])->all(),
            'paginacion' => [
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
                'per_page' => $pagina->perPage(),
                'total' => $pagina->total(),
                'from' => $pagina->firstItem(),
                'to' => $pagina->lastItem(),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function devolucionesPendientes(?EscalonamientoPeriodo $periodo): array
    {
        if (! $periodo) {
            return [];
        }

        return DocumentoVenta::query()
            ->with('cliente:id,numero_cliente,nombre')
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('tipo', 'devolucion')
            ->where('estado', 'pendiente_de_vinculacion')
            ->orderBy('fecha_emision')
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(fn (DocumentoVenta $documento) => [
                'id' => $documento->id,
                'folio' => $documento->folio,
                'total' => (string) $documento->total,
                'fecha_emision' => $documento->fecha_emision?->toDateString(),
                'remision_original' => $documento->remision_original,
                'numero_cliente' => $documento->cliente?->numero_cliente,
                'nombre' => $documento->cliente?->nombre,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function aplicacionesActivas(?EscalonamientoPeriodo $periodo): array
    {
        if (! $periodo) {
            return [];
        }

        return EscalonamientoAplicacionDevolucion::query()
            ->with([
                'devolucion:id,folio,total,cliente_id',
                'devolucion.cliente:id,numero_cliente,nombre',
                'remisionVinculada:id,folio,total',
            ])
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('estado', EscalonamientoAplicacionDevolucion::ESTADO_ACTIVA)
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (EscalonamientoAplicacionDevolucion $aplicacion) => [
                'id' => $aplicacion->id,
                'importe' => (string) $aplicacion->importe,
                'evidencia' => $aplicacion->evidencia,
                'folio_devolucion' => $aplicacion->devolucion?->folio,
                'folio_remision' => $aplicacion->remisionVinculada?->folio,
                'numero_cliente' => $aplicacion->devolucion?->cliente?->numero_cliente,
                'nombre' => $aplicacion->devolucion?->cliente?->nombre,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function previsualizacion(Request $request, ?EscalonamientoPeriodo $periodo): ?array
    {
        $importacionId = (int) $request->get('importacion', 0);
        if ($importacionId === 0 || ! $periodo || ! $request->user()?->can('escalonamiento.operar')) {
            return null;
        }

        $importacion = EscalonamientoImportacion::query()
            ->with('filas')
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('estado', 'previsualizada')
            ->find($importacionId);

        if (! $importacion) {
            return null;
        }

        $conteo = $importacion->filas->countBy('resultado')->all();
        $aplicables = (int) ($conteo['alta'] ?? 0)
            + (int) ($conteo['revision'] ?? 0)
            + (int) ($conteo['pendiente'] ?? 0)
            + (int) ($conteo['identico'] ?? 0)
            + (int) ($conteo['excluido'] ?? 0);

        $historial = (int) ($conteo['historial_alta'] ?? 0)
            + (int) ($conteo['historial_identico'] ?? 0)
            + (int) ($conteo['historial_revision'] ?? 0)
            + (int) ($conteo['historial_pendiente'] ?? 0)
            + (int) ($conteo['historial_excluido'] ?? 0);

        $futuro = (int) ($conteo['futuro_alta'] ?? 0)
            + (int) ($conteo['futuro_identico'] ?? 0)
            + (int) ($conteo['futuro_revision'] ?? 0)
            + (int) ($conteo['futuro_pendiente'] ?? 0)
            + (int) ($conteo['futuro_excluido'] ?? 0);

        $confirmables = $aplicables + $historial + $futuro;

        $fechas = $importacion->filas
            ->map(fn ($fila) => is_array($fila->interpretacion) ? ($fila->interpretacion['fecha_emision'] ?? null) : null)
            ->filter()
            ->values();

        $alertaPeriodo = null;
        if ($aplicables === 0 && ($historial > 0 || $futuro > 0)) {
            $partes = [];
            if ($historial > 0) {
                $partes[] = $historial.' fila(s) se registrarán en meses anteriores (historial)';
            }
            if ($futuro > 0) {
                $partes[] = $futuro.' fila(s) se registrarán en un período futuro pendiente de revisión administrativa';
            }
            $alertaPeriodo = [
                'filas_fuera' => $historial + $futuro,
                'mensaje' => implode('. ', $partes).'.',
                'informativa' => true,
                'rango_fechas' => $fechas->isEmpty() ? null : [
                    'min' => $fechas->min(),
                    'max' => $fechas->max(),
                ],
            ];
        } elseif ($aplicables === 0 && (int) ($conteo['incidencia'] ?? 0) > 0) {
            $alertaPeriodo = [
                'filas_fuera' => (int) ($conteo['incidencia'] ?? 0),
                'mensaje' => sprintf(
                    'No hay documentos aplicables para confirmar en %s. Revisa las incidencias del archivo.',
                    $periodo->etiquetaMes(),
                ),
                'informativa' => false,
                'rango_fechas' => $fechas->isEmpty() ? null : [
                    'min' => $fechas->min(),
                    'max' => $fechas->max(),
                ],
            ];
        }

        $soloIdenticos = $aplicables > 0
            && (int) ($conteo['alta'] ?? 0) === 0
            && (int) ($conteo['revision'] ?? 0) === 0
            && (int) ($conteo['pendiente'] ?? 0) === 0
            && (int) ($conteo['excluido'] ?? 0) === 0
            && (int) ($conteo['identico'] ?? 0) === $aplicables;

        return [
            'id' => $importacion->id,
            'nombre' => $importacion->nombre_archivo,
            'tipo_documento' => $importacion->tipo_documento,
            'conteo' => $conteo,
            'resumen' => [
                'leidas' => $importacion->filas->count(),
                'aplicables' => $aplicables,
                'confirmables' => $confirmables,
                'nuevas' => (int) ($conteo['alta'] ?? 0),
                'sin_cambio' => (int) ($conteo['identico'] ?? 0),
                'revision' => (int) ($conteo['revision'] ?? 0),
                'pendiente' => (int) ($conteo['pendiente'] ?? 0),
                'excluido' => (int) ($conteo['excluido'] ?? 0),
                'historial' => $historial,
                'futuro' => $futuro,
                'incidencia' => (int) ($conteo['incidencia'] ?? 0),
            ],
            'puede_confirmar' => $confirmables > 0,
            'motivo_confirmacion' => $confirmables === 0
                ? 'No hay documentos aplicables para confirmar en este período.'
                : ($soloIdenticos && $historial === 0 && $futuro === 0 ? 'Archivo ya registrado; sin cambios en ventas.' : null),
            'etiqueta_confirmar' => $confirmables > 0
                ? 'Confirmar '.$confirmables.' filas'
                : 'Confirmar carga',
            'alerta_periodo' => $alertaPeriodo,
            'filas' => $importacion->filas->map(function ($fila) {
                $datos = is_array($fila->interpretacion) ? $fila->interpretacion : [];

                return [
                    'numero_fila' => $fila->numero_fila,
                    'resultado' => $fila->resultado,
                    'motivo' => $fila->motivo,
                    'tipo' => $datos['tipo'] ?? '',
                    'folio' => $datos['folio'] ?? '',
                    'fecha' => $datos['fecha_emision'] ?? null,
                    'sucursal' => $datos['sucursal'] ?? null,
                    'numero_cliente' => $datos['numero_cliente'] ?? '',
                    'nombre' => $datos['nombre'] ?? '',
                    'total' => $datos['total'],
                    'periodo_destino' => $datos['periodo_destino'] ?? null,
                ];
            })->all(),
        ];
    }
}
