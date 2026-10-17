<?php

namespace App\Services\GeliaAi\Acciones;

class ImportarInventariosAccion extends ImportarCatalogoAlmacenAccion
{
    public function id(): string
    {
        return 'importar_inventarios';
    }

    public function permiso(): string
    {
        return 'almacenes.inventarios.importar';
    }

    public function descripcion(): string
    {
        return 'Prepara (no ejecuta) la importación de existencias de un archivo adjunto a un almacén. La persona usuaria confirma en la interfaz. Si faltan archivo, almacén o columnas, la respuesta indica qué falta.';
    }

    protected function tipo(): string
    {
        return 'inventarios';
    }

    protected function etiqueta(): string
    {
        return 'inventarios';
    }

    protected function kindEsperado(): string
    {
        return 'existencias';
    }

    protected function columnasObligatorias(): array
    {
        return ['sku', 'folio', 'descripcion', 'existencia'];
    }

    protected function columnasOpcionales(): array
    {
        return [];
    }
}
