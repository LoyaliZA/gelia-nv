<?php

namespace App\Http\Requests\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Http\Requests\PuntoVenta\PdvOperacionPisoRequest;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\RegistroManualResguardoPdvConfig;
use App\Support\PuntoVenta\Resguardos\DepartamentosOrigenResguardoManualPdv;
use App\Support\PuntoVenta\Resguardos\NormalizadorPiezasRegistroManualPdv;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\UploadedFile;

class RegistrarResguardoManualPdvRequest extends PdvOperacionPisoRequest
{
    protected function permisoAccion(): string
    {
        return PuntoVentaModulo::PERMISO_RESGUARDOS_REGISTRAR_MANUAL;
    }

    public function authorize(): bool
    {
        $actor = $this->user();
        if (! app(RegistroManualResguardoPdvConfig::class)->estaActivoPara($actor instanceof User ? $actor : null)) {
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
            'piezas.*.nota' => ['nullable', 'string', 'max:500'],
            'piezas.*.estado_fisico' => ['nullable', 'string', 'in:'.implode(',', NormalizadorPiezasRegistroManualPdv::ESTADOS)],
            'piezas.*.unica_pieza' => ['sometimes', 'boolean'],
            'piezas.*.mejor_ejemplar' => ['sometimes', 'boolean'],
            'piezas.*.evidencia_ids' => ['sometimes', 'array', 'max:8'],
            'piezas.*.evidencia_ids.*' => ['integer', 'min:1'],
            'piezas.*.evidencias' => ['sometimes', 'array', 'max:8'],
            'piezas.*.evidencias.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'archivo_ticket' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'foto_paquete' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
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
        $datos['piezas'] = $this->piezasConArchivos(
            NormalizadorPiezasRegistroManualPdv::normalizar($datos['piezas'] ?? [])
        );
        $datos['cantidad_piezas'] = NormalizadorPiezasRegistroManualPdv::cantidad(
            $datos['piezas'],
            isset($datos['cantidad_piezas']) ? (int) $datos['cantidad_piezas'] : null
        );

        return $datos;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $piezas = $this->input('piezas', []);
            if ($this->actorEnFlujoCompleto() && (! is_array($piezas) || $piezas === [])) {
                $validator->errors()->add('piezas', 'Registre al menos una pieza del pedido.');
            }

            if (! is_array($piezas)) {
                return;
            }

            foreach (array_values($piezas) as $indice => $pieza) {
                if (! is_array($pieza)) {
                    continue;
                }
                $estado = (string) ($pieza['estado_fisico'] ?? 'bueno');
                if (! NormalizadorPiezasRegistroManualPdv::requiereEvidencia($estado)) {
                    continue;
                }
                $ids = is_array($pieza['evidencia_ids'] ?? null) ? array_filter($pieza['evidencia_ids']) : [];
                if ($ids !== [] || $this->archivosPieza($indice) !== []) {
                    continue;
                }
                $validator->errors()->add(
                    "piezas.{$indice}.evidencias",
                    'Esta pieza requiere una foto del detalle.'
                );
            }
        });
    }

    /**
     * @param  list<array<string, mixed>>  $piezas
     * @return list<array<string, mixed>>
     */
    private function piezasConArchivos(array $piezas): array
    {
        foreach ($piezas as $indice => $pieza) {
            $archivos = $this->archivosPieza($indice);
            if ($archivos !== []) {
                $piezas[$indice]['evidencias'] = $archivos;
            }
        }

        return $piezas;
    }

    /**
     * @return list<UploadedFile>
     */
    private function archivosPieza(int $indice): array
    {
        $archivos = $this->file("piezas.{$indice}.evidencias");
        if ($archivos instanceof UploadedFile) {
            return [$archivos];
        }
        if (! is_array($archivos)) {
            return [];
        }

        return array_values(array_filter(
            $archivos,
            fn ($archivo) => $archivo instanceof UploadedFile
        ));
    }

    private function actorEnFlujoCompleto(): bool
    {
        $actor = $this->user();

        return $actor instanceof User
            && app(ResuelveAlcancePdv::class)->tienePermisoPdv(
                $actor,
                PuntoVentaModulo::PERMISO_RESGUARDOS_FLUJO_COMPLETO
            );
    }
}
