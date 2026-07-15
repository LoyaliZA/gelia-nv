<?php

namespace App\Services\Clientes\Direcciones;

use App\Models\Cliente;
use App\Models\EnlaceDireccion;
use Illuminate\Support\Str;

class GenerarEnlaceDireccionService
{
    /** Alfabeto sin caracteres ambiguos (0/O, 1/l/I). */
    private const ALFABETO = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';

    public function __construct(
        private ServicioAuditoriaDireccion $auditoria,
    ) {}

    /**
     * @param  array{accion?: string|null, direccion_id?: int|null, horas?: int, usuario_id?: int|null}  $opciones
     * @return array{enlace: EnlaceDireccion, token: string, url: string}
     */
    public function ejecutar(Cliente $cliente, array $opciones = []): array
    {
        $codigo = $this->generarCodigoUnico();
        $horas = (int) ($opciones['horas'] ?? 72);

        $enlace = EnlaceDireccion::query()->create([
            'cliente_id' => $cliente->id,
            'token_hash' => hash('sha256', $codigo),
            'codigo_publico' => $codigo,
            'accion_permitida' => $opciones['accion'] ?? null,
            'direccion_id' => $opciones['direccion_id'] ?? null,
            'expira_en' => now()->addHours($horas),
            'creado_por' => $opciones['usuario_id'] ?? null,
        ]);

                $this->auditoria->ejecutar(
            $cliente->id,
            'generar_enlace',
            $opciones['usuario_id'] ?? null,
            $opciones['direccion_id'] ?? null,
            null,
            null,
            [
                'enlace_id' => $enlace->id,
                'accion' => $enlace->accion_permitida,
                'expira_en' => $enlace->expira_en?->toIso8601String(),
            ],
            'sistema',
        );

        return [
            'enlace' => $enlace,
            'token' => $codigo,
            'url' => $enlace->urlPublica(),
        ];
    }

    private function generarCodigoUnico(int $longitud = 16): string
    {
        $max = strlen(self::ALFABETO) - 1;

        for ($intento = 0; $intento < 20; $intento++) {
            $codigo = '';
            for ($i = 0; $i < $longitud; $i++) {
                $codigo .= self::ALFABETO[random_int(0, $max)];
            }

            $existe = EnlaceDireccion::query()
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
