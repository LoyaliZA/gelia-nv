<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracionSistema;
use App\Models\User;
use App\Services\GeliaAi\ResolverAccesoGeliaAi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GeliaAiAccesoController extends Controller
{
    public function index(ResolverAccesoGeliaAi $acceso): Response
    {
        $this->authorizeAcceso();

        $userIds = $acceso->userIdsPermitidos();
        $usuarios = $userIds === []
            ? []
            : User::query()
                ->whereIn('id', $userIds)
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
                ->values()
                ->all();

        return Inertia::render('Admin/GeliaAi/Acceso', [
            'acceso_modo' => $acceso->modo(),
            'usuarios' => $usuarios,
            'modos' => [
                ResolverAccesoGeliaAi::MODO_GENERAL,
                ResolverAccesoGeliaAi::MODO_USUARIOS,
                ResolverAccesoGeliaAi::MODO_SUPER_ADMIN,
            ],
        ]);
    }

    public function update(Request $request, ResolverAccesoGeliaAi $acceso)
    {
        $this->authorizeAcceso();

        $validated = $request->validate([
            'acceso_modo' => ['required', Rule::in([
                ResolverAccesoGeliaAi::MODO_GENERAL,
                ResolverAccesoGeliaAi::MODO_USUARIOS,
                ResolverAccesoGeliaAi::MODO_SUPER_ADMIN,
            ])],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $modo = $validated['acceso_modo'];
        $ids = array_values(array_unique(array_map('intval', $validated['user_ids'] ?? [])));

        if ($modo !== ResolverAccesoGeliaAi::MODO_USUARIOS) {
            $ids = [];
        }

        $this->upsertConfig('gelia_ai.acceso_modo', $modo, 'string', 'Gelia AI', 'Quién puede usar el chat: general | usuarios | super_admin');
        $this->upsertConfig(
            'gelia_ai.acceso_user_ids',
            json_encode($ids),
            'json',
            'Gelia AI',
            'IDs de usuarios con acceso (solo si acceso_modo=usuarios)'
        );

        Cache::forget('configuraciones_sistema_globales');

        return redirect()
            ->route('admin.gelia_ai.acceso.index')
            ->with('success', 'Acceso de GELIA AI actualizado.');
    }

    public function buscarUsuarios(Request $request)
    {
        $this->authorizeAcceso();

        $q = trim((string) $request->query('q', ''));

        $query = User::query()->orderBy('name')->limit(25)->select(['id', 'name', 'email']);
        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        return response()->json($query->get());
    }

    private function authorizeAcceso(): void
    {
        $user = request()->user();
        abort_unless(
            $user && (app(ResolverAccesoGeliaAi::class)->puedeGestionarAcceso($user)),
            403
        );
    }

    private function upsertConfig(string $clave, string $valor, string $tipo, string $grupo, string $descripcion): void
    {
        ConfiguracionSistema::updateOrCreate(
            ['clave' => $clave],
            [
                'valor' => $valor,
                'tipo' => $tipo,
                'grupo' => $grupo,
                'descripcion' => $descripcion,
            ]
        );
    }
}
