<?php

namespace App\Http\Controllers\Api\V1\Mobile\Activos;

use App\Http\Controllers\Controller;
use App\Models\Activo;
use App\Models\ActivoAsignacion;
use App\Models\User;
use App\Services\Activos\FirmarAsignacionActivoService;
use App\Services\Activos\ListarActivosService;
use App\Services\Activos\ResolverCodigoActivoService;
use App\Services\Mobile\Serializar\OperacionMovilSerializer;
use App\Support\Api\V1\AutorizacionMovil;
use App\Support\Api\V1\RespuestaPaginada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivoMobileController extends Controller
{
    public function index(Request $request, ListarActivosService $listar, OperacionMovilSerializer $serializar): JsonResponse
    {
        $usuario = $this->usuario($request);
        AutorizacionMovil::alguno($usuario, ['activos.ver']);
        $usuario->loadMissing('departamentos');

        $paginado = $listar->ejecutar($usuario, $this->filtros($request), true, RespuestaPaginada::porPagina($request));
        $paginado->getCollection()->load('asignaciones');

        return response()->json([
            'data' => $paginado->getCollection()->map(fn (Activo $activo) => $serializar->activo($activo))->values(),
            'meta' => RespuestaPaginada::meta($paginado),
        ]);
    }

    public function show(Request $request, Activo $activo, ListarActivosService $listar, OperacionMovilSerializer $serializar): JsonResponse
    {
        $usuario = $this->usuario($request);
        AutorizacionMovil::alguno($usuario, ['activos.ver']);
        $usuario->loadMissing('departamentos');
        abort_unless($listar->puedeVer($usuario, $activo), 404, 'Activo no encontrado.');

        $activo->load([
            'tipo', 'categoria', 'departamento', 'area', 'responsable',
            'asignaciones.usuario', 'mantenimientoActivo',
        ]);

        return response()->json([
            'data' => $serializar->activo($activo, true),
        ]);
    }

    public function resolverCodigo(Request $request, ResolverCodigoActivoService $resolver): JsonResponse
    {
        $usuario = $this->usuario($request);
        AutorizacionMovil::alguno($usuario, ['activos.ver']);
        $usuario->loadMissing('departamentos');

        $codigo = trim((string) $request->query('codigo', ''));
        if ($codigo === '') {
            return response()->json(['message' => 'Código vacío.'], 422);
        }

        $activo = $resolver->resolver($usuario, $codigo);
        if ($activo === null) {
            return response()->json(['message' => 'No se encontró un activo con ese código.'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $activo->id,
                'folio' => $activo->folio,
                'nombre' => $activo->nombre,
            ],
        ]);
    }

    public function pendientesFirma(Request $request, OperacionMovilSerializer $serializar): JsonResponse
    {
        $usuario = $this->usuario($request);
        AutorizacionMovil::alguno($usuario, ['activos.ver']);

        $asignaciones = ActivoAsignacion::query()
            ->with(['activo.tipo', 'activo.departamento', 'usuario'])
            ->where('user_id', $usuario->id)
            ->where('activa', true)
            ->where('firmado', false)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $asignaciones->map(fn (ActivoAsignacion $asignacion) => $serializar->asignacion($asignacion))->values(),
        ]);
    }

    public function firmar(
        Request $request,
        ActivoAsignacion $asignacion,
        FirmarAsignacionActivoService $firmar,
        OperacionMovilSerializer $serializar,
    ): JsonResponse {
        $usuario = $this->usuario($request);
        AutorizacionMovil::alguno($usuario, ['activos.ver']);
        $usuario->loadMissing('departamentos');

        $datos = $request->validate([
            'firma' => ['required', 'string'],
        ]);

        $actualizada = $firmar->ejecutar($usuario, $asignacion, $datos['firma']);
        $actualizada->load(['activo', 'usuario']);

        return response()->json([
            'data' => $serializar->asignacion($actualizada),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filtros(Request $request): array
    {
        $filtros = $request->only([
            'busqueda', 'catalogo_tipo_activo_id', 'catalogo_categoria_activo_id',
            'departamento_id', 'estado', 'responsable_user_id', 'mis_activos',
            'sin_asignar', 'en_mantenimiento', 'pendientes_firma',
        ]);

        if ($request->filled('q') && empty($filtros['busqueda'])) {
            $filtros['busqueda'] = $request->query('q');
        }

        return $filtros;
    }

    private function usuario(Request $request): User
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return $usuario;
    }
}
