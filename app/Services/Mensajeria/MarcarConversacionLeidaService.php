<?php

namespace App\Services\Mensajeria;

use App\Models\Conversacion;
use App\Models\ConversacionParticipante;
use App\Models\Mensaje;
use App\Models\MensajeLectura;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MarcarConversacionLeidaService
{
    public function __construct(
        private NotificarCursorLecturaService $notificarCursor,
    ) {}

    public function ejecutar(Conversacion $conversacion, User $user): void
    {
        $ahora = now();

        ConversacionParticipante::query()
            ->where('conversacion_id', $conversacion->id)
            ->where('user_id', $user->id)
            ->update(['ultimo_leido_at' => $ahora]);

        $ids = Mensaje::query()
            ->where('conversacion_id', $conversacion->id)
            ->where('user_id', '!=', $user->id)
            ->whereDoesntHave('lecturas', fn ($q) => $q->where('user_id', $user->id))
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids->chunk(500) as $lote) {
            MensajeLectura::query()->insertOrIgnore($lote->map(fn ($id) => [
                'mensaje_id' => $id,
                'user_id' => $user->id,
                'leido_at' => $ahora->toDateTimeString(),
            ])->all());
        }

        $porAutor = $ids->isEmpty()
            ? []
            : $this->acusesPorAutor($conversacion, $ids->all());

        $this->notificarCursor->ejecutar(
            $conversacion->id,
            $user->id,
            $ahora->toIso8601String(),
            $porAutor
        );
    }

    /**
     * @param  array<int, int>  $mensajeIds
     * @return array<int, array<int, array{id: int, estado_lectura: string}>|array{leido_hasta_id: int, estado_lectura: string, mensajes: array}>
     */
    private function acusesPorAutor(Conversacion $conversacion, array $mensajeIds): array
    {
        $otros = max(0, $conversacion->participantes()->count() - 1);
        $agrupado = [];

        foreach (array_chunk($mensajeIds, 500) as $lote) {
            $filas = DB::table('mensajes as m')
                ->leftJoin('mensaje_lecturas as l', function ($join) {
                    $join->on('l.mensaje_id', '=', 'm.id')
                        ->whereColumn('l.user_id', '!=', 'm.user_id');
                })
                ->whereIn('m.id', $lote)
                ->groupBy('m.id', 'm.user_id')
                ->get(['m.id', 'm.user_id', DB::raw('COUNT(l.id) as lecturas')]);

            foreach ($filas as $fila) {
                $lecturas = (int) $fila->lecturas;
                $estado = 'enviado';
                if ($otros > 0 && $lecturas >= $otros) {
                    $estado = 'leido';
                } elseif ($lecturas > 0) {
                    $estado = 'entregado';
                }

                $agrupado[(int) $fila->user_id][] = [
                    'id' => (int) $fila->id,
                    'estado_lectura' => $estado,
                ];
            }
        }

        $porAutor = [];
        foreach ($agrupado as $autorId => $mensajes) {
            $estados = array_unique(array_column($mensajes, 'estado_lectura'));
            if (count($estados) === 1) {
                $porAutor[$autorId] = [
                    'leido_hasta_id' => max(array_column($mensajes, 'id')),
                    'estado_lectura' => $estados[0],
                    'mensajes' => [],
                ];
                continue;
            }

            $porAutor[$autorId] = $mensajes;
        }

        return $porAutor;
    }
}
