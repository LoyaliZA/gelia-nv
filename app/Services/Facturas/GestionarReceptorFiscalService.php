<?php

namespace App\Services\Facturas;

use App\Models\ReceptorFiscal;
use App\Support\Facturas\ReglasCatalogosFiscales;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GestionarReceptorFiscalService
{
    public function __construct(
        private GenerarCodigoReceptorFiscalService $codigos,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos): ReceptorFiscal
    {
        $payload = $this->normalizarPayload($datos);
        $this->assertMinimoIdentidad($payload);

        return DB::transaction(function () use ($payload) {
            return ReceptorFiscal::query()->create([
                ...$payload,
                'codigo_interno' => $this->codigos->siguiente(),
                'activo' => $payload['activo'] ?? true,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(ReceptorFiscal $receptor, array $datos): ReceptorFiscal
    {
        $payload = $this->normalizarPayload($datos);

        return DB::transaction(function () use ($receptor, $payload) {
            $receptor->update($payload);

            return $receptor->fresh();
        });
    }

    /**
     * Upsert por receptor existente, RFC o creación.
     *
     * @param  array<string, mixed>  $datos
     */
    public function upsertDesdeFormulario(?int $receptorId, array $datos): ReceptorFiscal
    {
        $payload = $this->normalizarPayload($datos);
        $this->assertMinimoIdentidad($payload);

        return DB::transaction(function () use ($receptorId, $payload) {
            if ($receptorId) {
                $receptor = ReceptorFiscal::query()->whereKey($receptorId)->lockForUpdate()->first();
                if ($receptor) {
                    $receptor->update($payload);

                    return $receptor->fresh();
                }
            }

            $rfc = $payload['rfc'] ?? null;
            if (is_string($rfc) && $rfc !== '') {
                $porRfc = ReceptorFiscal::query()
                    ->where('activo', true)
                    ->where('rfc', $rfc)
                    ->lockForUpdate()
                    ->get();
                if ($porRfc->count() > 1) {
                    throw ValidationException::withMessages([
                        'rfc' => 'Hay varios receptores activos con el mismo RFC; elija uno en el padrón.',
                    ]);
                }
                if ($porRfc->count() === 1) {
                    $receptor = $porRfc->first();
                    $receptor->update($payload);

                    return $receptor->fresh();
                }
            }

            return $this->crear($payload);
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function normalizarPayload(array $datos): array
    {
        $out = [];

        if (array_key_exists('rfc', $datos)) {
            $rfc = ReglasCatalogosFiscales::normalizarRfc($datos['rfc'] ?? null);
            $out['rfc'] = $rfc === '' ? null : $rfc;
        }
        if (array_key_exists('codigo_postal', $datos)) {
            $cp = preg_replace('/\D+/', '', (string) ($datos['codigo_postal'] ?? '')) ?? '';
            $out['codigo_postal'] = $cp === '' ? null : substr($cp, 0, 5);
        }
        if (array_key_exists('regimen_fiscal', $datos)) {
            $v = trim((string) ($datos['regimen_fiscal'] ?? ''));
            $out['regimen_fiscal'] = $v === '' ? null : $v;
        }
        if (array_key_exists('correo_electronico', $datos)) {
            $v = mb_strtolower(trim((string) ($datos['correo_electronico'] ?? '')));
            $out['correo_electronico'] = $v === '' ? null : $v;
        }
        if (array_key_exists('uso_factura', $datos)) {
            $v = trim((string) ($datos['uso_factura'] ?? ''));
            $out['uso_factura'] = $v === '' ? null : $v;
        }
        if (array_key_exists('nombre_razon_social', $datos)) {
            $v = ReglasCatalogosFiscales::normalizarRazonSocial($datos['nombre_razon_social'] ?? null);
            $out['nombre_razon_social'] = $v === '' ? null : $v;
        }
        if (array_key_exists('telefono', $datos)) {
            $tel = preg_replace('/\D+/', '', (string) ($datos['telefono'] ?? '')) ?? '';
            $out['telefono'] = $tel === '' ? null : substr($tel, 0, 10);
        }
        if (array_key_exists('activo', $datos)) {
            $out['activo'] = (bool) $datos['activo'];
        }
        if (array_key_exists('notas', $datos)) {
            $v = trim((string) ($datos['notas'] ?? ''));
            $out['notas'] = $v === '' ? null : $v;
        }

        return ReglasCatalogosFiscales::aplicarForzados($out);
    }

    /** @param  array<string, mixed>  $payload */
    private function assertMinimoIdentidad(array $payload): void
    {
        $rfc = $payload['rfc'] ?? null;
        $razon = $payload['nombre_razon_social'] ?? null;
        if (($rfc === null || $rfc === '') && ($razon === null || $razon === '')) {
            throw ValidationException::withMessages([
                'nombre_razon_social' => 'Indique RFC o razón social del receptor.',
            ]);
        }
    }
}
