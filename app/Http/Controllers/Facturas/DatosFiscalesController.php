<?php

namespace App\Http\Controllers\Facturas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facturas\UpdateDatosFiscalesRequest;
use App\Models\Cliente;
use App\Services\Facturas\GestionarDatosFiscalesClienteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DatosFiscalesController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('facturas.gestionar_datos_fiscales');

        $query = Cliente::query()
            ->select([
                'id', 'numero_cliente', 'nombre',
                'rfc', 'codigo_postal', 'regimen_fiscal',
                'correo_electronico', 'uso_factura', 'nombre_razon_social',
                'telefono',
            ])
            ->orderBy('numero_cliente');

        if ($request->filled('q')) {
            $q = trim($request->q);
            $query->where(function ($sub) use ($q) {
                $sub->where('numero_cliente', 'like', "%{$q}%")
                    ->orWhere('nombre', 'like', "%{$q}%")
                    ->orWhere('rfc', 'like', "%{$q}%")
                    ->orWhere('nombre_razon_social', 'like', "%{$q}%");
            });
        }

        return Inertia::render('Facturas/DatosFiscales/Index', [
            'clientes' => $query->paginate(20)->withQueryString(),
            'filtros' => $request->only(['q']),
        ]);
    }

    public function update(
        UpdateDatosFiscalesRequest $request,
        Cliente $cliente,
        GestionarDatosFiscalesClienteService $service
    ): RedirectResponse {
        $service->actualizar($cliente, $request->validated());

        return redirect()->back()->with('success', 'Datos fiscales actualizados.');
    }
}
