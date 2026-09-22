<?php

namespace App\Services\PuntoVenta\Publicidad;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Events\PuntoVenta\PublicidadPdvActualizada;
use App\Models\Medios\Medio;
use App\Models\PuntoVenta\PdvPantallaPublicidad;
use App\Models\User;
use App\Services\PuntoVenta\Pantallas\ConsultaPlaylistPantallaSalaPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class GestionarPublicidadPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly ConsultaPlaylistPantallaSalaPdvService $consulta,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listar(User $actor, int $sucursalId): array
    {
        $this->asegurar($actor, PuntoVentaModulo::PERMISO_PUBLICIDAD_VER, $sucursalId);

        return $this->consulta->administrar($sucursalId);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return list<array<string, mixed>>
     */
    public function crear(User $actor, int $sucursalId, array $datos): array
    {
        $this->asegurar($actor, PuntoVentaModulo::PERMISO_PUBLICIDAD_CREAR, $sucursalId);
        $medio = $this->medioListo((int) ($datos['medio_id'] ?? 0));
        $tipo = $medio->tipo;
        $maxOrden = (int) PdvPantallaPublicidad::query()->max('orden');

        $item = PdvPantallaPublicidad::query()->create([
            'sucursal_id' => ($datos['alcance'] ?? 'sucursal') === 'global' ? null : $sucursalId,
            'medio_id' => $medio->id,
            'tipo' => $tipo,
            'ruta' => $medio->object_key,
            'duracion_seg' => $this->duracionPara($tipo, $datos['duracion_seg'] ?? $medio->duracion_seg),
            'ajuste' => $datos['ajuste'] ?? PdvPantallaPublicidad::AJUSTE_COVER,
            'orden' => $maxOrden + 1,
            'activa' => array_key_exists('activa', $datos)
                ? filter_var($datos['activa'], FILTER_VALIDATE_BOOLEAN)
                : true,
            'vigente_desde' => $datos['vigente_desde'] ?? null,
            'vigente_hasta' => $datos['vigente_hasta'] ?? null,
            'nombre_original' => $medio->nombre_original,
            'creado_por' => $actor->id,
        ]);

        $this->notificar($sucursalId, $item);

        return $this->consulta->administrar($sucursalId);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return list<array<string, mixed>>
     */
    public function actualizar(User $actor, int $sucursalId, int $publicidadId, array $datos): array
    {
        $this->asegurar($actor, PuntoVentaModulo::PERMISO_PUBLICIDAD_EDITAR, $sucursalId);
        $item = $this->localizar($sucursalId, $publicidadId);

        $tipo = $item->tipo;
        if (array_key_exists('alcance', $datos)) {
            $item->sucursal_id = $datos['alcance'] === 'global' ? null : $sucursalId;
        }
        if (array_key_exists('ajuste', $datos) && $datos['ajuste']) {
            $item->ajuste = $datos['ajuste'];
        }
        if (array_key_exists('duracion_seg', $datos)) {
            $item->duracion_seg = $this->duracionPara($tipo, $datos['duracion_seg']);
        }
        if (array_key_exists('activa', $datos)) {
            $item->activa = filter_var($datos['activa'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('vigente_desde', $datos)) {
            $item->vigente_desde = $datos['vigente_desde'] ?: null;
        }
        if (array_key_exists('vigente_hasta', $datos)) {
            $item->vigente_hasta = $datos['vigente_hasta'] ?: null;
        }
        $item->save();
        $this->notificar($sucursalId, $item);

        return $this->consulta->administrar($sucursalId);
    }

    /**
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    public function ordenar(User $actor, int $sucursalId, array $ids): array
    {
        $this->asegurar($actor, PuntoVentaModulo::PERMISO_PUBLICIDAD_ORDENAR, $sucursalId);
        $orden = 1;
        foreach ($ids as $id) {
            $item = $this->localizar($sucursalId, (int) $id);
            $item->update(['orden' => $orden]);
            $orden++;
        }
        $this->notificar($sucursalId, null);

        return $this->consulta->administrar($sucursalId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function eliminar(User $actor, int $sucursalId, int $publicidadId): array
    {
        $this->asegurar($actor, PuntoVentaModulo::PERMISO_PUBLICIDAD_ELIMINAR, $sucursalId);
        $item = $this->localizar($sucursalId, $publicidadId);
        $medioId = $item->medio_id;
        $item->delete();

        if ($medioId) {
            $referencias = PdvPantallaPublicidad::query()->where('medio_id', $medioId)->count();
            if ($referencias === 0) {
                $medio = Medio::query()->find($medioId);
                if ($medio instanceof Medio) {
                    $medio->update(['estado' => Medio::ESTADO_DELETED]);
                    // ponytail: no borrar R2 aquí; limpieza física puede reutilizarse en otro anuncio mientras existan filas
                }
            }
        }

        $this->notificar($sucursalId, null);

        return $this->consulta->administrar($sucursalId);
    }

    private function localizar(int $sucursalId, int $publicidadId): PdvPantallaPublicidad
    {
        $item = PdvPantallaPublicidad::query()
            ->whereKey($publicidadId)
            ->where(function ($query) use ($sucursalId): void {
                $query->whereNull('sucursal_id')->orWhere('sucursal_id', $sucursalId);
            })
            ->first();

        if (! $item instanceof PdvPantallaPublicidad) {
            throw new NotFoundHttpException('Publicidad no encontrada.');
        }

        return $item;
    }

    private function asegurar(User $actor, string $permiso, int $sucursalId): void
    {
        $this->alcance->asegurarMutacionPiso($actor, $permiso, $sucursalId);
    }

    private function medioListo(int $medioId): Medio
    {
        $medio = Medio::query()->whereKey($medioId)->first();
        if (! $medio instanceof Medio || $medio->estado !== Medio::ESTADO_READY) {
            throw new UnprocessableEntityHttpException('El archivo multimedia no está listo.');
        }
        if ($medio->proposito !== Medio::PROPOSITO_PDV_PUBLICIDAD) {
            throw new AccessDeniedHttpException('El archivo no corresponde a publicidad.');
        }

        return $medio;
    }

    private function duracionPara(string $tipo, mixed $duracion): ?int
    {
        if ($tipo === PdvPantallaPublicidad::TIPO_VIDEO) {
            return $duracion === null || $duracion === '' ? null : (int) $duracion;
        }

        $valor = (int) ($duracion ?: config('medios.imagen_duracion_default', 10));

        return max(3, min(300, $valor));
    }

    private function notificar(int $sucursalContextoId, ?PdvPantallaPublicidad $item): void
    {
        PublicidadPdvActualizada::dispatch(
            $sucursalContextoId,
            $item?->sucursal_id,
            $item?->id,
        );
    }
}
