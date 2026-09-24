<?php

namespace App\Http\Requests\PuntoVenta\Resguardos;

use App\Http\Requests\PuntoVenta\PdvOperacionPisoRequest;
use App\Models\Producto;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\RegistroManualResguardoPdvConfig;
use App\Support\PuntoVenta\Resguardos\DepartamentosOrigenResguardoManualPdv;

class RegistrarResguardoManualPdvRequest extends PdvOperacionPisoRequest
{
    protected function permisoAccion(): string
    {
        return PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR_GERENTE;
    }

    public function authorize(): bool
    {
        if (! app(RegistroManualResguardoPdvConfig::class)->estaActivo()) {
            return false;
        }

        return parent::authorize();
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('envia_a_otra_persona')) {
            $this->merge([
                'envia_a_otra_persona' => filter_var(
                    $this->input('envia_a_otra_persona'),
                    FILTER_VALIDATE_BOOLEAN
                ),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:64'],
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'folio' => ['required', 'string', 'min:1', 'max:64'],
            'origen_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (DepartamentosOrigenResguardoManualPdv::encontrarActivo((int) $value) === null) {
                        $fail('Seleccione un área de origen válida.');
                    }
                },
            ],
            'cantidad_bultos_esperada' => ['required', 'integer', 'min:1', 'max:500'],
            'envia_a_otra_persona' => ['sometimes', 'boolean'],
            'envia_otra_persona' => [
                'nullable',
                'string',
                'max:255',
                'required_if:envia_a_otra_persona,true',
            ],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'cantidad_piezas' => ['nullable', 'integer', 'min:1', 'max:99999'],
            'piezas' => ['sometimes', 'array', 'max:200'],
            'piezas.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'piezas.*.cantidad' => ['required', 'integer', 'min:1', 'max:9999'],
            'archivo_ticket' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'foto_paquete' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadOperacion(): array
    {
        $datos = $this->validated();
        $datos['folio'] = trim((string) $datos['folio']);
        $datos['envia_a_otra_persona'] = (bool) ($datos['envia_a_otra_persona'] ?? false);
        $datos['envia_otra_persona'] = $datos['envia_a_otra_persona']
            ? trim((string) ($datos['envia_otra_persona'] ?? ''))
            : null;
        $datos['observaciones'] = filled($datos['observaciones'] ?? null)
            ? trim((string) $datos['observaciones'])
            : null;
        $datos['piezas'] = $this->normalizarPiezas($datos['piezas'] ?? []);
        $datos['cantidad_piezas'] = $this->resolverCantidadPiezas(
            $datos['piezas'],
            isset($datos['cantidad_piezas']) ? (int) $datos['cantidad_piezas'] : null
        );

        return $datos;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array{producto_id: int, sku: string|null, descripcion: string|null, cantidad: int}>
     */
    private function normalizarPiezas(array $filas): array
    {
        if ($filas === []) {
            return [];
        }

        $ids = array_values(array_unique(array_map(
            static fn (array $fila): int => (int) ($fila['producto_id'] ?? 0),
            $filas
        )));
        $productos = Producto::query()
            ->whereIn('id', $ids)
            ->get(['id', 'sku', 'descripcion'])
            ->keyBy('id');

        $normalizadas = [];
        foreach ($filas as $fila) {
            $productoId = (int) ($fila['producto_id'] ?? 0);
            $producto = $productos->get($productoId);
            if ($producto === null) {
                continue;
            }

            $normalizadas[] = [
                'producto_id' => $producto->id,
                'sku' => $producto->sku,
                'descripcion' => $producto->descripcion,
                'cantidad' => (int) $fila['cantidad'],
            ];
        }

        return $normalizadas;
    }

    /**
     * @param  list<array{cantidad: int}>  $piezas
     */
    private function resolverCantidadPiezas(array $piezas, ?int $cantidadDeclarada): ?int
    {
        if ($piezas !== []) {
            return array_sum(array_column($piezas, 'cantidad'));
        }

        return $cantidadDeclarada !== null && $cantidadDeclarada > 0
            ? $cantidadDeclarada
            : null;
    }
}
