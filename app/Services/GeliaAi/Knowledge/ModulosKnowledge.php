<?php

namespace App\Services\GeliaAi\Knowledge;

class ModulosKnowledge
{
    public function fragmento(string $modulo): string
    {
        return match (strtolower(trim($modulo))) {
            'listados' => $this->listados(),
            'solicitudes' => $this->solicitudes(),
            'inventario', 'inventarios', 'almacenes' => $this->inventario(),
            'productos' => $this->productos(),
            'ventas', 'reportes' => $this->ventas(),
            default => 'Módulo desconocido. Usa: listados, solicitudes, inventario, productos o ventas.',
        };
    }

    private function listados(): string
    {
        return <<<'TXT'
Listados (Funciones Operativas → Listados). Genera Excel de precios/existencias.
Tipos sistema: resurtido, costos, actualizada, inventario, venta_especial, meli (+ plantillas custom).
Flujo: subir existencias (obligatorio) + precios y/o costos → elegir tipo → generar → revisar inconsistencias → guardar historial y/o enviar correo → descargar.
Permisos clave: listados.ver, listados.guardar_generado, listados.enviar, listados.visualizar, listados.configurar_porcentajes.
No crea inventario: solo procesa archivos cargados por el usuario.
TXT;
    }

    private function solicitudes(): string
    {
        return <<<'TXT'
Solicitudes TAG (módulo Solicitudes): procesos financieros de cambio de lista/descuento, no facturas ni traspasos.
Crear: cliente, proceso, tipo cliente, lista, monto cotizado, observaciones, evidencia. Estado inicial Pendiente.
Ciclo: Pendiente → reportar (Respondida/Incorrecta) → confirmar pago / verificar → Verificada. También consultas y cancelación.
Compra en tienda: lista Bronce, monto 0; al aprobar queda concluida sin confirmar pago. Compra Realizada: Solicitar tag (ASIGNAR TAG): sin lista/cotización; mismo flujo de pago auto.
Operativos van a Cancelaciones/Cotizaciones, no aquí.
Permisos: solicitudes.ver_listado, crear, reportar, verificar, confirmar_pago, etc.
TXT;
    }

    private function inventario(): string
    {
        return <<<'TXT'
Inventario (Almacenes): stock por producto+almacén (existencia, apartado, disponible=existencia-apartado).
Buscar por SKU, descripción, código de barras o folio. Filtrar por almacén/sucursal en la UI.
GELIA consulta existencias; con permiso almacenes.costos.ver también puede reportar costo y precio_venta del catálogo.
GELIA no crea ni ajusta inventario. No inventa precios.
Permisos: almacenes.inventarios.ver, almacenes.costos.ver (precios).
TXT;
    }

    private function productos(): string
    {
        return <<<'TXT'
Productos viven en Gestión Interna → Productos (ya no en Almacenes).
Cada SKU es un producto independiente. Atributos tipados por categoría.
Extensiones (p. ej. Perfumería/notas olfativas) solo aplican si la categoría las tiene asignadas; no son universales.
Relaciones opcionales entre presentaciones hermanas (sin cascada). Contenido comercial (pitch/SEO) por canal.
Inventarios y costos siguen en Almacenes.
Permisos: gestion_interna.productos.ver / gestionar / importar.
TXT;
    }

    private function ventas(): string
    {
        return <<<'TXT'
Reportes → Ventas: montos/cantidades importados del ERP por producto+almacén+periodo (YYYY-MM).
No son ventas en tiempo real del POS local. Sin filas importadas ≠ cero ventas reales.
Permisos: reportes.ventas.ver / importar.
TXT;
    }
}
