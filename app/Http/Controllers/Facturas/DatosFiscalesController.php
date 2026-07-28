<?php

namespace App\Http\Controllers\Facturas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facturas\StoreReceptorFiscalRequest;
use App\Http\Requests\Facturas\UpdateDatosFiscalesRequest;
use App\Models\Cliente;
use App\Models\ReceptorFiscal;
use App\Services\Facturas\GestionarDatosFiscalesClienteService;
use App\Services\Facturas\GestionarReceptorFiscalService;
use App\Services\Facturas\ImportarDatosFiscalesMasivoService;
use App\Services\Facturas\ImportarReceptoresFiscalesService;
use App\Services\Facturas\ListarCatalogosFiscalesService;
use App\Services\Facturas\PlantillaImportacionDatosFiscalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatosFiscalesController extends Controller
{
    public function index(Request $request, ListarCatalogosFiscalesService $catalogos): Response
    {
        Gate::authorize('facturas.gestionar_datos_fiscales');

        $tab = $request->input('tab', 'clientes') === 'receptores' ? 'receptores' : 'clientes';

        $clientes = null;
        $receptores = null;

        if ($tab === 'clientes') {
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

            $clientes = $query->paginate(20)->withQueryString();
        } else {
            $query = ReceptorFiscal::query()->orderByDesc('id');

            if ($request->filled('q')) {
                $q = trim($request->q);
                $query->where(function ($sub) use ($q) {
                    $sub->where('codigo_interno', 'like', "%{$q}%")
                        ->orWhere('rfc', 'like', "%{$q}%")
                        ->orWhere('nombre_razon_social', 'like', "%{$q}%");
                });
            }

            $receptores = $query->paginate(20)->withQueryString();
        }

        return Inertia::render('Facturas/DatosFiscales/Index', [
            'tab' => $tab,
            'clientes' => $clientes,
            'receptores' => $receptores,
            'filtros' => $request->only(['q', 'tab']),
            'catalogos' => $catalogos->activosParaUi(),
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

    public function storeReceptor(
        StoreReceptorFiscalRequest $request,
        GestionarReceptorFiscalService $service
    ): RedirectResponse {
        $service->crear($request->validated());

        return redirect()
            ->route('facturas.datos_fiscales.index', ['tab' => 'receptores'])
            ->with('success', 'Receptor fiscal creado.');
    }

    public function updateReceptor(
        StoreReceptorFiscalRequest $request,
        ReceptorFiscal $receptor,
        GestionarReceptorFiscalService $service
    ): RedirectResponse {
        $service->actualizar($receptor, $request->validated());

        return redirect()->back()->with('success', 'Receptor fiscal actualizado.');
    }

    public function buscarReceptores(Request $request): JsonResponse
    {
        if (! Gate::any(['facturas.gestionar_datos_fiscales', 'facturas.crear'])) {
            abort(403);
        }
        $q = trim((string) $request->input('q', ''));
        $clienteId = $request->integer('cliente_id') ?: null;

        $query = ReceptorFiscal::query()
            ->where('activo', true)
            ->orderBy('nombre_razon_social')
            ->limit(20);

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('codigo_interno', 'like', "%{$q}%")
                    ->orWhere('rfc', 'like', "%{$q}%")
                    ->orWhere('nombre_razon_social', 'like', "%{$q}%");
            });
        } elseif ($clienteId) {
            $query->whereHas('clientes', fn ($c) => $c->where('clientes.id', $clienteId));
        }

        $items = $query->get([
            'id', 'codigo_interno', 'rfc', 'nombre_razon_social',
            'regimen_fiscal', 'uso_factura', 'codigo_postal',
            'correo_electronico', 'telefono',
        ]);

        $vinculados = [];
        if ($clienteId) {
            $vinculados = ReceptorFiscal::query()
                ->where('activo', true)
                ->whereHas('clientes', fn ($c) => $c->where('clientes.id', $clienteId))
                ->pluck('id')
                ->all();
        }

        return response()->json([
            'data' => $items,
            'vinculados_ids' => $vinculados,
        ]);
    }

    public function plantillaClientes(PlantillaImportacionDatosFiscalesService $plantilla): StreamedResponse
    {
        Gate::authorize('facturas.gestionar_datos_fiscales');

        return $plantilla->descargarClientes();
    }

    public function plantillaReceptores(PlantillaImportacionDatosFiscalesService $plantilla): StreamedResponse
    {
        Gate::authorize('facturas.gestionar_datos_fiscales');

        return $plantilla->descargarReceptores();
    }

    public function importarClientes(Request $request, ImportarDatosFiscalesMasivoService $service): RedirectResponse
    {
        Gate::authorize('facturas.gestionar_datos_fiscales');

        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $stats = $service->ejecutar($request->file('archivo'));

        return redirect()->back()->with('reporte_importacion_datos_fiscales', $stats);
    }

    public function importarReceptores(Request $request, ImportarReceptoresFiscalesService $service): RedirectResponse
    {
        Gate::authorize('facturas.gestionar_datos_fiscales');

        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $stats = $service->ejecutar($request->file('archivo'));

        return redirect()->back()->with('reporte_importacion_receptores', $stats);
    }
}
