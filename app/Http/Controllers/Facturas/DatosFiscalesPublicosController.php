<?php

namespace App\Http\Controllers\Facturas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facturas\StoreDatosFiscalesPublicosRequest;
use App\Models\EnlaceDatosFiscales;
use App\Services\Facturas\AplicarDatosFiscalesPublicosDesdeEnlaceService;
use App\Services\Facturas\ImportarDatosFiscalesService;
use App\Services\Facturas\ListarCatalogosFiscalesService;
use App\Services\Facturas\ValidarEnlaceDatosFiscalesService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DatosFiscalesPublicosController extends Controller
{
    public function show(Request $request, ValidarEnlaceDatosFiscalesService $validador, ListarCatalogosFiscalesService $catalogos, ?string $codigo = null): Response
    {
        $token = $codigo !== null && $codigo !== ''
            ? $codigo
            : (string) $request->query('token', '');

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

        try {
            $enlace = $validador->ejecutar($token);
        } catch (\InvalidArgumentException) {
            return $this->vistaConfirmacion([
                'aplicado' => false,
                'enlace_invalido' => true,
                'motivo' => 'invalido',
            ]);
        }

        $enlace->loadMissing(['cliente', 'solicitud']);
        $cliente = $enlace->cliente;
        $clienteResumen = $cliente ? [
            'nombre_enmascarado' => $this->enmascararNombre((string) $cliente->nombre),
            'numero_enmascarado' => $this->enmascararNumero((string) $cliente->numero_cliente),
        ] : null;

        $campos = is_array($enlace->campos_permitidos) && $enlace->campos_permitidos !== []
            ? $enlace->campos_permitidos
            : EnlaceDatosFiscales::CAMPOS;

        $etiquetas = app(ImportarDatosFiscalesService::class)->etiquetasParaUi();

        return Inertia::render('Clientes/DatosFiscales/FormularioPublico', [
            'token' => $token,
            'enlace_valido' => true,
            'cliente' => $clienteResumen,
            'destinatario_tipo' => $enlace->destinatario_tipo,
            'accion_permitida' => $enlace->accion_permitida,
            'campos' => collect($campos)->map(fn ($clave) => [
                'clave' => $clave,
                'etiqueta' => $etiquetas[$clave] ?? $clave,
            ])->values()->all(),
            'catalogos' => $catalogos->activosParaUi(),
        ]);
    }

    public function store(
        StoreDatosFiscalesPublicosRequest $request,
        AplicarDatosFiscalesPublicosDesdeEnlaceService $aplicar,
        ValidarEnlaceDatosFiscalesService $validador,
    ) {
        $token = trim((string) $request->input('token', ''));
        $enlace = $validador->porToken($token);

        if (! $enlace) {
            throw ValidationException::withMessages([
                'token' => 'Enlace no válido.',
            ]);
        }

        if ($enlace->fueUsado()) {
            return redirect()
                ->route('datos_fiscales.publicas.confirmacion', ['folio' => 'aplicado'])
                ->with('ya_utilizado', true);
        }

        try {
            $aplicar->ejecutar($token, $request->datosFiscales());
        } catch (\InvalidArgumentException $e) {
            if (str_contains($e->getMessage(), 'ya fue utilizado')) {
                return redirect()
                    ->route('datos_fiscales.publicas.confirmacion', ['folio' => 'aplicado'])
                    ->with('ya_utilizado', true);
            }

            throw ValidationException::withMessages([
                'token' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('datos_fiscales.publicas.confirmacion', ['folio' => 'aplicado'])
            ->with('aplicado_ok', true);
    }

    public function confirmacion(Request $request, string $folio): Response
    {
        return $this->vistaConfirmacion([
            'aplicado' => $folio === 'aplicado',
            'ya_utilizado' => (bool) $request->session()->get('ya_utilizado', false),
            'enlace_invalido' => $folio !== 'aplicado',
            'motivo' => $folio === 'aplicado'
                ? ($request->session()->get('ya_utilizado') ? 'usado' : 'ok')
                : 'invalido',
        ]);
    }

    /**
     * @param  array{aplicado?: bool, ya_utilizado?: bool, enlace_invalido?: bool, motivo?: string|null}  $props
     */
    private function vistaConfirmacion(array $props): Response
    {
        return Inertia::render('Clientes/DatosFiscales/ConfirmacionPublica', [
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
