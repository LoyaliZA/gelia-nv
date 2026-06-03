<?php

namespace App\Http\Controllers\Activos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Activos\AsignarActivoRequest;
use App\Http\Requests\Activos\CambiarEstadoActivoRequest;
use App\Http\Requests\Activos\ProgramarMantenimientoRequest;
use App\Http\Requests\Activos\StoreActivoRequest;
use App\Http\Requests\Activos\TransferirActivoRequest;
use App\Http\Requests\Activos\UpdateActivoRequest;
use App\Models\Activo;
use App\Models\ActivoAsignacion;
use App\Models\ActivoFoto;
use App\Models\ActivoMantenimiento;
use App\Models\CatalogoTipoActivo;
use App\Models\CatalogoCategoriaActivo;
use App\Models\Departamento;
use App\Models\User;
use App\Models\ActivoConfiguracion;
use Illuminate\Support\Facades\Storage;
use App\Services\Activos\ActualizarActivoService;
use App\Services\Activos\AlertasActivosService;
use App\Services\Activos\AsignarActivoService;
use App\Services\Activos\BuscarMarcasModelosActivoService;
use App\Services\Activos\BuscarActivosService;
use App\Services\Activos\BuscarUsuariosActivoService;
use App\Services\Activos\GenerarResponsivaService;
use App\Services\Activos\GenerarEtiquetasActivosService;
use App\Services\Activos\ResolverDimensionesEtiquetaService;
use App\Services\Activos\CambiarEstadoActivoService;
use App\Services\Activos\CompletarMantenimientoActivoService;
use App\Services\Activos\CrearActivoService;
use App\Services\Activos\DevolverActivoService;
use App\Services\Activos\EliminarFotoActivoService;
use App\Services\Activos\ExportarActivosService;
use App\Services\Activos\ListarActivosService;
use App\Services\Activos\PresentarConsultaPublicaActivoService;
use App\Services\Activos\ProgramarMantenimientoActivoService;
use App\Services\Activos\SubirFotosActivoService;
use App\Support\EtiquetaActivoAssets;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Rap2hpoutre\FastExcel\FastExcel;

class ActivoController extends Controller
{
    public function index(Request $request, ListarActivosService $listarService, AlertasActivosService $alertasService): Response
    {
        $filtros = $request->only([
            'busqueda', 'catalogo_tipo_activo_id', 'catalogo_categoria_activo_id', 'departamento_id',
            'estado', 'responsable_user_id', 'mis_activos', 'sin_asignar', 'en_mantenimiento', 'pendientes_firma',
        ]);

        $alertas = $alertasService->ejecutar(Auth::user());

        $userId = null;
        if (!empty($filtros['mis_activos'])) {
            $userId = Auth::id();
        } elseif (!empty($filtros['responsable_user_id'])) {
            $userId = (int) $filtros['responsable_user_id'];
        }

        $colaboradorAsignaciones = [];
        if ($userId) {
            $colaboradorAsignaciones = ActivoAsignacion::where('user_id', $userId)
                ->where('activa', true)
                ->with(['activo.tipo'])
                ->get()
                ->map(function ($asig) {
                    return [
                        'id' => $asig->id,
                        'activo_id' => $asig->activo_id,
                        'folio' => $asig->activo->folio,
                        'nombre' => $asig->activo->nombre,
                        'tipo' => $asig->activo->tipo?->nombre,
                        'firmado' => $asig->firmado,
                        'fecha_inicio' => $asig->fecha_inicio ? $asig->fecha_inicio->format('Y-m-d') : null,
                        'condiciones_entrega' => $asig->condiciones_entrega,
                    ];
                });
        }

        return Inertia::render('Activos/Index', [
            'activos' => $listarService->ejecutar(Auth::user(), $filtros),
            'tipos' => CatalogoTipoActivo::where('activo', true)->orderBy('nombre')->get(),
            'categorias' => CatalogoCategoriaActivo::where('activo', true)->orderBy('nombre')->get(),
            'departamentos' => Departamento::where('activo', true)->orderBy('nombre')->get(),
            'usuarios' => User::select(['id', 'name', 'email'])->orderBy('name')->get(),
            'filtros' => $filtros,
            'colaboradorAsignaciones' => $colaboradorAsignaciones,
            'alertasResumen' => [
                'vencidos' => count($alertas['vencidos']),
                'proximos_7' => count($alertas['proximos_7']),
                'proximos_30' => count($alertas['proximos_30']),
                'mantenimiento' => count($alertas['mantenimiento']),
            ],
            'alertas' => $alertas,
            'terminosCondiciones' => ActivoConfiguracion::obtenerTerminos(),
        ]);
    }

