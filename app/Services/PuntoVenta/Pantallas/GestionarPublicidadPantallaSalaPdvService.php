<?php

namespace App\Services\PuntoVenta\Pantallas;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\PdvPantallaPublicidad;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GestionarPublicidadPantallaSalaPdvService
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
        $this->asegurarAutorizado($actor, $sucursalId);

        return $this->consulta->administrar($sucursalId);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return list<array<string, mixed>>
     */
    public function crear(User $actor, int $sucursalId, array $datos, UploadedFile $archivo): array
    {
        $this->asegurarAutorizado($actor, $sucursalId);

        $tipo = $this->tipoDesdeArchivo($archivo);
        $ruta = $this->guardarArchivo($archivo);

        PdvPantallaPublicidad::query()->create([
            'sucursal_id' => ($datos['alcance'] ?? 'sucursal') === 'global' ? null : $sucursalId,
            'tipo' => $tipo,
            'ruta' => $ruta,
            'duracion_seg' => $this->duracionPara($tipo, $datos['duracion_seg'] ?? null),
            'ajuste' => $datos['ajuste'] ?? PdvPantallaPublicidad::AJUSTE_COVER,
            'orden' => (int) ($datos['orden'] ?? 0),
            'activa' => array_key_exists('activa', $datos)
                ? filter_var($datos['activa'], FILTER_VALIDATE_BOOLEAN)
                : true,
            'vigente_desde' => $datos['vigente_desde'] ?? null,
            'vigente_hasta' => $datos['vigente_hasta'] ?? null,
            'nombre_original' => $archivo->getClientOriginalName(),
            'creado_por' => $actor->id,
        ]);

        return $this->consulta->administrar($sucursalId);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return list<array<string, mixed>>
     */
    public function actualizar(
        User $actor,
        int $sucursalId,
        int $publicidadId,
        array $datos,
        ?UploadedFile $archivo,
    ): array {
        $this->asegurarAutorizado($actor, $sucursalId);
        $item = $this->localizar($sucursalId, $publicidadId);

        $tipo = $item->tipo;
        $ruta = $item->ruta;
        $nombreOriginal = $item->nombre_original;

        if ($archivo instanceof UploadedFile) {
            $tipo = $this->tipoDesdeArchivo($archivo);
            $rutaNueva = $this->guardarArchivo($archivo);
            $this->eliminarArchivo($item->ruta);
            $ruta = $rutaNueva;
            $nombreOriginal = $archivo->getClientOriginalName();
        }

        $item->update([
            'sucursal_id' => ($datos['alcance'] ?? 'sucursal') === 'global' ? null : $sucursalId,
            'tipo' => $tipo,
            'ruta' => $ruta,
            'duracion_seg' => $this->duracionPara($tipo, $datos['duracion_seg'] ?? null),
            'ajuste' => $datos['ajuste'] ?? $item->ajuste,
            'orden' => (int) ($datos['orden'] ?? $item->orden),
            'activa' => array_key_exists('activa', $datos)
                ? filter_var($datos['activa'], FILTER_VALIDATE_BOOLEAN)
                : $item->activa,
            'vigente_desde' => array_key_exists('vigente_desde', $datos) ? $datos['vigente_desde'] : $item->vigente_desde,
            'vigente_hasta' => array_key_exists('vigente_hasta', $datos) ? $datos['vigente_hasta'] : $item->vigente_hasta,
            'nombre_original' => $nombreOriginal,
        ]);

        return $this->consulta->administrar($sucursalId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function eliminar(User $actor, int $sucursalId, int $publicidadId): array
    {
        $this->asegurarAutorizado($actor, $sucursalId);
        $item = $this->localizar($sucursalId, $publicidadId);
        $this->eliminarArchivo($item->ruta);
        $item->delete();

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

    private function asegurarAutorizado(User $actor, int $sucursalId): void
    {
        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_PANTALLA_SALA_ABRIR,
            $sucursalId,
        );
    }

    private function tipoDesdeArchivo(UploadedFile $archivo): string
    {
        $mime = (string) $archivo->getMimeType();

        if (str_starts_with($mime, 'video/')) {
            return PdvPantallaPublicidad::TIPO_VIDEO;
        }

        if (str_starts_with($mime, 'image/')) {
            return PdvPantallaPublicidad::TIPO_IMAGEN;
        }

        throw new AccessDeniedHttpException('El archivo debe ser imagen o video.');
    }

    private function duracionPara(string $tipo, mixed $duracion): ?int
    {
        if ($duracion === null || $duracion === '') {
            return $tipo === PdvPantallaPublicidad::TIPO_IMAGEN ? 8 : null;
        }

        return (int) $duracion;
    }

    private function guardarArchivo(UploadedFile $archivo): string
    {
        $extension = strtolower((string) $archivo->getClientOriginalExtension()) ?: 'bin';
        $nombre = Str::uuid()->toString().'.'.$extension;

        return $archivo->storeAs(PdvPantallaPublicidad::DIRECTORIO, $nombre, PdvPantallaPublicidad::DISK);
    }

    private function eliminarArchivo(?string $ruta): void
    {
        if ($ruta === null || $ruta === '') {
            return;
        }

        Storage::disk(PdvPantallaPublicidad::DISK)->delete($ruta);
    }
}
