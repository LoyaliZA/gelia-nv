<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrarEntregaMultipleResguardoPdvService
{
    public function __construct(
        private readonly RegistrarEntregaResguardoPdvService $entrega,
    ) {}

    /**
     * @param  list<array{
     *     resguardo: ResguardoPdv,
     *     version: int,
     *     idempotency_key: string,
     *     relacion: string,
     *     nombre_quien_retira: string,
     *     metodo_validacion: string,
     *     firma: UploadedFile,
     *     observaciones: ?string,
     *     evidencias: list<UploadedFile>,
     *     bulto_ids: list<int>|null
     * }>  $items
     * @return list<ResguardoPdv>
     */
    public function ejecutar(User $actor, array $items): array
    {
        if (count($items) < 2) {
            throw ValidationException::withMessages([
                'entregas' => 'La entrega múltiple requiere al menos dos resguardos.',
            ]);
        }

        $resguardoIds = collect($items)->map(fn (array $item) => (int) $item['resguardo']->id);
        if ($resguardoIds->unique()->count() !== $resguardoIds->count()) {
            throw ValidationException::withMessages([
                'entregas' => 'No se puede incluir el mismo resguardo más de una vez.',
            ]);
        }

        $claves = collect($items)->map(fn (array $item) => (string) $item['idempotency_key']);
        if ($claves->unique()->count() !== $claves->count()) {
            throw ValidationException::withMessages([
                'entregas' => 'Cada resguardo debe tener una clave de idempotencia distinta.',
            ]);
        }

        $ordenados = collect($items)
            ->sortBy(fn (array $item) => (int) $item['resguardo']->id)
            ->values()
            ->all();

        $reintentos = 0;
        $nuevos = 0;
        foreach ($ordenados as $item) {
            if ($this->entrega->resolverReintentoIdempotente($item['resguardo'], $item['idempotency_key'])) {
                $reintentos++;
            } else {
                $nuevos++;
            }
        }

        if ($reintentos > 0 && $nuevos > 0) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La operación múltiple no puede mezclar reintentos con resguardos nuevos.',
            ]);
        }

        if ($nuevos === 0) {
            return array_map(
                fn (array $item) => $item['resguardo']->fresh(['bultos', 'entregas']),
                $ordenados
            );
        }

        $pathsEscritos = [];

        try {
            return DB::transaction(function () use ($actor, $ordenados, &$pathsEscritos) {
                $resultados = [];

                foreach ($ordenados as $item) {
                    $resultados[] = $this->entrega->registrar(
                        $item['resguardo'],
                        $actor,
                        (int) $item['version'],
                        (string) $item['idempotency_key'],
                        (string) $item['relacion'],
                        (string) $item['nombre_quien_retira'],
                        (string) $item['metodo_validacion'],
                        $item['firma'],
                        $item['observaciones'] ?? null,
                        $item['evidencias'] ?? [],
                        $item['bulto_ids'] ?? null,
                        true,
                        $pathsEscritos
                    );
                }

                return $resultados;
            });
        } catch (\Throwable $e) {
            $this->entrega->eliminarArchivosHuerfanos($pathsEscritos);
            throw $e;
        }
    }
}
