<?php

namespace App\Services\Mensajeria;

use App\Models\Conversacion;
use App\Models\ConversacionParticipante;
use App\Models\User;
use App\Services\Presencia\PresenciaUsuarioService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListarConversacionesService
{
    public function __construct(
        private PresenciaUsuarioService $presenciaUsuario,
    ) {}

    public function ejecutar(User $user): Collection
    {
        $conversaciones = $this->baseQuery($user)->get();

        return $this->conNoLeidos($conversaciones, $user);
    }

    public function resumen(User $user, int $limit = 8): array
    {
        $conversaciones = $this->baseQuery($user)->limit($limit)->get();

        $totalUnread = $this->contarTotalNoLeidos($user);

        return [
            'total_unread' => $totalUnread,
            'conversaciones' => $this->conNoLeidos($conversaciones, $user)->values()->all(),
        ];
    }

    private function baseQuery(User $user)
    {
        return Conversacion::query()
            ->select('conversaciones.*')
            ->join('conversacion_participantes as cp', 'cp.conversacion_id', '=', 'conversaciones.id')
            ->where('cp.user_id', $user->id)
            ->with(['participantes.user:id,name,username,foto_perfil'])
            ->orderByDesc('conversaciones.ultimo_mensaje_at')
            ->orderByDesc('conversaciones.id');
    }

    private function conNoLeidos(Collection $conversaciones, User $user): Collection
    {
        $mapa = $this->mapaNoLeidos($user, $conversaciones->pluck('id')->all());

        return $conversaciones->map(fn ($row) => $this->formatear($row, $user, $mapa));
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function mapaNoLeidos(User $user, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $filas = DB::table('mensajes as m')
            ->join('conversacion_participantes as cp', function ($join) use ($user) {
                $join->on('cp.conversacion_id', '=', 'm.conversacion_id')
                    ->where('cp.user_id', '=', $user->id);
            })
            ->whereIn('m.conversacion_id', $ids)
            ->where('m.user_id', '!=', $user->id)
            ->whereNull('m.deleted_at')
            ->where(function ($q) {
                $q->whereNull('cp.ultimo_leido_at')
                    ->orWhereColumn('m.created_at', '>', 'cp.ultimo_leido_at');
            })
            ->groupBy('m.conversacion_id')
            ->select('m.conversacion_id', DB::raw('COUNT(*) as total'))
            ->get();

        $mapa = [];
        foreach ($filas as $fila) {
            $mapa[(int) $fila->conversacion_id] = (int) $fila->total;
        }

        return $mapa;
    }

    private function formatear(Conversacion $conversacion, User $user, array $noLeidos): array
    {
        $unreadCount = $noLeidos[$conversacion->id] ?? 0;

        $otrosParticipantes = $conversacion->participantes
            ->filter(fn ($p) => $p->user_id !== $user->id)
            ->values();

        $nombre = $conversacion->esGrupo()
            ? ($conversacion->nombre ?? 'Grupo')
            : $this->nombreDirecto($otrosParticipantes);

        $foto = $conversacion->esGrupo()
            ? $conversacion->foto
            : ($otrosParticipantes->first()?->user?->foto_perfil);

        $otroUserId = $conversacion->esGrupo() ? null : $otrosParticipantes->first()?->user_id;
        $presenciaOtros = $this->presenciasPorConversacion($conversacion, $user);

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
                'username' => $p->user?->username,
                'foto_perfil' => $p->user?->foto_perfil,
                'rol' => $p->rol,
                'presencia' => $presenciaOtros[$p->user_id] ?? null,
            ])->values()->all(),
            'presencia_otro' => $otroUserId ? ($presenciaOtros[$otroUserId] ?? null) : null,
        ];
    }

    /** @return array<int, array> */
    private function presenciasPorConversacion(Conversacion $conversacion, User $viewer): array
    {
        try {
            $ids = $conversacion->participantes
                ->pluck('user_id')
                ->filter(fn ($id) => (int) $id !== (int) $viewer->id)
                ->values()
                ->all();

            return $this->presenciaUsuario->formatearVarios($ids);
        } catch (\Throwable) {
            return [];
        }
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
