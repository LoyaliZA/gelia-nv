<?php

namespace App\Services\Mensajeria;

use App\Models\Conversacion;
use App\Models\Mensaje;
use App\Models\User;
use Illuminate\Support\Collection;

class ListarMensajesService
{
    private const POR_PAGINA = 40;

    public function __construct(
        private FormatearMensajeService $formatearMensaje,
    ) {}

    public function ejecutar(Conversacion $conversacion, User $user, ?string $cursor = null): array
    {
        $query = Mensaje::query()
            ->where('conversacion_id', $conversacion->id)
            ->with(['user:id,name,foto_perfil', 'adjuntos', 'replyTo.user:id,name', 'lecturas'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($cursor) {
            [$createdAt, $id] = $this->parseCursor($cursor);
            $query->where(function ($q) use ($createdAt, $id) {
                $q->where('created_at', '<', $createdAt)
                    ->orWhere(function ($q2) use ($createdAt, $id) {
                        $q2->where('created_at', '=', $createdAt)->where('id', '<', $id);
                    });
            });
        }

        $mensajes = $query->limit(self::POR_PAGINA + 1)->get();
        $hasMore = $mensajes->count() > self::POR_PAGINA;

        if ($hasMore) {
            $mensajes = $mensajes->take(self::POR_PAGINA);
        }

        $items = $mensajes
            ->reverse()
            ->values()
            ->map(fn ($m) => $this->formatearMensaje->ejecutar($m, $user, $conversacion));

        $nextCursor = null;
        if ($hasMore && $mensajes->isNotEmpty()) {
            $oldest = $mensajes->last();
            $nextCursor = $this->encodeCursor($oldest);
        }

        return [
            'mensajes' => $items->all(),
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    private function parseCursor(string $cursor): array
    {
        $decoded = base64_decode($cursor, true);
        if (!$decoded || !str_contains($decoded, '|')) {
            return [now()->toDateTimeString(), PHP_INT_MAX];
        }

        [$createdAt, $id] = explode('|', $decoded, 2);

        return [$createdAt, (int) $id];
    }

    private function encodeCursor(Mensaje $mensaje): string
    {
        return base64_encode($mensaje->created_at->toDateTimeString() . '|' . $mensaje->id);
    }
}