    public function show(Activo $activo): Response
    {
        $this->autorizarAccesoActivo($activo);

        $activo->load([
            'tipo',
            'categoria',
            'padre.tipo',
            'accesorios.tipo',
            'departamento',
            'area',
            'responsable',
            'registradoPor',
            'fotos',
            'asignaciones.usuario',
            'asignaciones.asignadoPor',
            'mantenimientos.usuario',
            'mantenimientoActivo.usuario',
            'movimientos.usuario',
            'movimientos.userDestino',
            'movimientos.departamentoOrigen',
            'movimientos.departamentoDestino',
        ]);

        return Inertia::render('Activos/Show', [
            'activo' => $activo,
            'tipos' => CatalogoTipoActivo::where('activo', true)->orderBy('nombre')->get(),
            'categorias' => CatalogoCategoriaActivo::where('activo', true)->orderBy('nombre')->get(),
            'departamentos' => Departamento::where('activo', true)->with('areas')->orderBy('nombre')->get(),
            'terminosCondiciones' => ActivoConfiguracion::obtenerTerminos(),
        ]);
    }

    public function qr(Activo $activo)
    {
        $this->autorizarAccesoActivo($activo);

        return $this->respuestaQrConsultaPublica($activo);
    }

    public function qrPng(Activo $activo)
    {
        $this->autorizarAccesoActivo($activo);

        return $this->respuestaQrConsultaPublica($activo, 'png', 320);
    }

    public function resolverCodigo(Request $request)
    {
        $codigo = trim((string) $request->input('codigo', ''));
        if ($codigo === '') {
            return response()->json(['error' => 'Código vacío.'], 422);
        }

        // Buscar un UUID dentro del código escaneado (útil si escanean la URL completa del QR)
        if (preg_match('/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i', $codigo, $coincidencias)) {
            $token = $coincidencias[0];
            $activo = Activo::where('consulta_token', $token)->first();
            if ($activo && $activo->estado !== 'baja') {
                $this->autorizarAccesoActivo($activo);

                return response()->json([
                    'id' => $activo->id,
                    'folio' => $activo->folio,
                    'nombre' => $activo->nombre,
                ]);
            }
        }

        $activo = $this->buscarActivoPorCodigoEscaneado($codigo);

        if (!$activo) {
            return response()->json(['error' => 'No se encontró un activo con ese código.'], 404);
        }

        $this->autorizarAccesoActivo($activo);

        return response()->json([
            'id' => $activo->id,
            'folio' => $activo->folio,
            'nombre' => $activo->nombre,
        ]);
    }

    public function consultaPublica(string $token, PresentarConsultaPublicaActivoService $service): Response
    {
        $activo = $this->resolverActivoConsultaPublica($token);

        return Inertia::render('Activos/ConsultaPublica', [
            'activo' => $service->ejecutar($activo),
            'puedeEditar' => Auth::check() && Auth::user()->can('activos.editar'),
            'urlEditar' => Auth::check() && Auth::user()->can('activos.editar')
                ? route('activos.show', $activo->id)
                : null,
        ]);
    }

    public function consultaQr(string $token)
    {
        $activo = $this->resolverActivoConsultaPublica($token);

        return $this->respuestaQrConsultaPublica($activo);
    }

