<?php

namespace App\Http\Requests\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MarcarEmpacadoPedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.cedis') ?? false;
    }

    public function rules(): array
    {
        return [
            'bultos_por_pedido' => ['nullable', 'array'],
            'bultos_por_pedido.*' => ['required', 'array', 'min:1', 'max:50'],
            'bultos_por_pedido.*.*.foto_bulto' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'bultos_por_pedido.*.*.foto_ticket' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var PedidoBma|null $pedido */
            $pedido = $this->route('pedidoBma');
            if (! $pedido instanceof PedidoBma) {
                return;
            }

            $pedido->loadMissing([
                'estatus', 'paqueteria', 'origen',
                'complementos.estatus', 'complementos.paqueteria', 'complementos.origen',
                'tareaPreparacionVigente.modalidad',
            ]);

            $raiz = $pedido->raizEmpaque()->loadMissing([
                'estatus', 'paqueteria', 'origen',
                'complementos.estatus', 'complementos.paqueteria', 'complementos.origen',
                'tareaPreparacionVigente.modalidad',
            ]);

            $grupo = collect([$raiz])->merge($raiz->complementos ?? []);
            $requierenBultos = $grupo->filter(
                fn (PedidoBma $miembro) => $miembro->esGestionablePorCedis()
                    && $miembro->puedeMarcarEmpacado()
                    && $miembro->requiereSucursalDestino()
            );

            if ($requierenBultos->isEmpty()) {
                return;
            }

            $payload = $this->file('bultos_por_pedido', []);
            if (! is_array($payload) || $payload === []) {
                $validator->errors()->add(
                    'bultos_por_pedido',
                    'Debe registrar los bultos y evidencias fotográficas de cada pedido con sucursal destino.'
                );

                return;
            }

            foreach ($requierenBultos as $miembro) {
                $key = (string) $miembro->id;
                $bultos = $payload[$key] ?? $payload[$miembro->id] ?? null;

                if (! is_array($bultos) || count($bultos) < 1) {
                    $folio = $miembro->folio ?: $miembro->id;
                    $validator->errors()->add(
                        "bultos_por_pedido.{$key}",
                        "Registre al menos un bulto con fotos para el pedido {$folio}."
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'bultos_por_pedido.*.min' => 'Cada pedido debe tener al menos un bulto registrado.',
            'bultos_por_pedido.*.*.foto_bulto.required' => 'Cada bulto debe incluir foto del bulto.',
            'bultos_por_pedido.*.*.foto_ticket.required' => 'Cada bulto debe incluir foto del ticket.',
            'bultos_por_pedido.*.*.foto_bulto.mimes' => 'Las fotos del bulto deben ser JPG, PNG o WEBP.',
            'bultos_por_pedido.*.*.foto_ticket.mimes' => 'Las fotos del ticket deben ser JPG, PNG o WEBP.',
            'bultos_por_pedido.*.*.foto_bulto.max' => 'Cada foto del bulto no debe superar 5 MB.',
            'bultos_por_pedido.*.*.foto_ticket.max' => 'Cada foto del ticket no debe superar 5 MB.',
        ];
    }

    /**
     * @return array<int, list<array{foto_bulto: \Illuminate\Http\UploadedFile, foto_ticket: \Illuminate\Http\UploadedFile}>>
     */
    public function bultosPorPedidoNormalizados(): array
    {
        $payload = $this->file('bultos_por_pedido', []);
        if (! is_array($payload)) {
            return [];
        }

        $normalizado = [];
        foreach ($payload as $pedidoId => $bultos) {
            if (! is_array($bultos)) {
                continue;
            }
            $filas = [];
            foreach (array_values($bultos) as $fila) {
                if (! is_array($fila)) {
                    continue;
                }
                $filas[] = [
                    'foto_bulto' => $fila['foto_bulto'] ?? null,
                    'foto_ticket' => $fila['foto_ticket'] ?? null,
                ];
            }
            if ($filas !== []) {
                $normalizado[(int) $pedidoId] = $filas;
            }
        }

        return $normalizado;
    }
}
