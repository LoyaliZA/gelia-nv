<?php

namespace App\Services\Mensajeria;

use App\Models\Conversacion;
use App\Models\ConversacionParticipante;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListarConversacionesService
{
    public function ejecutar(User $user): Collection
    {
        return $this->baseQuery($user)->get()->map(fn ($row) => $this->formatear($row, $user));
    }

    public function resumen(User $user, int $limit = 8): array
    {
        $conversaciones = $this->baseQuery($user)->limit($limit)->get();

        $totalUnread = $this->contarTotalNoLeidos($user);

        return [
            'total_unread' => $totalUnread,
            'conversaciones' => $conversaciones->map(fn ($row) => $this->formatear($row, $user))->values()->all(),
        ];
    }

    private function baseQuery(User $user)
    {
        return Conversacion::query()
            ->select('conversaciones.*')
            ->join('conversacion_participantes as cp', 'cp.conversacion_id', '=', 'conversaciones.id')
            ->where('cp.user_id', $user->id)
            ->with(['participantes.user:id,name,foto_perfil,username'])
            ->orderByDesc('conversaciones.ultimo_mensaje_at')
            ->orderByDesc('conversaciones.id');
    }

    private function formatear(Conversacion $conversacion, User $user): array
    {
        $miParticipante = $conversacion->participantes->firstWhere('user_id', $user->id);
        $ultimoLeido = $miParticipante?->ultimo_leido_at;

        $unreadCount = $conversacion->mensajes()
            ->where('user_id', '!=', $user->id)
            ->when($ultimoLeido, fn ($q) => $q->where('created_at', '>', $ultimoLeido))
            ->count();

        $otrosParticipantes = $conversacion->participantes
            ->filter(fn ($p) => $p->user_id !== $user->id)
            ->values();

        $nombre = $conversacion->esGrupo()
            ? ($conversacion->nombre ?? 'Grupo')
            : $this->nombreDirecto($otrosParticipantes);

        $foto = $conversacion->esGrupo()
            ? $conversacion->foto
            : ($otrosParticipantes->first()?->user?->foto_perfil);

        return [
            'id' => $conversacion->id,
            'tipo' => $conversacion->tipo,
            'nombre' => $nombre,
            'foto' => $foto,
            'ultimo_mensaje_preview' => $conversacion->ultimo_mensaje_preview,
            'ultimo_mensaje_at' => $conversacion->ultimo_mensaje_at?->toIso8601String(),
            'unread_count' => $unreadCount,
            'participantes' => $conversacion->participantes->map(fn ($p) => [
                'id' => $p->user_id,
                'name' => $p->user?->name,
                'foto_perfil' => $p->user?->foto_perfil,
                'rol' => $p->rol,
            ])->values()->all(),
        ];
    }

    private function nombreDirecto(Collection $participantes): string
    {
        $otro = $participantes->first()?->user;

        return $otro?->name ?? 'Conversación';
    }

    private function contarTotalNoLeidos(User $user): int
    {
        return (int) DB::table('conversacion_participantes as cp')
            ->join('conversaciones as c', 'c.id', '=', 'cp.conversacion_id')
            ->join('mensajes as m', 'm.conversacion_id', '=', 'c.id')
            ->where('cp.user_id', $user->id)
            ->where('m.user_id', '!=', $user->id)
            ->whereNull('m.deleted_at')
            ->where(function ($q) {
                $q->whereNull('cp.ultimo_leido_at')
                    ->orWhereColumn('m.created_at', '>', 'cp.ultimo_leido_at');
            })
            ->distinct('m.id')
            ->count('m.id');
    }
}
