<?php

namespace App\Services\Facturas;

use App\Models\ReceptorFiscal;
use App\Support\Facturas\ReglasCatalogosFiscales;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Rap2hpoutre\FastExcel\FastExcel;

class ImportarReceptoresFiscalesService
{
    public function __construct(
        private ResolverCodigoCatalogoFiscalService $resolverCatalogo,
        private GestionarReceptorFiscalService $gestionar,
    ) {}

    /**
     * @return array{creados: int, actualizados: int, omitidos: int, errores: list<string>}
     */
    public function ejecutar(UploadedFile $archivo): array
    {
        $path = $archivo->getRealPath();
        if (! $path || ! is_readable($path)) {
            throw ValidationException::withMessages(['archivo' => 'No se pudo leer el archivo subido.']);
        }

        $stats = ['creados' => 0, 'actualizados' => 0, 'omitidos' => 0, 'errores' => []];
        $rows = (new FastExcel)->import($path);

        foreach ($rows as $index => $row) {
            $fila = $this->normalizarHeaders($row);
            if ($this->filaVacia($fila)) {
                continue;
            }

            $numeroFila = $index + 2;
            $ref = trim((string) ($fila['RFC'] ?? ''))
                ?: trim((string) ($fila['NOMBRE (RAZON SOCIAL)'] ?? ''))
                ?: '—';

            try {
                $datos = $this->mapearCampos($fila);
                if (($datos['rfc'] ?? null) === null && ($datos['nombre_razon_social'] ?? null) === null) {
                    throw ValidationException::withMessages([
                        'nombre_razon_social' => 'Indique RFC o razón social.',
                    ]);
                }

                $this->validar($datos);
                $existente = $this->buscarExistente($datos);

                if ($existente) {
                    $this->gestionar->actualizar($existente, $datos);
                    $stats['actualizados']++;
                } else {
                    $this->gestionar->crear($datos);
                    $stats['creados']++;
                }
            } catch (ValidationException $e) {
                $mensaje = collect($e->errors())->flatten()->first() ?: $e->getMessage();
                $stats['errores'][] = "Fila {$numeroFila} ({$ref}): {$mensaje}";
            } catch (\Throwable $e) {
                $stats['errores'][] = "Fila {$numeroFila} ({$ref}): {$e->getMessage()}";
            }
        }

        $stats['errores'] = array_slice($stats['errores'], 0, 50);

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function buscarExistente(array $datos): ?ReceptorFiscal
    {
        $rfc = $datos['rfc'] ?? null;
        if (is_string($rfc) && $rfc !== '') {
            $matches = ReceptorFiscal::query()
                ->where('activo', true)
                ->where('rfc', $rfc)
                ->get();
            if ($matches->count() > 1) {
                throw ValidationException::withMessages([
                    'rfc' => "RFC {$rfc} duplicado en varios receptores activos.",
                ]);
            }
            if ($matches->count() === 1) {
                return $matches->first();
            }
        }

        $razon = $datos['nombre_razon_social'] ?? null;
        if (is_string($razon) && $razon !== '') {
            return ReceptorFiscal::query()
                ->where('activo', true)
                ->where('nombre_razon_social', $razon)
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function mapearCampos(array $fila): array
    {
        $datos = [];

        $razon = trim((string) ($fila['NOMBRE (RAZON SOCIAL)'] ?? ''));
        if ($razon !== '') {
            $datos['nombre_razon_social'] = ReglasCatalogosFiscales::normalizarRazonSocial($razon);
        }

        $rfc = trim((string) ($fila['RFC'] ?? ''));
        if ($rfc !== '') {
            $datos['rfc'] = ReglasCatalogosFiscales::normalizarRfc($rfc);
        }

        $cp = trim((string) ($fila['CODIGO POSTAL'] ?? ''));
        if ($cp !== '') {
            $datos['codigo_postal'] = preg_replace('/\D+/', '', $cp) ?? '';
        }

        $regimenRaw = trim((string) ($fila['REGIMEN FISCAL'] ?? ''));
        if ($regimenRaw !== '') {
            $codigo = $this->resolverCatalogo->regimen($regimenRaw);
            if ($codigo === null) {
                throw ValidationException::withMessages([
                    'regimen_fiscal' => "Régimen fiscal no válido: {$regimenRaw}",
                ]);
            }
            $datos['regimen_fiscal'] = $codigo;
        }

        $correo = trim((string) ($fila['CORREO ELECTRONICO'] ?? ''));
        if ($correo !== '') {
            $datos['correo_electronico'] = mb_strtolower($correo);
        }

        $usoRaw = trim((string) ($fila['USO DE FACTURA'] ?? ''));
        if ($usoRaw !== '') {
            $codigo = $this->resolverCatalogo->uso($usoRaw);
            if ($codigo === null) {
                throw ValidationException::withMessages([
                    'uso_factura' => "Uso de factura no válido: {$usoRaw}",
                ]);
            }
            $datos['uso_factura'] = $codigo;
        }

        $tel = trim((string) ($fila['NUMERO TELEFONICO'] ?? ''));
        if ($tel !== '') {
            $datos['telefono'] = preg_replace('/\D+/', '', $tel) ?? '';
        }

        return ReglasCatalogosFiscales::aplicarForzados($datos);
    }

    /** @param  array<string, mixed>  $datos */
    private function validar(array $datos): void
    {
        $v = Validator::make($datos, [
            'rfc' => ['nullable', 'string', 'max:13'],
            'codigo_postal' => ['nullable', 'regex:/^\d{5}$/'],
            'regimen_fiscal' => ['nullable', 'string', 'max:10', Rule::exists('catalogo_regimen_fiscal', 'codigo')->where('activo', true)],
            'correo_electronico' => ['nullable', 'email:filter', 'max:255'],
            'uso_factura' => ['nullable', 'string', 'max:10', Rule::exists('catalogo_uso_cfdi', 'codigo')->where('activo', true)],
            'nombre_razon_social' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'regex:/^\d{1,10}$/'],
        ]);

        $v->after(function ($validator) use ($datos) {
            if ($err = ReglasCatalogosFiscales::errorRfc($datos['rfc'] ?? null)) {
                $validator->errors()->add('rfc', $err);
            }
        });

        $v->validate();
    }

    /** @param  array<string, mixed>  $row @return array<string, string> */
    private function normalizarHeaders(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            $key = mb_strtoupper(preg_replace('/\s+/u', ' ', trim((string) $k)) ?? '', 'UTF-8');
            $out[$key] = is_scalar($v) ? (string) $v : '';
        }

        return $out;
    }

    /** @param  array<string, mixed>  $fila */
    private function filaVacia(array $fila): bool
    {
        foreach ($fila as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }
}
