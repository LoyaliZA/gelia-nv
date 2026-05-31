<?php

namespace App\Services\Mensajeria;

use App\Models\Conversacion;
use App\Models\Mensaje;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CargarMensajesAlrededorService
{
    private const VENTANA = 25;

    public function __construct(
        private FormatearMensajeService $formatearMensaje,
    ) {}

    public function ejecutar(Conversacion $conversacion, User $user, int $mensajeId): array
    {
        $target = Mensaje::query()
            ->where('conversacion_id', $conversacion->id)
            ->where('id', $mensajeId)
            ->firstOrFail();

        $antes = $this->queryAnteriores($conversacion->id, $target)
            ->with(['user:id,name,username,foto_perfil', 'adjuntos', 'replyTo.user:id,name', 'replyTo.adjuntos', 'lecturas'])
            ->limit(self::VENTANA)
            ->get()
            ->reverse()
            ->values();

        $despues = $this->queryPosteriores($conversacion->id, $target)
            ->with(['user:id,name,username,foto_perfil', 'adjuntos', 'replyTo.user:id,name', 'replyTo.adjuntos', 'lecturas'])
            ->limit(self::VENTANA)
            ->get();

        $target->load(['user:id,name,username,foto_perfil', 'adjuntos', 'replyTo.user:id,name', 'replyTo.adjuntos', 'lecturas']);

        $mensajes = $antes
            ->push($target)
            ->concat($despues)
            ->map(fn ($m) => $this->formatearMensaje->ejecutar($m, $user, $conversacion));

        $referenciaAntigua = $antes->isNotEmpty() ? $antes->first() : $target;
        $hayMasAntiguos = $this->queryAnteriores($conversacion->id, $referenciaAntigua)->exists();

        $nextCursor = null;
        if ($hayMasAntiguos && $antes->isNotEmpty()) {
            $oldest = $antes->first();
            $nextCursor = base64_encode($oldest->created_at->toDateTimeString() . '|' . $oldest->id);
        }

        return [
            'mensajes' => $mensajes->all(),
            'mensaje_objetivo_id' => $target->id,
            'next_cursor' => $nextCursor,
            'has_more' => $hayMasAntiguos,
        ];
    }

    private function queryAnteriores(int $conversacionId, Mensaje $referencia): Builder
    {
        return Mensaje::query()
            ->where('conversacion_id', $conversacionId)
            ->where(function ($q) use ($referencia) {
                $q->where('created_at', '<', $referencia->created_at)
                    ->orWhere(function ($q2) use ($referencia) {
                        $q2->where('created_at', '=', $referencia->created_at)
                            ->where('id', '<', $referencia->id);
                    });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    private function queryPosteriores(int $conversacionId, Mensaje $referencia): Builder
    {
        return Mensaje::query()
            ->where('conversacion_id', $conversacionId)
            ->where(function ($q) use ($referencia) {
                $q->where('created_at', '>', $referencia->created_at)
                    ->orWhere(function ($q2) use ($referencia) {
                        $q2->where('created_at', '=', $referencia->created_at)
                            ->where('id', '>', $referencia->id);
                    });
            })
            ->orderBy('created_at')
            ->orderBy('id');
    }
}
