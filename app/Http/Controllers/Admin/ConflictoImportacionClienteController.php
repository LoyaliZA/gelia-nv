<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConflictoImportacionCliente;
use App\Services\Clientes\ResolverConflictoImportacion;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConflictoImportacionClienteController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->can('clientes.resolver_conflicto_importacion'), 403);

        return Inertia::render('Admin/ConflictosImportacionClientes', [
            'conflictos' => ConflictoImportacionCliente::query()
                ->with('cliente:id,nombre,numero_cliente')
                ->where('estado', ConflictoImportacionCliente::PENDIENTE)
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function resolver(Request $request, ConflictoImportacionCliente $conflicto, ResolverConflictoImportacion $resolver)
    {
        abort_unless($request->user()?->can('clientes.resolver_conflicto_importacion'), 403);
        $datos = $request->validate(['decision' => 'required|in:conservar,aplicar']);
        $resolver->resolver($request->user(), $conflicto, $datos['decision']);

        return back()->with('success', 'La decisión quedó registrada.');
    }
}