    public function store(
        StoreActivoRequest $request,
        CrearActivoService $crearService,
        SubirFotosActivoService $fotosService,
        AsignarActivoService $asignarService,
    ) {
        $datos = $request->validated();
        $userIdAsignacion = $datos['user_id'] ?? null;
        $notasAsignacion = $datos['notas'] ?? null;
        $condicionesEntrega = $datos['condiciones_entrega'] ?? null;
        unset($datos['user_id'], $datos['notas'], $datos['condiciones_entrega']);

        $activo = $crearService->ejecutar(Auth::user(), $datos);

        if ($request->hasFile('fotos')) {
            $fotosService->ejecutar($activo, $request->file('fotos'));
        }

        if ($userIdAsignacion) {
            $activo = $asignarService->ejecutar($activo, Auth::user(), (int) $userIdAsignacion, $notasAsignacion, $condicionesEntrega);
        } elseif ($activo->activo_padre_id) {
            $padre = Activo::find($activo->activo_padre_id);
            if ($padre?->responsable_user_id) {
                $activo = $asignarService->ejecutar(
                    $activo,
                    Auth::user(),
                    (int) $padre->responsable_user_id,
                    $notasAsignacion,
                    $condicionesEntrega,
                );
            }
        }

        $mensaje = $userIdAsignacion
            ? 'Activo registrado y asignado correctamente.'
            : 'Activo registrado correctamente.';

        if ($request->boolean('registro_continuo')) {
            return back()->with([
                'success' => $mensaje,
                'activo_registrado' => [
                    'id' => $activo->id,
                    'folio' => $activo->folio,
                ],
            ]);
        }

        return redirect()->route('activos.show', $activo)->with('success', $mensaje);
    }

    public function update(UpdateActivoRequest $request, Activo $activo, ActualizarActivoService $service, SubirFotosActivoService $fotosService)
    {
        $this->autorizarAccesoActivo($activo);
        $service->ejecutar($activo, Auth::user(), $request->validated());

        if ($request->hasFile('fotos')) {
            $fotosService->ejecutar($activo, $request->file('fotos'));
        }

        return back()->with('success', 'Activo actualizado correctamente.');
    }

    public function asignar(AsignarActivoRequest $request, Activo $activo, AsignarActivoService $service)
    {
        $this->autorizarAccesoActivo($activo);
        $service->ejecutar(
            $activo,
            Auth::user(),
            $request->validated('user_id'),
            $request->validated('notas'),
            $request->validated('condiciones_entrega')
        );

        return back()->with('success', 'Activo asignado correctamente.');
    }

    public function devolver(Request $request, Activo $activo, DevolverActivoService $service)
    {
        $this->autorizarAccesoActivo($activo);
        $request->validate([
            'notas' => 'nullable|string|max:1000',
            'condiciones_devolucion' => 'nullable|string|max:1000',
        ]);
        $service->ejecutar($activo, Auth::user(), $request->input('notas'), $request->input('condiciones_devolucion'));

        return back()->with('success', 'Activo devuelto correctamente.');
    }

    public function transferir(TransferirActivoRequest $request, Activo $activo, TransferirActivoService $service)
    {
        $this->autorizarAccesoActivo($activo);
        $service->ejecutar(
            $activo,
            Auth::user(),
            $request->validated('departamento_destino_id'),
            $request->validated('motivo'),
            $request->validated('notas'),
        );

        return back()->with('success', 'Activo transferido correctamente.');
    }

    public function cambiarEstado(CambiarEstadoActivoRequest $request, Activo $activo, CambiarEstadoActivoService $service)
    {
        $this->autorizarAccesoActivo($activo);
        $service->ejecutar(
            $activo,
            Auth::user(),
            $request->validated('estado'),
            $request->validated('motivo'),
            $request->validated('notas'),
        );

        return back()->with('success', 'Estado del activo actualizado.');
    }

    public function programarMantenimiento(ProgramarMantenimientoRequest $request, Activo $activo, ProgramarMantenimientoActivoService $service)
    {
        $this->autorizarAccesoActivo($activo);
        $service->ejecutar($activo, Auth::user(), $request->validated());

        return back()->with('success', 'Mantenimiento programado correctamente.');
    }

    public function completarMantenimiento(Request $request, Activo $activo, ActivoMantenimiento $mantenimiento, CompletarMantenimientoActivoService $service)
    {
        $this->autorizarAccesoActivo($activo);
        $request->validate(['notas' => 'nullable|string|max:1000']);
        $service->ejecutar($activo, $mantenimiento, Auth::user(), $request->input('notas'));

        return back()->with('success', 'Mantenimiento completado.');
    }

