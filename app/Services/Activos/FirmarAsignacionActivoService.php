<?php

namespace App\Services\Activos;

use App\Models\Activo;
use App\Models\ActivoAsignacion;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FirmarAsignacionActivoService
{
    public function ejecutar(User $usuario, ActivoAsignacion $asignacion, string $firma): ActivoAsignacion
    {
        $this->autorizar($usuario, $asignacion);
        $decodificada = $this->decodificar($firma);
        $this->guardar($asignacion, $decodificada['type'], $decodificada['data']);

        return $asignacion->fresh();
    }

    /**
     * @param  list<int>  $asignacionIds
     */
    public function ejecutarConjunto(User $usuario, array $asignacionIds, string $firma): int
    {
        $decodificada = $this->decodificar($firma);
        $firmadas = 0;

        foreach ($asignacionIds as $id) {
            $asignacion = ActivoAsignacion::query()->findOrFail($id);
            $this->autorizar($usuario, $asignacion);
            $this->guardar($asignacion, $decodificada['type'], $decodificada['data']);
            $firmadas++;
        }

        return $firmadas;
    }

    /**
     * @return array{type: string, data: string}
     */
    private function decodificar(string $firma): array
    {
        if (! preg_match('/^data:image\/(\w+);base64,/', $firma, $type)) {
            throw ValidationException::withMessages([
                'firma' => 'Formato de datos de firma inválido.',
            ]);
        }

        $data = substr($firma, strpos($firma, ',') + 1);
        $extension = strtolower($type[1]);

        if (! in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
            throw ValidationException::withMessages([
                'firma' => 'Formato de imagen inválido.',
            ]);
        }

        $binario = base64_decode($data, true);
        if ($binario === false) {
            throw ValidationException::withMessages([
                'firma' => 'Fallo al decodificar la firma.',
            ]);
        }

        return ['type' => $extension, 'data' => $binario];
    }

    private function guardar(ActivoAsignacion $asignacion, string $extension, string $binario): void
    {
        $filename = "activos/firmas/{$asignacion->id}.{$extension}";
        Storage::disk('public')->put($filename, $binario);

        $asignacion->update([
            'firmado' => true,
            'firma_ruta' => $filename,
            'firma_fecha' => now(),
        ]);
    }

    private function autorizar(User $usuario, ActivoAsignacion $asignacion): void
    {
        if ((int) $asignacion->user_id === (int) $usuario->id) {
            return;
        }

        $this->autorizarAccesoActivo($usuario, $asignacion->activo);

        if (! $usuario->hasRole(['Super Admin', 'Administrador']) && ! $usuario->can('activos.asignar')) {
            abort(403, 'No tiene autorización para firmar esta asignación.');
        }
    }

    private function autorizarAccesoActivo(User $usuario, Activo $activo): void
    {
        if ($usuario->hasRole(['Super Admin', 'Administrador']) || $usuario->can('activos.ver_todos')) {
            return;
        }

        $departamentos = $usuario->departamentos->pluck('id')->all();
        if (! in_array($activo->departamento_id, $departamentos, true)) {
            abort(403, 'No tiene acceso a este activo.');
        }
    }
}
