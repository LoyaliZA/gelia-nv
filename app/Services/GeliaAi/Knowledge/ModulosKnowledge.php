<?php

namespace App\Services\GeliaAi\Knowledge;

class ModulosKnowledge
{
    public function fragmento(string $modulo): string
    {
        return match (strtolower(trim($modulo))) {
            'listados' => $this->listados(),
            'solicitudes' => $this->solicitudes(),
            'inventario', 'inventarios', 'almacenes', 'productos' => $this->inventario(),
            default => 'Módulo desconocido. Usa: listados, solicitudes o inventario.',
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
Compra en tienda: lista Bronce, monto 0. Solo TAG (ASIGNAR TAG): sin lista/cotización.
Operativos van a Cancelaciones/Cotizaciones, no aquí.
Permisos: solicitudes.ver_listado, crear, reportar, verificar, confirmar_pago, etc.
TXT;
    }

    private function inventario(): string
    {
        return <<<'TXT'
Inventario/Productos (Almacenes): stock por producto+almacén (existencia, apartado, disponible=existencia-apartado).
Buscar por SKU, descripción, código de barras o folio. Filtrar por almacén/sucursal en la UI.
GELIA solo consulta existencias; no crea ni ajusta inventario. Costos no se exponen en el chat.
Permisos: almacenes.productos.ver, almacenes.inventarios.ver.
TXT;
    }
}
