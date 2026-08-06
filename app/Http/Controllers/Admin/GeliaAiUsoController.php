<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeliaAiConversacion;
use App\Models\GeliaAiMensaje;
use App\Models\GeliaAiUso;
use App\Models\User;
use App\Services\GeliaAi\ResolverAccesoGeliaAi;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GeliaAiUsoController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAcceso();

        [$desde, $hasta] = $this->rangoFechas($request);

        $base = GeliaAiUso::query()
            ->where('created_at', '>=', $desde)
            ->where('created_at', '<=', $hasta);

        $totales = (clone $base)
            ->selectRaw('COALESCE(SUM(prompt_tokens),0) as prompt_tokens')
            ->selectRaw('COALESCE(SUM(completion_tokens),0) as completion_tokens')
            ->selectRaw('COALESCE(SUM(total_tokens),0) as total_tokens')
            ->selectRaw('COUNT(*) as turnos')
            ->selectRaw('COUNT(DISTINCT user_id) as usuarios')
            ->first();

        $ranking = (clone $base)
            ->select('user_id')
            ->selectRaw('SUM(total_tokens) as total_tokens')
            ->selectRaw('COUNT(*) as turnos')
            ->selectRaw('AVG(total_tokens) as promedio_tokens')
            ->groupBy('user_id')
            ->orderByDesc('total_tokens')
            ->limit(25)
            ->get();

        $userIds = $ranking->pluck('user_id')->all();
        $users = $userIds === []
            ? collect()
            : User::query()->whereIn('id', $userIds)->get(['id', 'name', 'email'])->keyBy('id');

        $rankingOut = $ranking->map(fn ($row) => [
            'user_id' => (int) $row->user_id,
            'name' => $users->get($row->user_id)?->name ?? 'Usuario #'.$row->user_id,
            'email' => $users->get($row->user_id)?->email,
            'total_tokens' => (int) $row->total_tokens,
            'turnos' => (int) $row->turnos,
            'promedio_tokens' => (int) round((float) $row->promedio_tokens),
        ])->values()->all();

        $porMode = (clone $base)
            ->select('mode')
            ->selectRaw('SUM(total_tokens) as total_tokens')
            ->selectRaw('COUNT(*) as turnos')
            ->groupBy('mode')
            ->orderByDesc('total_tokens')
            ->get()
            ->map(fn ($row) => [
                'mode' => $row->mode ?: 'desconocido',
                'total_tokens' => (int) $row->total_tokens,
                'turnos' => (int) $row->turnos,
            ])
            ->values()
            ->all();

        $topTurnos = (clone $base)
            ->with(['user:id,name,email'])
            ->orderByDesc('total_tokens')
            ->limit(30)
            ->get()
            ->map(fn (GeliaAiUso $u) => $this->mapTurno($u))
            ->all();

        return Inertia::render('Admin/GeliaAi/Uso', [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'totales' => [
                'prompt_tokens' => (int) ($totales->prompt_tokens ?? 0),
                'completion_tokens' => (int) ($totales->completion_tokens ?? 0),
                'total_tokens' => (int) ($totales->total_tokens ?? 0),
                'turnos' => (int) ($totales->turnos ?? 0),
                'usuarios' => (int) ($totales->usuarios ?? 0),
            ],
            'ranking' => $rankingOut,
            'por_mode' => $porMode,
            'top_turnos' => $topTurnos,
        ]);
    }

    public function turnos(Request $request): JsonResponse
    {
        $this->authorizeAcceso();

        [$desde, $hasta] = $this->rangoFechas($request);

        $query = GeliaAiUso::query()
            ->with(['user:id,name,email'])
            ->where('created_at', '>=', $desde)
            ->where('created_at', '<=', $hasta)
            ->orderByDesc('total_tokens')
            ->orderByDesc('id');

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->query('user_id'));
        }
        if ($request->filled('mode')) {
            $query->where('mode', (string) $request->query('mode'));
        }
        if ($request->filled('min_tokens')) {
            $query->where('total_tokens', '>=', (int) $request->query('min_tokens'));
        }

        $page = $query->paginate(min(50, max(1, (int) $request->query('per_page', 20))));

        return response()->json([
            'data' => collect($page->items())->map(fn (GeliaAiUso $u) => $this->mapTurno($u))->values(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }

    public function conversacion(int $conversacion): JsonResponse
    {
        $this->authorizeAcceso();

        $conv = GeliaAiConversacion::query()->whereKey($conversacion)->firstOrFail();

        $mensajes = GeliaAiMensaje::query()
            ->where('conversacion_id', $conv->id)
            ->orderBy('id')
            ->get(['id', 'role', 'content', 'created_at'])
            ->map(fn (GeliaAiMensaje $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'created_at' => $m->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return response()->json([
            'id' => $conv->id,
            'titulo' => $conv->titulo,
            'user_id' => $conv->user_id,
            'mensajes' => $mensajes,
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function rangoFechas(Request $request): array
    {
        $hasta = $request->filled('hasta')
            ? Carbon::parse((string) $request->query('hasta'))->endOfDay()
            : now()->endOfDay();
        $desde = $request->filled('desde')
            ? Carbon::parse((string) $request->query('desde'))->startOfDay()
            : $hasta->copy()->subDays(29)->startOfDay();

        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta->copy()->startOfDay(), $desde->copy()->endOfDay()];
        }

        return [$desde, $hasta];
    }

    /** @return array<string, mixed> */
    private function mapTurno(GeliaAiUso $u): array
    {
        return [
            'id' => $u->id,
            'user_id' => $u->user_id,
            'user_name' => $u->user?->name,
            'user_email' => $u->user?->email,
            'conversacion_id' => $u->conversacion_id,
            'prompt_tokens' => $u->prompt_tokens,
            'completion_tokens' => $u->completion_tokens,
            'total_tokens' => $u->total_tokens,
            'rounds' => $u->rounds,
            'mode' => $u->mode,
            'modelo' => $u->modelo,
            'mensaje_chars' => $u->mensaje_chars,
            'reply_chars' => $u->reply_chars,
            'con_archivos' => $u->con_archivos,
            'created_at' => $u->created_at?->toIso8601String(),
        ];
    }

    private function authorizeAcceso(): void
    {
        $user = request()->user();
        abort_unless(
            $user && app(ResolverAccesoGeliaAi::class)->puedeGestionarAcceso($user),
            403
        );
    }
}
