<?php

namespace Database\Seeders;

use App\Models\CatalogoZonaEntrega;
use App\Models\CatalogoZonaEntregaOverride;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class AsignacionesZonaEntregaSeeder extends Seeder
{
    /**
     * Asignaciones especiales (ej. Ciudad Industrial → horario ZONA 3).
     * Solo se cargan desde database/data/asignaciones_zona_entrega.json.
     *
     * Nota: estas capas no tienen tab en MapaLogistico ni visibilidad en el cotizador;
     * solo afectan la lógica del motor cuando existen features en el JSON.
     */
    public function run(): void
    {
        $rutaArchivo = database_path('data/asignaciones_zona_entrega.json');

        if (!File::exists($rutaArchivo)) {
            $this->command->warn('No se encontró asignaciones_zona_entrega.json; no se sincronizan overrides.');
            return;
        }

        $data = json_decode(File::get($rutaArchivo), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error('asignaciones_zona_entrega.json no tiene formato JSON válido.');
            return;
        }

        $nombresSincronizados = [];

        foreach ($data['features'] ?? [] as $feature) {
            $properties = $feature['properties'] ?? [];
            $nombre = $this->extraerNombreAsignacion($properties);
            $nombreZonaReferencia = $properties['zona_referencia'] ?? null;
            $geometry = $feature['geometry'] ?? null;

            if (!$nombre || !$nombreZonaReferencia || !$this->geometriaValida($geometry)) {
                $this->command->warn('Feature omitido: nombre, zona_referencia o geometría inválidos.');
                continue;
            }

            $zonaReferencia = CatalogoZonaEntrega::where('nombre', $nombreZonaReferencia)->first();

            if (!$zonaReferencia) {
                $this->command->warn("No se encontró {$nombreZonaReferencia} para {$nombre}.");
                continue;
            }

            CatalogoZonaEntregaOverride::updateOrCreate(
                ['nombre' => $nombre],
                [
                    'zona_referencia_id' => $zonaReferencia->id,
                    'coordenadas_poligono' => $geometry,
                    'activo' => true,
                    'prioridad' => (int) ($properties['prioridad'] ?? 1),
                ]
            );

            $nombresSincronizados[] = $nombre;
        }

        CatalogoZonaEntregaOverride::query()
            ->when(
                $nombresSincronizados !== [],
                fn ($q) => $q->whereNotIn('nombre', $nombresSincronizados)
            )
            ->delete();

        if ($nombresSincronizados === []) {
            $this->command->info('Sin features en asignaciones_zona_entrega.json; overrides eliminados.');
            return;
        }

        $this->command->info('Asignaciones especiales sincronizadas: ' . implode(', ', $nombresSincronizados));
    }

    private function extraerNombreAsignacion(array $properties): ?string
    {
        foreach ($properties as $clave => $valor) {
            if (in_array($clave, ['zona_referencia', 'prioridad'], true)) {
                continue;
            }

            if (is_string($clave)) {
                return $clave;
            }
        }

        return null;
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
