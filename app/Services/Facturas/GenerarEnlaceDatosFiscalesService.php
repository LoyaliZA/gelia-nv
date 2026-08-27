<?php

namespace App\Services\Facturas;

use App\Models\EnlaceDatosFiscales;
use App\Models\SolicitudFactura;
use Illuminate\Support\Str;

class GenerarEnlaceDatosFiscalesService
{
    /** Alfabeto sin caracteres ambiguos (0/O, 1/l/I). */
    private const ALFABETO = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';

    /**
     * @param  array{accion: string, campos?: list<string>|null, horas?: int, usuario_id?: int|null}  $opciones
     * @return array{enlace: EnlaceDatosFiscales, token: string, url: string}
     */
    public function ejecutar(SolicitudFactura $solicitud, array $opciones = []): array
    {
        $campos = $this->normalizarCampos($opciones['campos'] ?? null);
        $accion = $opciones['accion'] ?? (
            empty($campos) || count($campos) === count(EnlaceDatosFiscales::CAMPOS)
                ? EnlaceDatosFiscales::ACCION_PRIMERA
                : EnlaceDatosFiscales::ACCION_ACTUALIZAR
        );

        if ($accion === EnlaceDatosFiscales::ACCION_PRIMERA) {
            $campos = EnlaceDatosFiscales::CAMPOS;
        }

        if ($campos === []) {
            throw new \InvalidArgumentException('Debe indicar al menos un campo fiscal.');
        }

        // Revoca enlaces vigentes previos de la misma solicitud.
        EnlaceDatosFiscales::query()
            ->where('solicitud_factura_id', $solicitud->id)
            ->whereNull('usado_en')
            ->whereNull('revocado_en')
            ->update(['revocado_en' => now()]);

        $codigo = $this->generarCodigoUnico();
        $horas = (int) ($opciones['horas'] ?? 72);

        $enlace = EnlaceDatosFiscales::query()->create([
            'solicitud_factura_id' => $solicitud->id,
            'cliente_id' => $solicitud->cliente_id,
            'token_hash' => hash('sha256', $codigo),
            'codigo_publico' => $codigo,
            'accion_permitida' => $accion,
            'campos_permitidos' => $campos,
            'destinatario_tipo' => $solicitud->destinatario_tipo ?: SolicitudFactura::DESTINATARIO_CLIENTE,
            'expira_en' => now()->addHours($horas),
            'creado_por' => $opciones['usuario_id'] ?? null,
        ]);

        $solicitud->update([
            'campos_fiscales_solicitados' => $campos,
            'formulario_enviado_at' => now(),
            'formulario_respondido_at' => null,
        ]);

        return [
            'enlace' => $enlace,
            'token' => $codigo,
            'url' => $enlace->urlPublica(),
        ];
    }

    /**
     * @param  list<string>|null  $campos
     * @return list<string>
     */
    private function normalizarCampos(?array $campos): array
    {
        if ($campos === null || $campos === []) {
            return EnlaceDatosFiscales::CAMPOS;
        }

        $permitidos = [];
        foreach ($campos as $campo) {
            $campo = (string) $campo;
            if (in_array($campo, EnlaceDatosFiscales::CAMPOS, true) && ! in_array($campo, $permitidos, true)) {
                $permitidos[] = $campo;
            }
        }

        return $permitidos;
    }

    private function generarCodigoUnico(int $longitud = 16): string
    {
        $max = strlen(self::ALFABETO) - 1;

        for ($intento = 0; $intento < 20; $intento++) {
            $codigo = '';
            for ($i = 0; $i < $longitud; $i++) {
                $codigo .= self::ALFABETO[random_int(0, $max)];
            }

            $existe = EnlaceDatosFiscales::query()
                ->where('codigo_publico', $codigo)
                ->orWhere('token_hash', hash('sha256', $codigo))
                ->exists();

            if (! $existe) {
                return $codigo;
            }
        }

        return Str::lower(Str::random(24));
    }
}
