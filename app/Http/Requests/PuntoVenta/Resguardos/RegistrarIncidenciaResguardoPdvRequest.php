<?php

namespace App\Http\Requests\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\User;
use App\Support\PuntoVenta\Resguardos\IncidenciaResguardoPdv;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarIncidenciaResguardoPdvRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        $permiso = IncidenciaResguardoPdv::permisoRegistro((string) $this->input('tipo', ''));
        if ($permiso === null) {
            return true;
        }

        $resguardo = $this->route('resguardo');

        return app(ResuelveAlcancePdv::class)->permiteMutacionPiso(
            $user,
            $permiso,
            $resguardo instanceof ResguardoPdv ? (int) $resguardo->sucursal_id : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tipo = (string) $this->input('tipo', '');
        $exigeEvidencia = IncidenciaResguardoPdv::exigeEvidencia($tipo);

        return [
            'version' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'tipo' => ['required', 'string', Rule::in(IncidenciaResguardoPdv::tiposRegistrables())],
            'descripcion' => ['required', 'string', 'max:5000'],
            'bulto_id' => ['sometimes', 'nullable', 'integer', 'exists:pdv_resguardo_bultos,id'],
            'bulto' => ['sometimes', 'nullable', 'array'],
            'bulto.folio' => ['required_with:bulto', 'string', 'max:64'],
            'bulto.tipo' => ['required_with:bulto', 'string', Rule::in([
                ResguardoPdvBulto::TIPO_CAJA,
                ResguardoPdvBulto::TIPO_BOLSA,
            ])],
            'bulto.condicion' => ['required_with:bulto', 'string', 'max:64'],
            'bulto.piezas' => ['sometimes', 'integer', 'min:1'],
            'almacen_id' => ['required_with:bulto', 'integer', 'exists:almacenes,id'],
            'evidencias' => [$exigeEvidencia ? 'required' : 'sometimes', 'array', $exigeEvidencia ? 'min:1' : ''],
            'evidencias.*' => ['file', 'image', 'max:10240'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadOperacion(): array
    {
        return $this->validated();
    }
}
