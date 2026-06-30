<?php

namespace App\Services\Almacenes;

use App\Services\Auditoria\RegistrarAuditoriaConfiguracionService;

class RegistrarAuditoriaAlmacenService
{
    private const MODULO = 'Almacenes';

    public function importacion(string $tipo, array $resumen): void
    {
        RegistrarAuditoriaConfiguracionService::ejecutar(
            self::MODULO,
            "Importación masiva: {$tipo}",
            array_merge(['tipo' => $tipo], $resumen),
        );
    }

    public function catalogoImportado(string $tipo, array $resumen): void
    {
        $this->importacion("catálogo {$tipo}", $resumen);
    }

    public function productoCreado(int $id, string $sku, array $extra = []): void
    {
        $this->registrar('Producto creado', 'producto', $id, $sku, $extra);
    }

    public function productoActualizado(int $id, string $sku, array $extra = []): void
    {
        $this->registrar('Producto actualizado', 'producto', $id, $sku, $extra);
    }

    public function productoEliminado(int $id, string $sku, array $extra = []): void
    {
        $this->registrar('Producto eliminado', 'producto', $id, $sku, $extra);
    }

    public function inventarioModificado(int $id, string $referencia, array $extra = []): void
    {
        $this->registrar('Inventario modificado', 'inventario', $id, $referencia, $extra);
    }

    public function inventarioEliminado(int $id, string $referencia, array $extra = []): void
    {
        $this->registrar('Inventario eliminado', 'inventario', $id, $referencia, $extra);
    }

    public function costoModificado(int $id, string $referencia, array $extra = []): void
    {
        $this->registrar('Costo modificado', 'costo', $id, $referencia, $extra);
    }

    public function costoEliminado(int $id, string $referencia, array $extra = []): void
    {
        $this->registrar('Costo eliminado', 'costo', $id, $referencia, $extra);
    }

    public function catalogoCrud(string $accion, string $entidad, int $id, string $referencia, array $extra = []): void
    {
        $this->registrar("Catálogo {$accion}", $entidad, $id, $referencia, $extra);
    }

    private function registrar(string $accion, string $entidad, int $id, string $referencia, array $extra): void
    {
        RegistrarAuditoriaConfiguracionService::ejecutar(
            self::MODULO,
            $accion,
            array_merge([
                'entidad' => $entidad,
                'id' => $id,
                'referencia' => $referencia,
            ], $extra),
        );
    }
}
