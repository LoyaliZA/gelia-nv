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
        return 'Prepara (no ejecuta) la importación de costos/precios de un archivo adjunto a un almacén. La persona usuaria confirma en la interfaz. Si faltan archivo, almacén o columnas, la respuesta indica qué falta.';
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
        return ['sku'];
    }

    protected function columnasOpcionales(): array
    {
        return ['costo', 'costo_reposicion', 'precio_venta'];
    }

    protected function columnasValor(): array
    {
        return ['costo', 'costo_reposicion', 'precio_venta'];
    }
}
