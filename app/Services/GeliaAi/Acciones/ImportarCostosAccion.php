<?php

namespace App\Services\GeliaAi\Acciones;

class ImportarCostosAccion extends ImportarCatalogoAlmacenAccion
{
    public function id(): string
    {
        return 'importar_costos';
    }

    public function permiso(): string
    {
        return 'almacenes.costos.importar';
    }

    public function descripcion(): string
    {
        return 'Prepara (no ejecuta) la importación del costo de empresa desde un archivo adjunto. La persona usuaria confirma en la interfaz. Si faltan archivo o columnas, la respuesta indica qué falta.';
    }

    protected function tipo(): string
    {
        return 'costos';
    }

    protected function etiqueta(): string
    {
        return 'costos';
    }

    protected function kindEsperado(): string
    {
        return 'costos';
    }

    protected function columnasObligatorias(): array
    {
        return [];
    }

    protected function columnasOpcionales(): array
    {
        return ['folio', 'sku', 'costo', 'costo_reposicion'];
    }

    protected function exigeIdentificador(): bool
    {
        return true;
    }

    protected function columnasValor(): array
    {
        return ['costo', 'costo_reposicion'];
    }

    protected function exigeAlmacen(): bool
    {
        return false;
    }
}
