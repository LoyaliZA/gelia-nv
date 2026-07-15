<?php

namespace App\Http\Controllers\Clientes\Direcciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clientes\Direcciones\StoreDireccionPublicaDesdeEnlaceRequest;
use App\Models\ClienteDireccion;
use App\Models\SolicitudDireccion;
use App\Services\Clientes\Direcciones\AplicarDireccionPublicaDesdeEnlaceService;
use App\Services\Clientes\Direcciones\ValidarEnlaceDireccionService;
use App\Support\ControlPedidos\CodigoDireccionCliente;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SolicitudDireccionPublicaController extends Controller
{
    public function show(Request $request, ValidarEnlaceDireccionService $validador, ?string $codigo = null): Response
    {
        $token = $codigo !== null && $codigo !== ''
            ? $codigo
            : (string) $request->query('token', '');

        // Sin token único: no se abre el formulario base.
        if ($token === '') {
            return $this->vistaConfirmacion([
                'aplicado' => false,
                'enlace_invalido' => true,
                'motivo' => 'sin_token',
            ]);
        }

        $enlace = $validador->porToken($token);

        if (! $enlace) {
            return $this->vistaConfirmacion([
                'aplicado' => false,
                'enlace_invalido' => true,
                'motivo' => 'invalido',
            ]);
        }

        if ($enlace->fueUsado()) {
            return $this->vistaConfirmacion([
                'aplicado' => true,
                'ya_utilizado' => true,
                'motivo' => 'usado',
            ]);
        }

        if ($enlace->revocado_en !== null || ($enlace->expira_en !== null && $enlace->expira_en->isPast())) {
            return $this->vistaConfirmacion([
                'aplicado' => false,
                'enlace_invalido' => true,
                'motivo' => 'expirado',
            ]);
        }

        if (! filled($enlace->accion_permitida)) {
            return $this->vistaConfirmacion([
                'aplicado' => false,
                'enlace_invalido' => true,
                'motivo' => 'sin_accion',
            ]);
        }

        try {
            $enlace = $validador->ejecutar($token);
        } catch (\InvalidArgumentException) {
            return $this->vistaConfirmacion([
                'aplicado' => false,
                'enlace_invalido' => true,
                'motivo' => 'invalido',
            ]);
        }

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

        return Inertia::render('Clientes/Direcciones/FormularioPublico', [
            'token' => $token,
            'enlace_valido' => true,
            'modo_simplificado' => true,
            'cliente' => $clienteResumen,
            'direcciones' => $direcciones,
            'accion_permitida' => $enlace->accion_permitida,
            'acciones' => [[
                'value' => $enlace->accion_permitida,
                'label' => match ($enlace->accion_permitida) {
                    SolicitudDireccion::ACCION_PRIMERA => 'Registrar primera dirección',
                    SolicitudDireccion::ACCION_ACTUALIZAR => 'Actualizar dirección',
                    default => 'Añadir dirección adicional',
                },
            ]],
        ]);
    }

    public function store(
        Request $request,
        AplicarDireccionPublicaDesdeEnlaceService $aplicar,
        ValidarEnlaceDireccionService $validador,
    ) {
        $token = trim((string) ($request->input('token') ?? ''));

        if ($token === '') {
            throw ValidationException::withMessages([
                'token' => 'Se requiere un enlace válido para guardar la dirección.',
            ]);
        }

        $enlace = $validador->porToken($token);

        if (! $enlace) {
            throw ValidationException::withMessages([
                'token' => 'Enlace no válido.',
            ]);
        }

        if ($enlace->fueUsado()) {
            return redirect()
                ->route('direcciones.publicas.confirmacion', ['folio' => 'aplicado'])
                ->with('ya_utilizado', true);
        }

        $directaRequest = StoreDireccionPublicaDesdeEnlaceRequest::createFrom($request);
        $directaRequest->setContainer(app())->setRedirector(app('redirect'));
        $directaRequest->validateResolved();

        try {
            $direccion = $aplicar->ejecutar($token, $directaRequest->datosDireccion());
        } catch (\InvalidArgumentException $e) {
            if (str_contains($e->getMessage(), 'ya fue utilizado')) {
                return redirect()
                    ->route('direcciones.publicas.confirmacion', ['folio' => 'aplicado'])
                    ->with('ya_utilizado', true);
            }

            throw ValidationException::withMessages([
                'token' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('direcciones.publicas.confirmacion', ['folio' => 'aplicado'])
            ->with('direccion_aplicada_id', $direccion->id)
            ->with('aplicado_ok', true);
    }

    public function confirmacion(Request $request, string $folio): Response
    {
        if ($folio === 'aplicado') {
            return $this->vistaConfirmacion([
                'aplicado' => true,
                'ya_utilizado' => (bool) $request->session()->get('ya_utilizado', false),
                'motivo' => $request->session()->get('ya_utilizado') ? 'usado' : 'ok',
            ]);
        }

        // Folios legacy de solicitud ya no se usan en el flujo de vendedora;
        // se mantienen solo como lectura histórica.
        $solicitud = SolicitudDireccion::query()->where('folio', $folio)->first();

        if (! $solicitud) {
            return $this->vistaConfirmacion([
                'aplicado' => false,
                'enlace_invalido' => true,
                'motivo' => 'invalido',
            ]);
        }

        return $this->vistaConfirmacion([
            'folio' => $solicitud->folio,
            'estado' => $solicitud->estado,
            'aplicado' => false,
            'motivo' => 'solicitud',
        ]);
    }

    /**
     * @param  array{folio?: string|null, estado?: string|null, aplicado?: bool, ya_utilizado?: bool, enlace_invalido?: bool, motivo?: string|null}  $props
     */
    private function vistaConfirmacion(array $props): Response
    {
        return Inertia::render('Clientes/Direcciones/ConfirmacionPublica', [
            'folio' => $props['folio'] ?? null,
            'estado' => $props['estado'] ?? null,
            'aplicado' => (bool) ($props['aplicado'] ?? false),
            'ya_utilizado' => (bool) ($props['ya_utilizado'] ?? false),
            'enlace_invalido' => (bool) ($props['enlace_invalido'] ?? false),
            'motivo' => $props['motivo'] ?? null,
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
