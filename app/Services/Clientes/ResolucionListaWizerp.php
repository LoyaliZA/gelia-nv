<?php

namespace App\Services\Clientes;

use App\Models\CatalogoListaDescuento;
use Illuminate\Support\Collection;

/**
 * Traducción de códigos de lista del ERP (Wizerp) a nombres del catálogo GELIA.
 * Misma tabla que usa la importación masiva de clientes.
 */
class ResolucionListaWizerp
{
    /** @var array<string, string> */
    private const MAPA_CODIGO_A_NOMBRE = [
        'PG' => 'PUBLICO GENERAL',
        '7' => 'COLABORADORES',
        '5' => 'PLATAFORMAS',
        '4' => 'MAYOREO DIAMANTE',
        '3' => 'MAYOREO PLATA',
        '2' => 'MAYOREO BRONCE',
        '1' => 'MAYOREO ORO',
        'DIAMANTE' => 'MAYOREO DIAMANTE',
        'ORO' => 'MAYOREO ORO',
        'PLATA' => 'MAYOREO PLATA',
        'BRONCE' => 'MAYOREO BRONCE',
        'COLABORADORES' => 'COLABORADORES',
        'PLATAFORMAS' => 'PLATAFORMAS',
        'PUBLICO GENERAL' => 'PUBLICO GENERAL',
    ];

    /** @var list<string> */
    private const NOMBRES_SIN_ESCALONAMIENTO = [
        'COLABORADORES',
        'PLATAFORMAS',
    ];

    public function normalizarCodigo(string $raw): string
    {
        $codigo = strtoupper(trim($raw));

        if (preg_match('/^(\d+)\.0+$/', $codigo, $matches)) {
            return $matches[1];
        }

        if (is_numeric($codigo) && ! isset(self::MAPA_CODIGO_A_NOMBRE[$codigo])) {
            return (string) (int) (float) $codigo;
        }

        return $codigo;
    }

    public function nombreDesdeCodigo(?string $codigoRaw): ?string
    {
        if ($codigoRaw === null || trim($codigoRaw) === '') {
            return null;
        }

        $codigo = $this->normalizarCodigo($codigoRaw);

        return self::MAPA_CODIGO_A_NOMBRE[$codigo] ?? null;
    }

    /**
     * @param  Collection<int, CatalogoListaDescuento>|array<int, CatalogoListaDescuento>  $listas
     */
    public function resolverListaId(Collection|array $listas, ?string $codigoRaw): ?int
    {
        $nombre = $this->nombreDesdeCodigo($codigoRaw);
        if ($nombre === null) {
            return null;
        }

        $coleccion = $listas instanceof Collection ? $listas : collect($listas);
        $lista = $coleccion->firstWhere('nombre', $nombre);

        return $lista ? (int) $lista->id : null;
    }

    public function nombreExcluyeEscalonamiento(?string $nombreLista): bool
    {
        if ($nombreLista === null || trim($nombreLista) === '') {
            return false;
        }

        return in_array(strtoupper(trim($nombreLista)), self::NOMBRES_SIN_ESCALONAMIENTO, true);
    }

    public function listaParticipaEnEscalonamiento(?CatalogoListaDescuento $lista): bool
    {
        if ($lista === null || ! $lista->activo) {
            return false;
        }

        if ($this->nombreExcluyeEscalonamiento($lista->nombre)) {
            return false;
        }

        return (bool) $lista->participa_escalonamiento;
    }
}