    public function subirFotos(Request $request, Activo $activo, SubirFotosActivoService $service)
    {
        $this->autorizarAccesoActivo($activo);
        $request->validate([
            'fotos' => 'required|array|max:5',
            'fotos.*' => 'image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);
        $service->ejecutar($activo, $request->file('fotos'));

        return back()->with('success', 'Fotografías subidas correctamente.');
    }

    public function eliminarFoto(Activo $activo, ActivoFoto $foto, EliminarFotoActivoService $service)
    {
        $this->autorizarAccesoActivo($activo);
        if ($foto->activo_id !== $activo->id) {
            abort(404);
        }
        $service->ejecutar($foto);

        return back()->with('success', 'Fotografía eliminada.');
    }

    public function alertas(AlertasActivosService $service)
    {
        return response()->json($service->ejecutar(Auth::user()));
    }

    public function exportar(Request $request, ExportarActivosService $service)
    {
        $filtros = $request->only([
            'busqueda', 'catalogo_tipo_activo_id', 'catalogo_categoria_activo_id', 'departamento_id',
            'estado', 'responsable_user_id', 'mis_activos', 'sin_asignar', 'en_mantenimiento', 'pendientes_firma',
        ]);

        $activos = $service->ejecutar(Auth::user(), $filtros);
        $nombreArchivo = 'activos-' . now()->format('Y-m-d-His') . '.xlsx';

        return (new FastExcel($service->filas($activos)))->download($nombreArchivo);
    }

    public function etiquetas(ListarActivosService $listarService, ResolverDimensionesEtiquetaService $dimensiones): Response
    {
        $this->authorize('activos.exportar');

        $filtros = $this->filtrosEtiquetas(request());
        $activos = $listarService->ejecutar(Auth::user(), $filtros, false);
        $layout = $dimensiones->resolver(
            request()->filled('ancho_mm') ? (float) request('ancho_mm') : null,
            request()->filled('alto_mm') ? (float) request('alto_mm') : null,
            $this->opcionesLayoutEtiquetas(request()),
        );

        $muestra = $activos->take(4)->map(fn (Activo $a) => [
            'id' => $a->id,
            'folio' => $a->folio,
            'nombre' => $a->nombre,
            'tipo' => $a->tipo?->nombre,
            'qr_url' => route('activos.qr_png', $a->id),
        ])->values()->all();

        return Inertia::render('Activos/Etiquetas', [
            'tipos' => CatalogoTipoActivo::where('activo', true)->orderBy('nombre')->get(),
            'categorias' => CatalogoCategoriaActivo::where('activo', true)->orderBy('nombre')->get(),
            'departamentos' => Departamento::where('activo', true)->orderBy('nombre')->get(),
            'usuarios' => User::select(['id', 'name', 'email'])->orderBy('name')->get(),
            'filtros' => $this->filtrosEtiquetasPublicos($filtros),
            'conteo' => $activos->count(),
            'muestra' => $muestra,
            'layout' => $layout,
            'max_etiquetas' => GenerarEtiquetasActivosService::MAX_ETIQUETAS,
            'tamanos_hoja' => EtiquetaActivoAssets::tamanosParaFrontend(),
        ]);
    }

    public function etiquetasContar(Request $request, ListarActivosService $listarService)
    {
        $this->authorize('activos.exportar');

        $activos = $listarService->ejecutar(Auth::user(), $this->filtrosEtiquetas($request), false);

        return response()->json([
            'total' => $activos->count(),
            'muestra' => $activos->take(4)->map(fn (Activo $a) => [
                'id' => $a->id,
                'folio' => $a->folio,
                'nombre' => $a->nombre,
                'tipo' => $a->tipo?->nombre,
                'qr_url' => route('activos.qr_png', $a->id),
            ])->values()->all(),
        ]);
    }

    public function etiquetasVistaPrevia(Request $request, ListarActivosService $listarService, GenerarEtiquetasActivosService $generarService)
    {
        $this->authorize('activos.exportar');

        $activos = $listarService->ejecutar(Auth::user(), $this->filtrosEtiquetas($request), false);
        $pdf = $generarService->ejecutar(
            $activos,
            $request->filled('ancho_mm') ? (float) $request->input('ancho_mm') : null,
            $request->filled('alto_mm') ? (float) $request->input('alto_mm') : null,
            $this->opcionesLayoutEtiquetas($request),
        );

        return $pdf->stream('etiquetas-activos-vista-previa.pdf');
    }

    public function etiquetasDescargar(Request $request, ListarActivosService $listarService, GenerarEtiquetasActivosService $generarService)
    {
        $this->authorize('activos.exportar');

        $activos = $listarService->ejecutar(Auth::user(), $this->filtrosEtiquetas($request), false);
        $pdf = $generarService->ejecutar(
            $activos,
            $request->filled('ancho_mm') ? (float) $request->input('ancho_mm') : null,
            $request->filled('alto_mm') ? (float) $request->input('alto_mm') : null,
            $this->opcionesLayoutEtiquetas($request),
        );

        return $pdf->download('etiquetas-activos-' . now()->format('Y-m-d-His') . '.pdf');
    }

    public function buscarUsuarios(Request $request, BuscarUsuariosActivoService $service)
    {
        return response()->json(
            $service->ejecutar(
                $request->input('q'),
                $request->integer('departamento_id') ?: null,
            )
        );
    }

    public function buscarMarcas(Request $request, BuscarMarcasModelosActivoService $service)
    {
        return response()->json(
            $service->marcas(
                $request->integer('tipo_id') ?: null,
                $request->input('q'),
            )
        );
    }

    public function buscarModelos(Request $request, BuscarMarcasModelosActivoService $service)
    {
        return response()->json(
            $service->modelos(
                $request->integer('marca_id') ?: null,
                $request->input('q'),
                $request->integer('tipo_id') ?: null,
                $request->input('marca_nombre'),
            )
        );
    }

    private function resolverActivoConsultaPublica(string $token): Activo
    {
        $activo = Activo::where('consulta_token', $token)->first();

        if (!$activo || $activo->estado === 'baja') {
            abort(404);
        }

        return $activo;
    }

    private function respuestaQrConsultaPublica(Activo $activo, string $formato = 'svg', int $size = 120)
    {
        $url = route('activos.consulta.publica', $activo->consulta_token, absolute: true);
        $qrCode = new QrCode(
            data: $url,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 4,
        );

        if ($formato === 'png') {
            $result = (new PngWriter())->write($qrCode);

            return response($result->getString(), 200, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'public, max-age=86400',
                'Content-Disposition' => 'inline; filename="qr-' . $activo->folio . '.png"',
            ]);
        }

        $result = (new SvgWriter())->write($qrCode);

        return response($result->getString(), 200, [
            'Content-Type' => $result->getMimeType(),
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function buscarActivoPorCodigoEscaneado(string $codigo): ?Activo
    {
        $query = Activo::query()->where('estado', '!=', 'baja');
        $usuario = Auth::user();

        if ($usuario && !$usuario->hasRole(['Super Admin', 'Administrador']) && !$usuario->can('activos.ver_todos')) {
            $departamentos = $usuario->departamentos->pluck('id')->toArray();
            if (empty($departamentos)) {
                return null;
            }
            $query->whereIn('departamento_id', $departamentos);
        }

        $codigoLike = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $codigo) . '%';

        return $query->where(function ($q) use ($codigo, $codigoLike) {
            $q->where('folio', $codigo)
                ->orWhere('nombre', 'like', $codigoLike)
                ->orWhere('atributos->serial', $codigo)
                ->orWhere('atributos->mac', $codigo)
                ->orWhere('atributos->mac', 'like', $codigoLike)
                ->orWhere('atributos->ip', $codigo)
                ->orWhere('atributos->numero_serie', $codigo)
                ->orWhere('atributos->no_serie', $codigo)
                ->orWhere('atributos->imei', $codigo);
        })->first();
    }

    public function firmar(Request $request, ActivoAsignacion $asignacion)
    {
        $usuario = Auth::user();
        if ($asignacion->user_id !== $usuario->id) {
            $this->autorizarAccesoActivo($asignacion->activo);
            if (!$usuario->hasRole(['Super Admin', 'Administrador']) && !$usuario->can('activos.asignar')) {
                abort(403, 'No tiene autorización para firmar esta asignación.');
            }
        }

        $request->validate([
            'firma' => 'required|string',
        ]);

        $base64 = $request->input('firma');
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
            $data = substr($base64, strpos($base64, ',') + 1);
            $type = strtolower($type[1]);

            if (!in_array($type, ['png', 'jpg', 'jpeg'], true)) {
                return back()->withErrors(['firma' => 'Formato de imagen inválido.']);
            }

            $data = base64_decode($data);

            if ($data === false) {
                return back()->withErrors(['firma' => 'Fallo al decodificar la firma.']);
            }
        } else {
            return back()->withErrors(['firma' => 'Formato de datos de firma inválido.']);
        }

        $filename = "activos/firmas/{$asignacion->id}.{$type}";
        Storage::disk('public')->put($filename, $data);

        $asignacion->update([
            'firmado' => true,
            'firma_ruta' => $filename,
            'firma_fecha' => now(),
        ]);

        return back()->with('success', 'Activo firmado de recibido correctamente.');
    }

    public function responsiva(ActivoAsignacion $asignacion, GenerarResponsivaService $generarService)
    {
        $usuario = Auth::user();
        if ($asignacion->user_id !== $usuario->id) {
            $this->autorizarAccesoActivo($asignacion->activo);
        }

        $pdf = $generarService->individual($asignacion);

        return $pdf->download("Responsiva_{$asignacion->activo->folio}.pdf");
    }

    public function responsivaVistaPrevia(ActivoAsignacion $asignacion, GenerarResponsivaService $generarService)
    {
        $usuario = Auth::user();
        if ($asignacion->user_id !== $usuario->id) {
            $this->autorizarAccesoActivo($asignacion->activo);
        }

        $pdf = $generarService->individual($asignacion);

        return $pdf->stream("Responsiva_{$asignacion->activo->folio}.pdf");
    }

    public function firmarConjunto(Request $request)
    {
        $request->validate([
            'asignacion_ids' => 'required|array',
            'asignacion_ids.*' => 'integer|exists:activo_asignaciones,id',
            'firma' => 'required|string',
        ]);

        $usuario = Auth::user();
        $asignacionIds = $request->input('asignacion_ids');
        $base64 = $request->input('firma');

        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
            $data = substr($base64, strpos($base64, ',') + 1);
            $type = strtolower($type[1]);

            if (!in_array($type, ['png', 'jpg', 'jpeg'], true)) {
                return back()->withErrors(['firma' => 'Formato de imagen inválido.']);
            }

            $data = base64_decode($data);

            if ($data === false) {
                return back()->withErrors(['firma' => 'Fallo al decodificar la firma.']);
            }
        } else {
            return back()->withErrors(['firma' => 'Formato de datos de firma inválido.']);
        }

        foreach ($asignacionIds as $id) {
            $asignacion = ActivoAsignacion::findOrFail($id);

            if ($asignacion->user_id !== $usuario->id) {
                $this->autorizarAccesoActivo($asignacion->activo);
                if (!$usuario->hasRole(['Super Admin', 'Administrador']) && !$usuario->can('activos.asignar')) {
                    abort(403, 'No tiene autorización para firmar esta asignación.');
                }
            }

            $filename = "activos/firmas/{$asignacion->id}.{$type}";
            Storage::disk('public')->put($filename, $data);

            $asignacion->update([
                'firmado' => true,
                'firma_ruta' => $filename,
                'firma_fecha' => now(),
            ]);
        }

        return back()->with('success', 'Entrega en conjunto firmada correctamente.');
    }

    public function responsivaConjunta(User $usuario, GenerarResponsivaService $generarService)
    {
        $currentUser = Auth::user();
        if ($usuario->id !== $currentUser->id) {
            if (!$currentUser->hasRole(['Super Admin', 'Administrador']) && !$currentUser->can('activos.asignar') && !$currentUser->can('activos.ver_todos')) {
                abort(403, 'No tiene autorización para ver las asignaciones de este usuario.');
            }
        }

        $asignaciones = ActivoAsignacion::where('user_id', $usuario->id)
            ->where('activa', true)
            ->with(['activo.tipo', 'activo.departamento', 'activo.area', 'activo.categoria', 'activo.accesorios.tipo', 'asignadoPor'])
            ->get();

        if ($asignaciones->isEmpty()) {
            abort(404, 'El colaborador no tiene asignaciones activas.');
        }

        foreach ($asignaciones as $asignacion) {
            if ($usuario->id !== $currentUser->id) {
                $this->autorizarAccesoActivo($asignacion->activo);
            }
        }

        $pdf = $generarService->conjunta($usuario, $asignaciones);

        return $pdf->download("Responsiva_Conjunta_{$usuario->name}.pdf");
    }

    public function responsivaConjuntaVistaPrevia(User $usuario, GenerarResponsivaService $generarService)
    {
        $currentUser = Auth::user();
        if ($usuario->id !== $currentUser->id) {
            if (!$currentUser->hasRole(['Super Admin', 'Administrador']) && !$currentUser->can('activos.asignar') && !$currentUser->can('activos.ver_todos')) {
                abort(403, 'No tiene autorización para ver las asignaciones de este usuario.');
            }
        }

        $asignaciones = ActivoAsignacion::where('user_id', $usuario->id)
            ->where('activa', true)
            ->with(['activo.tipo', 'activo.departamento', 'activo.area', 'activo.categoria', 'activo.accesorios.tipo', 'asignadoPor'])
            ->get();

        if ($asignaciones->isEmpty()) {
            abort(404, 'El colaborador no tiene asignaciones activas.');
        }

        foreach ($asignaciones as $asignacion) {
            if ($usuario->id !== $currentUser->id) {
                $this->autorizarAccesoActivo($asignacion->activo);
            }
        }

        $pdf = $generarService->conjunta($usuario, $asignaciones);

        return $pdf->stream("Responsiva_Conjunta_{$usuario->name}.pdf");
    }

    public function buscarActivos(Request $request, BuscarActivosService $service)
    {
        return response()->json(
            $service->ejecutar(
                Auth::user(),
                trim((string) $request->input('q', '')),
                $request->integer('excluir_id') ?: null,
            )
        );
    }

    public function guardarConfiguracion(Request $request)
    {
        $request->validate([
            'terminos_condiciones' => 'required|string|max:10000',
        ]);

        ActivoConfiguracion::updateOrCreate(
            ['id' => 1],
            ['terminos_condiciones' => $request->input('terminos_condiciones')]
        );

        return back()->with('success', 'Configuración de activos actualizada correctamente.');
    }

    private function autorizarAccesoActivo(Activo $activo): void
    {
        $usuario = Auth::user();

        if ($usuario->hasRole(['Super Admin', 'Administrador']) || $usuario->can('activos.ver_todos')) {
            return;
        }

        $departamentos = $usuario->departamentos->pluck('id')->toArray();

        if (!in_array($activo->departamento_id, $departamentos, true)) {
            abort(403, 'No tiene acceso a este activo.');
        }
    }

    private function filtrosEtiquetas(Request $request): array
    {
        $filtros = $request->only([
            'busqueda',
            'catalogo_tipo_activo_id',
            'catalogo_categoria_activo_id',
            'departamento_id',
            'estado',
            'responsable_user_id',
            'mis_activos',
            'sin_asignar',
            'en_mantenimiento',
            'pendientes_firma',
        ]);

        $responsables = $request->input('responsable_user_ids', []);
        if (is_string($responsables)) {
            $responsables = array_filter(explode(',', $responsables));
        }
        if (is_array($responsables) && !empty($responsables)) {
            $filtros['responsable_user_ids'] = $responsables;
        }

        $filtros['excluir_baja'] = true;

        if (empty($filtros['estado'])) {
            unset($filtros['estado']);
        }

        return $filtros;
    }

    private function filtrosEtiquetasPublicos(array $filtros): array
    {
        $publicos = collect($filtros)->except(['excluir_baja'])->all();

        if (!empty($publicos['responsable_user_ids'])) {
            $publicos['responsable_user_ids'] = array_values($publicos['responsable_user_ids']);
        }

        return $publicos;
    }

    private function opcionesLayoutEtiquetas(Request $request): array
    {
        return [
            'proporcion' => $request->input('proporcion') === '1:1' ? '1:1' : '2:1',
            'tamanio_hoja' => array_key_exists($request->input('tamanio_hoja'), EtiquetaActivoAssets::TAMANOS_HOJA)
                ? strtolower((string) $request->input('tamanio_hoja'))
                : ResolverDimensionesEtiquetaService::DEFAULT_TAMANIO_HOJA,
            'orientacion_hoja' => $request->input('orientacion_hoja') === 'portrait' ? 'portrait' : 'landscape',
            'orientacion_etiqueta' => $request->input('orientacion_etiqueta') === 'vertical' ? 'vertical' : 'horizontal',
            'gap_mm' => $request->filled('gap_mm') ? (float) $request->input('gap_mm') : ResolverDimensionesEtiquetaService::DEFAULT_GAP_MM,
        ];
    }
}
