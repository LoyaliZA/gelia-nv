<?php

namespace App\Http\Controllers\Clientes\Direcciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clientes\Direcciones\StoreSolicitudDireccionRequest;
use App\Models\ClienteDireccion;
use App\Models\SolicitudDireccion;
use App\Services\Clientes\Direcciones\CrearSolicitudDireccionService;
use App\Services\Clientes\Direcciones\ValidarEnlaceDireccionService;
use App\Support\ControlPedidos\CodigoDireccionCliente;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SolicitudDireccionPublicaController extends Controller
{
    public function show(Request $request, ValidarEnlaceDireccionService $validador, ?string $codigo = null): Response
    {
        $token = $codigo !== null && $codigo !== ''
            ? $codigo
            : (string) $request->query('token', '');
        $enlace = null;
        $clienteResumen = null;
        $direcciones = [];

        if ($token !== '') {
            try {
                $enlace = $validador->ejecutar($token);
                $cliente = $enlace->cliente;
                $clienteResumen = [
                    'nombre_enmascarado' => $this->enmascararNombre((string) $cliente->nombre),
                    'numero_enmascarado' => $this->enmascararNumero((string) $cliente->numero_cliente),
                ];
                $direcciones = ClienteDireccion::query()
                    ->where('cliente_id', $cliente->id)
                    ->activas()
                    ->orderByDesc('es_principal')
                    ->get(['id', 'numero_direccion', 'etiqueta', 'colonia', 'codigo_postal', 'es_principal'])
                    ->map(fn (ClienteDireccion $d) => [
                        'id' => $d->id,
                        'numero_direccion' => $d->numero_direccion,
                        'codigo' => CodigoDireccionCliente::formatear($cliente->numero_cliente, $d->numero_direccion),
                        'etiqueta' => $d->etiqueta,
                        'resumen' => trim(($d->colonia ?? '').' CP '.($d->codigo_postal ?? '')),
                        'es_principal' => $d->es_principal,
                    ]);
            } catch (\InvalidArgumentException) {
                $enlace = null;
            }
        }

        return Inertia::render('Clientes/Direcciones/FormularioPublico', [
            'token' => $token !== '' ? $token : null,
            'enlace_valido' => $enlace !== null,
            'cliente' => $clienteResumen,
            'direcciones' => $direcciones,
            'acciones' => [
                ['value' => SolicitudDireccion::ACCION_PRIMERA, 'label' => 'Registrar datos de envío por primera vez'],
                ['value' => SolicitudDireccion::ACCION_ADICIONAL, 'label' => 'Agregar dirección adicional'],
                ['value' => SolicitudDireccion::ACCION_ACTUALIZAR, 'label' => 'Actualizar dirección existente'],
            ],
        ]);
    }

    public function store(
        StoreSolicitudDireccionRequest $request,
        CrearSolicitudDireccionService $crear,
    ) {
        $validated = $request->validated();
        $rutaRemision = null;

        if ($request->boolean('anexa_remision') && $request->hasFile('archivo_remision')) {
            $rutaRemision = $request->file('archivo_remision')
                ->store('solicitudes_direccion/remisiones', 'local');
        }

        $datosDireccion = collect($validated)->only([
            'nombre_destinatario', 'telefono_destinatario', 'calle', 'numero_exterior',
            'numero_interior', 'colonia', 'codigo_postal', 'municipio', 'ciudad', 'estado',
            'pais', 'referencias', 'indicaciones_entrega', 'etiqueta', 'tipo_direccion',
        ])->all();

        if (! empty($validated['comentario'])) {
            $datosDireccion['indicaciones_entrega'] = trim(
                ($datosDireccion['indicaciones_entrega'] ?? '')."\n".$validated['comentario']
            );
        }

        $solicitud = $crear->ejecutar([
            'token' => $validated['token'] ?? null,
            'numero_cliente' => $validated['numero_cliente'] ?? null,
            'nombre_declarado' => $validated['nombre_declarado'],
            'telefono_declarado' => $validated['telefono_declarado'],
            'correo_declarado' => $validated['correo_declarado'] ?? null,
            'accion_solicitada' => $validated['accion_solicitada'],
            'direccion_seleccionada_id' => $validated['direccion_seleccionada_id'] ?? null,
            'anexa_remision' => $request->boolean('anexa_remision'),
            'archivo_remision' => $rutaRemision,
            'datos_direccion' => $datosDireccion,
        ], $request->ip());

        return redirect()
            ->route('direcciones.publicas.confirmacion', $solicitud->folio)
            ->with('success', 'Solicitud registrada.');
    }

    public function confirmacion(string $folio): Response
    {
        $solicitud = SolicitudDireccion::query()->where('folio', $folio)->firstOrFail();

        return Inertia::render('Clientes/Direcciones/ConfirmacionPublica', [
            'folio' => $solicitud->folio,
            'estado' => $solicitud->estado,
        ]);
    }

    private function enmascararNombre(string $nombre): string
    {
        $partes = preg_split('/\s+/', trim($nombre)) ?: [];
        $mask = [];
        foreach ($partes as $parte) {
            $mask[] = mb_substr($parte, 0, 1).str_repeat('*', max(mb_strlen($parte) - 1, 0));
        }

        return implode(' ', $mask);
    }

    private function enmascararNumero(string $numero): string
    {
        $len = mb_strlen($numero);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return str_repeat('*', $len - 4).mb_substr($numero, -4);
    }
}
