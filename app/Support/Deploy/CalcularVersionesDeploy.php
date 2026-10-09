<?php

namespace App\Support\Deploy;

final class CalcularVersionesDeploy
{
    private const ENTRADA = 'resources/js/app.jsx';

    private const SALA = [
        'resources/js/Pages/PuntoVenta/Pantallas/Sala.jsx',
    ];

    private const TURNOS = [
        'resources/js/Pages/PuntoVenta/Turnos/Recepcion.jsx',
        'resources/js/Pages/PuntoVenta/Turnos/Ventas.jsx',
    ];

    /**
     * @return array{shell: string, surfaces: array{sala: string, turnos: string}}
     */
    public static function actual(?string $rutaManifiesto = null): array
    {
        $ruta = $rutaManifiesto ?? public_path('build/manifest.json');
        if (! is_file($ruta)) {
            return self::desarrollo();
        }

        $json = json_decode((string) file_get_contents($ruta), true);

        return self::desdeManifiesto(is_array($json) ? $json : []);
    }

    /**
     * @param  array<string, mixed>  $manifiesto
     * @return array{shell: string, surfaces: array{sala: string, turnos: string}}
     */
    public static function desdeManifiesto(array $manifiesto): array
    {
        $entrada = self::textoConfig('deploy_superficies.entrada', self::ENTRADA);
        $paginasSala = self::listaConfig('deploy_superficies.sala', self::SALA);
        $paginasTurnos = self::listaConfig('deploy_superficies.turnos', self::TURNOS);

        return [
            'shell' => self::hashArchivos(self::cierre(
                $manifiesto,
                [$entrada],
                false,
            )),
            'surfaces' => [
                'sala' => self::hashArchivos(self::cierre($manifiesto, $paginasSala, true)),
                'turnos' => self::hashArchivos(self::cierre($manifiesto, $paginasTurnos, true)),
            ],
        ];
    }

    /**
     * @return array{shell: string, surfaces: array{sala: string, turnos: string}}
     */
    private static function textoConfig(string $clave, string $respaldo): string
    {
        $valor = config($clave);

        return is_string($valor) && $valor !== '' ? $valor : $respaldo;
    }

    /**
     * @param  list<string>  $respaldo
     * @return list<string>
     */
    private static function listaConfig(string $clave, array $respaldo): array
    {
        $valor = config($clave);
        if (! is_array($valor) || $valor === []) {
            return $respaldo;
        }

        $lista = [];
        foreach ($valor as $item) {
            if (is_string($item) && $item !== '') {
                $lista[] = $item;
            }
        }

        return $lista === [] ? $respaldo : $lista;
    }

    private static function desarrollo(): array
    {
        $version = (string) config('app.version', 'dev');

        return [
            'shell' => $version,
            'surfaces' => [
                'sala' => $version,
                'turnos' => $version,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $manifiesto
     * @param  list<string>  $raices
     * @return list<string>
     */
    private static function cierre(array $manifiesto, array $raices, bool $incluirDinamicos): array
    {
        $vistos = [];
        $cola = [];

        foreach ($raices as $raiz) {
            if (isset($manifiesto[$raiz])) {
                $cola[] = $raiz;
            }
        }

        while ($cola !== []) {
            $clave = array_shift($cola);
            if (! is_string($clave) || isset($vistos[$clave])) {
                continue;
            }

            $vistos[$clave] = true;
            $entrada = $manifiesto[$clave] ?? null;
            if (! is_array($entrada)) {
                continue;
            }

            foreach (self::clavesImport($entrada, 'imports') as $importada) {
                $cola[] = $importada;
            }

            if ($incluirDinamicos) {
                foreach (self::clavesImport($entrada, 'dynamicImports') as $importada) {
                    $cola[] = $importada;
                }
            }
        }

        $archivos = [];
        foreach (array_keys($vistos) as $clave) {
            $entrada = $manifiesto[$clave] ?? null;
            if (! is_array($entrada)) {
                continue;
            }
            $archivo = $entrada['file'] ?? null;
            if (! is_string($archivo) || $archivo === '' || str_ends_with($archivo, '.css')) {
                continue;
            }
            $archivos[] = $archivo;
        }

        sort($archivos);

        return $archivos;
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @return list<string>
     */
    private static function clavesImport(array $entrada, string $campo): array
    {
        $lista = $entrada[$campo] ?? [];
        if (! is_array($lista)) {
            return [];
        }

        $claves = [];
        foreach ($lista as $item) {
            if (is_string($item) && $item !== '') {
                $claves[] = $item;
            }
        }

        return $claves;
    }

    /**
     * @param  list<string>  $archivos
     */
    private static function hashArchivos(array $archivos): string
    {
        if ($archivos === []) {
            return (string) config('app.version', 'dev');
        }

        return substr(hash('sha256', implode("\n", $archivos)), 0, 16);
    }
}
