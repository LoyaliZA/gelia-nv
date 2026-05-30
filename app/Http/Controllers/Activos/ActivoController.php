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
use App\Models\ActivoFoto;
use App\Models\ActivoMantenimiento;
use App\Models\CatalogoTipoActivo;
use App\Models\Departamento;
use App\Models\User;
use App\Services\Activos\ActualizarActivoService;
use App\Services\Activos\AlertasActivosService;
use App\Services\Activos\AsignarActivoService;
use App\Services\Activos\BuscarMarcasModelosActivoService;
use App\Services\Activos\BuscarUsuariosActivoService;
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
use App\Services\Activos\TransferirActivoService;
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
            'busqueda', 'catalogo_tipo_activo_id', 'departamento_id',
            'estado', 'responsable_user_id', 'mis_activos', 'sin_asignar', 'en_mantenimiento',
        ]);

        $alertas = $alertasService->ejecutar(Auth::user());

        return Inertia::render('Activos/Index', [
            'activos' => $listarService->ejecutar(Auth::user(), $filtros),
            'tipos' => CatalogoTipoActivo::where('activo', true)->orderBy('nombre')->get(),
            'departamentos' => Departamento::where('activo', true)->orderBy('nombre')->get(),
            'usuarios' => User::select(['id', 'name', 'email'])->orderBy('name')->get(),
            'filtros' => $filtros,
            'alertasResumen' => [
                'vencidos' => count($alertas['vencidos']),
                'proximos_7' => count($alertas['proximos_7']),
                'proximos_30' => count($alertas['proximos_30']),
                'mantenimiento' => count($alertas['mantenimiento']),
            ],
            'alertas' => $alertas,
        ]);
    }

    public function show(Activo $activo): Response
    {
        $this->autorizarAccesoActivo($activo);

        $activo->load([
            'tipo',
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
            'departamentos' => Departamento::where('activo', true)->with('areas')->orderBy('nombre')->get(),
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

        if (preg_match('/\/activos\/consulta\/([a-f0-9-]{36})/i', $codigo, $coincidencias)) {
            $codigo = $coincidencias[1];
        }

        if (preg_match('/^[a-f0-9-]{36}$/i', $codigo)) {
            $activo = Activo::where('consulta_token', $codigo)->first();
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
        unset($datos['user_id'], $datos['notas']);

        $activo = $crearService->ejecutar(Auth::user(), $datos);

        if ($request->hasFile('fotos')) {
            $fotosService->ejecutar($activo, $request->file('fotos'));
        }

        if ($userIdAsignacion) {
            $activo = $asignarService->ejecutar($activo, Auth::user(), (int) $userIdAsignacion, $notasAsignacion);
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
        $service->ejecutar($activo, Auth::user(), $request->validated('user_id'), $request->validated('notas'));

        return back()->with('success', 'Activo asignado correctamente.');
    }

    public function devolver(Request $request, Activo $activo, DevolverActivoService $service)
    {
        $this->autorizarAccesoActivo($activo);
        $request->validate(['notas' => 'nullable|string|max:1000']);
        $service->ejecutar($activo, Auth::user(), $request->input('notas'));

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
            'busqueda', 'catalogo_tipo_activo_id', 'departamento_id',
            'estado', 'responsable_user_id', 'mis_activos', 'sin_asignar', 'en_mantenimiento',
        ]);

        $activos = $service->ejecutar(Auth::user(), $filtros);
        $nombreArchivo = 'activos-' . now()->format('Y-m-d-His') . '.xlsx';

        return (new FastExcel($service->filas($activos)))->download($nombreArchivo);
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
}
