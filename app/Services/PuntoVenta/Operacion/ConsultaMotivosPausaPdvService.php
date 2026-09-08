<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Models\PuntoVenta\MotivoPausaPdv;
use Illuminate\Validation\ValidationException;

class ConsultaMotivosPausaPdvService
{
    /**
     * @return list<array{id: int, slug: string, nombre: string, requiere_detalle: bool}>
     */
    public function listarActivos(): array
    {
        return MotivoPausaPdv::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['id', 'slug', 'nombre', 'requiere_detalle'])
            ->map(static fn (MotivoPausaPdv $motivo): array => [
                'id' => $motivo->id,
                'slug' => $motivo->slug,
                'nombre' => $motivo->nombre,
                'requiere_detalle' => (bool) $motivo->requiere_detalle,
            ])
            ->all();
    }

    /**
     * @return array{motivo: MotivoPausaPdv, detalle: string|null}
     */
    public function resolverParaInicio(int $motivoPausaId, ?string $motivoDetalle): array
    {
        $motivo = MotivoPausaPdv::query()->find($motivoPausaId);

        if (! $motivo instanceof MotivoPausaPdv) {
            throw ValidationException::withMessages([
                'motivo_pausa_id' => 'El motivo de pausa seleccionado no existe.',
            ]);
        }

        if (! $motivo->activo) {
            throw ValidationException::withMessages([
                'motivo_pausa_id' => 'El motivo de pausa ya no está disponible.',
            ]);
        }

        $detalle = trim((string) $motivoDetalle);

        if ($motivo->requiere_detalle && $detalle === '') {
            throw ValidationException::withMessages([
                'motivo_detalle' => 'Debe indicar el detalle para el motivo seleccionado.',
            ]);
        }

        if (! $motivo->requiere_detalle) {
            $detalle = '';
        }

        if ($detalle !== '' && mb_strlen($detalle) > 500) {
            throw ValidationException::withMessages([
                'motivo_detalle' => 'El detalle no puede exceder 500 caracteres.',
            ]);
        }

        return [
            'motivo' => $motivo,
            'detalle' => $detalle !== '' ? $detalle : null,
        ];
    }
}
