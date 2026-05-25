<?php

namespace App\Http\Controllers;

use App\Models\CatalogoZonaEntrega;
use App\Models\CatalogoZonaPeriferia;
use App\Models\CatalogoZonaRestringida;
use App\Models\ConfiguracionEntrega;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MapaLogisticoController extends Controller
{
    private const TIPOS_VALIDOS = ['principales', 'restringidas', 'periferia'];

    public function index(): Response
    {
        $configuracion = ConfiguracionEntrega::first();

        return Inertia::render('Admin/MapaLogistico', [
            'configuracion' => $configuracion,
            'googleApiKey' => $this->resolverApiKeyGoogle($configuracion),
            'zonas_principales' => $this->listarPrincipales(),
            'zonas_restringidas' => $this->listarRestringidas(),
            'zonas_periferia' => $this->listarPeriferia(),
        ]);
    }

    public function exportar(string $tipo): StreamedResponse
    {
        $this->validarTipo($tipo);

        $featureCollection = match ($tipo) {
            'principales' => $this->exportarPrincipales(),
            'restringidas' => $this->exportarRestringidas(),
            'periferia' => $this->exportarPeriferia(),
        };

        $nombreArchivo = "zonas_{$tipo}_" . now()->format('Y-m-d') . '.json';

        return response()->streamDownload(
            fn () => print(json_encode($featureCollection, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            $nombreArchivo,
            ['Content-Type' => 'application/json']
        );
    }

    public function importar(Request $request, string $tipo): JsonResponse
    {
        $this->validarTipo($tipo);

        $request->validate([
            'geojson' => 'required|array',
            'geojson.type' => 'required|in:FeatureCollection',
            'geojson.features' => 'required|array|min:1',
        ]);

        $importados = match ($tipo) {
            'principales' => $this->importarPrincipales($request->input('geojson.features')),
            'restringidas' => $this->importarRestringidas($request->input('geojson.features')),
            'periferia' => $this->importarPeriferia($request->input('geojson.features')),
        };

        return response()->json([
            'mensaje' => "Se sincronizaron {$importados} zona(s) de tipo {$tipo}.",
            'importados' => $importados,
        ]);
    }

    public function actualizarPeriferia(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'zona_referencia_id' => 'required|exists:catalogo_zonas_entrega,id',
            'activo' => 'sometimes|boolean',
        ]);

        $periferia = CatalogoZonaPeriferia::findOrFail($id);
        $periferia->update($datos);

        return response()->json([
            'mensaje' => 'Zona de periferia actualizada.',
            'zona' => $this->formatearPeriferia($periferia->fresh('zonaReferencia')),
        ]);
    }

    public function toggleActivo(Request $request, string $tipo, int $id): JsonResponse
    {
        $this->validarTipo($tipo);

        $request->validate(['activo' => 'required|boolean']);

        $modelo = match ($tipo) {
            'principales' => CatalogoZonaEntrega::findOrFail($id),
            'restringidas' => CatalogoZonaRestringida::findOrFail($id),
            'periferia' => CatalogoZonaPeriferia::findOrFail($id),
        };

        $modelo->update(['activo' => $request->boolean('activo')]);

        return response()->json(['mensaje' => 'Estado de zona actualizado.']);
    }

    public function eliminar(string $tipo, int $id): JsonResponse
    {
        $this->validarTipo($tipo);

        match ($tipo) {
            'principales' => CatalogoZonaEntrega::findOrFail($id)->update(['activo' => false]),
            'restringidas' => CatalogoZonaRestringida::findOrFail($id)->delete(),
            'periferia' => CatalogoZonaPeriferia::findOrFail($id)->delete(),
        };

        return response()->json(['mensaje' => 'Zona eliminada del catálogo.']);
    }

    public function store(Request $request, string $tipo): RedirectResponse
    {
        $this->validarTipo($tipo);

        $datos = $request->validate([
            'nombre' => 'required|string|max:255',
            'coordenadas' => 'required|array|min:3',
            'coordenadas.*.lat' => 'required|numeric',
            'coordenadas.*.lng' => 'required|numeric',
            'color_hex' => 'nullable|string|size:7',
            'costo_base' => 'nullable|numeric|min:0',
            'zona_referencia_id' => 'nullable|exists:catalogo_zonas_entrega,id',
        ]);

        $geometria = $this->construirGeometriaDesdePuntos($datos['coordenadas']);
        if (!$geometria) {
            return back()->withErrors(['coordenadas' => 'Se requieren al menos 3 vértices válidos.']);
        }

        match ($tipo) {
            'principales' => CatalogoZonaEntrega::create([
                'nombre' => $datos['nombre'],
                'coordenadas_poligono' => $geometria,
                'color_hex' => $datos['color_hex'] ?? '#3B82F6',
                'costo_base' => $datos['costo_base'] ?? 50.00,
                'activo' => true,
            ]),
            'restringidas' => CatalogoZonaRestringida::create([
                'nombre' => $datos['nombre'],
                'coordenadas_poligono' => $geometria,
                'activo' => true,
            ]),
            'periferia' => $this->crearPeriferia($datos, $geometria),
        };

        return back()->with('success', 'Zona creada correctamente.');
    }

    public function actualizarPoligono(Request $request, string $tipo, int $id): RedirectResponse
    {
        $this->validarTipo($tipo);

        $datos = $request->validate([
            'coordenadas' => 'required|array|min:3',
            'coordenadas.*.lat' => 'required|numeric',
            'coordenadas.*.lng' => 'required|numeric',
            'nombre' => 'sometimes|string|max:255',
            'color_hex' => 'nullable|string|size:7',
            'costo_base' => 'nullable|numeric|min:0',
            'zona_referencia_id' => 'nullable|exists:catalogo_zonas_entrega,id',
        ]);

        $geometria = $this->construirGeometriaDesdePuntos($datos['coordenadas']);
        if (!$geometria) {
            return back()->withErrors(['coordenadas' => 'Se requieren al menos 3 vértices válidos.']);
        }

        $modelo = match ($tipo) {
            'principales' => CatalogoZonaEntrega::findOrFail($id),
            'restringidas' => CatalogoZonaRestringida::findOrFail($id),
            'periferia' => CatalogoZonaPeriferia::findOrFail($id),
        };

        $actualizacion = ['coordenadas_poligono' => $geometria];

        if (isset($datos['nombre'])) {
            $actualizacion['nombre'] = $datos['nombre'];
        }

        if ($tipo === 'principales') {
            if (isset($datos['color_hex'])) {
                $actualizacion['color_hex'] = $datos['color_hex'];
            }
            if (isset($datos['costo_base'])) {
                $actualizacion['costo_base'] = $datos['costo_base'];
            }
        }

        if ($tipo === 'periferia' && isset($datos['zona_referencia_id'])) {
            $actualizacion['zona_referencia_id'] = $datos['zona_referencia_id'];
        }

        $modelo->update($actualizacion);

        return back()->with('success', 'Polígono actualizado correctamente.');
    }

    private function crearPeriferia(array $datos, array $geometria): CatalogoZonaPeriferia
    {
        $zonaReferenciaId = $datos['zona_referencia_id'] ?? null;

        if (!$zonaReferenciaId && preg_match('/PERIFERIA ZONA (\d+)/', $datos['nombre'], $matches)) {
            $zonaReferenciaId = CatalogoZonaEntrega::where('nombre', 'ZONA ' . $matches[1])->value('id');
        }

        if (!$zonaReferenciaId) {
            throw ValidationException::withMessages([
                'zona_referencia_id' => ['Debes asignar una zona de referencia para horarios de periferia.'],
            ]);
        }

        return CatalogoZonaPeriferia::create([
            'nombre' => $datos['nombre'],
            'coordenadas_poligono' => $geometria,
            'zona_referencia_id' => $zonaReferenciaId,
            'activo' => true,
        ]);
    }

    private function construirGeometriaDesdePuntos(array $puntos): ?array
    {
        if (count($puntos) < 3) {
            return null;
        }

        $ring = array_map(
            fn ($punto) => [(float) $punto['lng'], (float) $punto['lat']],
            $puntos
        );

        [$primeroLng, $primeroLat] = $ring[0];
        [$ultimoLng, $ultimoLat] = $ring[count($ring) - 1];

        if ($primeroLng !== $ultimoLng || $primeroLat !== $ultimoLat) {
            $ring[] = [$primeroLng, $primeroLat];
        }

        return [
            'type' => 'Polygon',
            'coordinates' => [$ring],
        ];
    }

    private function validarTipo(string $tipo): void
    {
        if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
            abort(404, 'Tipo de zona no válido.');
        }
    }

    private function resolverApiKeyGoogle(?ConfiguracionEntrega $configuracion): ?string
    {
        if (!$configuracion?->api_key_google) {
            return null;
        }

        try {
            return Crypt::decryptString($configuracion->api_key_google);
        } catch (\Exception) {
            return null;
        }
    }

    private function listarPrincipales(): array
    {
        return CatalogoZonaEntrega::orderBy('nombre')
            ->get()
            ->map(fn ($zona) => $this->formatearPrincipal($zona))
            ->values()
            ->all();
    }

    private function listarRestringidas(): array
    {
        return CatalogoZonaRestringida::orderBy('nombre')
            ->get()
            ->map(fn ($zona) => $this->formatearRestringida($zona))
            ->values()
            ->all();
    }

    private function listarPeriferia(): array
    {
        return CatalogoZonaPeriferia::with('zonaReferencia')
            ->orderBy('nombre')
            ->get()
            ->map(fn ($zona) => $this->formatearPeriferia($zona))
            ->values()
            ->all();
    }

    private function formatearPrincipal(CatalogoZonaEntrega $zona): array
    {
        return array_merge($this->formatearPoligonoBase($zona), [
            'color_hex' => $zona->color_hex,
            'costo_base' => (float) $zona->costo_base,
            'activo' => $zona->activo,
        ]);
    }

    private function formatearRestringida(CatalogoZonaRestringida $zona): array
    {
        return array_merge($this->formatearPoligonoBase($zona), [
            'activo' => $zona->activo,
        ]);
    }

    private function formatearPeriferia(CatalogoZonaPeriferia $zona): array
    {
        return array_merge($this->formatearPoligonoBase($zona), [
            'zona_referencia_id' => $zona->zona_referencia_id,
            'zona_referencia_nombre' => $zona->zonaReferencia?->nombre,
            'color_hex' => $zona->zonaReferencia?->color_hex ?? '#F59E0B',
            'activo' => $zona->activo,
        ]);
    }

    private function formatearPoligonoBase(object $registro): array
    {
        $coordenadas = $registro->coordenadas_poligono['coordinates'][0] ?? [];

        return [
            'id' => $registro->id,
            'nombre' => $registro->nombre,
            'rutas_formateadas' => array_map(
                fn ($punto) => ['lat' => (float) $punto[1], 'lng' => (float) $punto[0]],
                $coordenadas
            ),
        ];
    }

    private function exportarPrincipales(): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => CatalogoZonaEntrega::orderBy('nombre')->get()->map(fn ($zona) => [
                'type' => 'Feature',
                'properties' => [$zona->nombre => 0],
                'geometry' => $zona->coordenadas_poligono,
            ])->values()->all(),
        ];
    }

    private function exportarRestringidas(): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => CatalogoZonaRestringida::orderBy('nombre')->get()->map(fn ($zona) => [
                'type' => 'Feature',
                'properties' => [$zona->nombre => 0],
                'geometry' => $zona->coordenadas_poligono,
            ])->values()->all(),
        ];
    }

    private function exportarPeriferia(): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => CatalogoZonaPeriferia::orderBy('nombre')->get()->map(fn ($zona) => [
                'type' => 'Feature',
                'properties' => [$zona->nombre => 0],
                'geometry' => $zona->coordenadas_poligono,
            ])->values()->all(),
        ];
    }

    private function importarPrincipales(array $features): int
    {
        $colores = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444'];
        $importados = 0;

        foreach ($features as $index => $feature) {
            $nombre = $this->extraerNombreFeature($feature);
            if (!$nombre || !$this->geometriaValida($feature['geometry'] ?? null)) {
                continue;
            }

            CatalogoZonaEntrega::updateOrCreate(
                ['nombre' => $nombre],
                [
                    'coordenadas_poligono' => $feature['geometry'],
                    'color_hex' => CatalogoZonaEntrega::where('nombre', $nombre)->value('color_hex')
                        ?? $colores[$index % count($colores)],
                    'costo_base' => CatalogoZonaEntrega::where('nombre', $nombre)->value('costo_base') ?? 50.00,
                    'activo' => true,
                ]
            );

            $importados++;
        }

        return $importados;
    }

    private function importarRestringidas(array $features): int
    {
        $importados = 0;

        foreach ($features as $feature) {
            $nombre = $this->extraerNombreFeature($feature);
            if (!$nombre || !$this->geometriaValida($feature['geometry'] ?? null)) {
                continue;
            }

            CatalogoZonaRestringida::updateOrCreate(
                ['nombre' => $nombre],
                [
                    'coordenadas_poligono' => $feature['geometry'],
                    'activo' => true,
                ]
            );

            $importados++;
        }

        return $importados;
    }

    private function importarPeriferia(array $features): int
    {
        $importados = 0;

        foreach ($features as $feature) {
            $nombre = $this->extraerNombreFeature($feature);
            if (!$nombre || !$this->geometriaValida($feature['geometry'] ?? null)) {
                continue;
            }

            if (!preg_match('/PERIFERIA ZONA (\d+)/', $nombre, $matches)) {
                continue;
            }

            $zonaReferencia = CatalogoZonaEntrega::where('nombre', 'ZONA ' . $matches[1])->first();
            if (!$zonaReferencia) {
                continue;
            }

            CatalogoZonaPeriferia::updateOrCreate(
                ['nombre' => $nombre],
                [
                    'coordenadas_poligono' => $feature['geometry'],
                    'zona_referencia_id' => $zonaReferencia->id,
                    'activo' => true,
                ]
            );

            $importados++;
        }

        return $importados;
    }

    private function extraerNombreFeature(array $feature): ?string
    {
        $properties = $feature['properties'] ?? [];

        if (isset($properties['nombre']) && is_string($properties['nombre'])) {
            return $properties['nombre'];
        }

        if (!is_array($properties) || empty($properties)) {
            return null;
        }

        $nombre = array_key_first($properties);

        return is_string($nombre) ? $nombre : null;
    }

    private function geometriaValida(?array $geometry): bool
    {
        if (!$geometry || ($geometry['type'] ?? null) !== 'Polygon') {
            return false;
        }

        $anillo = $geometry['coordinates'][0] ?? [];

        return is_array($anillo) && count($anillo) >= 3;
    }
}
