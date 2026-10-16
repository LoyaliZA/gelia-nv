// Vista aislada: datos ficticios y transporte simulado. No usa sesión ni base de aplicación.
import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import axios from 'axios';
import '../../../resources/css/app.css';
import ModalPreparacionTienda from '../../../resources/js/Pages/ControlPedidos/Tienda/Partials/ModalPreparacionTienda';
import ModalFormPedido from '../../../resources/js/Pages/ControlPedidos/Partials/ModalFormPedido';
import ModalRevisarPedido from '../../../resources/js/Pages/ControlPedidos/Auditar/Partials/ModalRevisarPedido';
import TarjetasCedis from '../../../resources/js/Pages/ControlPedidos/Cedis/Partials/TarjetasCedis';
import SeccionRevisionFisicaPedido from '../../../resources/js/Pages/ControlPedidos/Partials/SeccionRevisionFisicaPedido';

const modo = new URLSearchParams(location.search).get('vista') || 'tienda';
if (new URLSearchParams(location.search).get('tema') === 'oscuro') document.documentElement.classList.add('dark');
const auth = { user: { id: 81001, name: 'Operador de prueba', permissions: ['control_pedidos.tienda.apartado.separar', 'control_pedidos.tienda.reportar_error', 'control_pedidos.auditar', 'control_pedidos.cedis.enviar'], roles: [], departamentos: [] } };
const pedido = { id: 81001, revision_registro: 1, folio: 'GELIA-PRUEBA-81', folio_remision: 'PEDIDO-PRUEBA-81', fecha: '2026-10-15',
    cliente_id: 81001, cliente: { id: 81001, nombre: 'Cliente ficticio para revisión de pedidos', numero_cliente: 'PRUEBA-81' },
    vendedor: { name: 'Vendedora de prueba', departamentos: [{ nombre: 'Ventas de prueba' }] },
    origen: { id: 1, nombre: 'Tienda', requiere_logistica: false }, catalogo_origen_pedido_id: 1,
    almacen_id: 1, almacen: { id: 1, nombre: 'Almacén de prueba', permite_salida_pedidos: true },
    es_traslado_interno_pdv: true, sucursal_destino_id: 2, sucursal_destino: { id: 2, nombre: 'Sucursal de prueba' },
    estatus: { fase_ciclo: 'EN_CEDIS', nombre_visual: 'En CEDIS' }, estatus_envio: 'pendiente_pesaje',
    numero_cajas: 2, cajas: [], documentos: [], anexar_remision: true, consulta_actualizacion_pendiente: true,
    total_mercancia: 1000, costo_envio: 0, total_a_cobrar: 1000, saldo_a_favor: 0,
    revisiones_producto: [{ id: 1, descripcion_producto: 'Pieza de muestra', estado_fisico: 'danado', comentario: 'Revisar superficie', cantidad_solicitada: 2, cantidad_encontrada: 2, unica_pieza: true, mejor_ejemplar: true }],
};
const tarea = { id: 81001, version: 1, estado: 'EN_ATENCION', estado_label: 'En atención', puede_preparar: true, puede_reportar_incidencia: true,
    responsable: { id: auth.user.id }, almacen: pedido.almacen,
    pedido: { id: pedido.id, folio: pedido.folio, folio_visible: pedido.folio_remision, cliente_nombre: pedido.cliente.nombre, vendedora_nombre: 'Vendedora de prueba' },
    apartado: { version: 1 }, productos: Array.from({ length: 6 }, (_, i) => ({ id: i + 1, producto_id: i + 1, sku: `PRUEBA-${i + 1}`, descripcion_snapshot: `Producto de prueba ${i + 1}`, cantidad_encontrada: 2, estado_fisico: 'bueno' })),
};
const datosTienda = { tarea, requisitos: {}, documentos: [], documentos_solicitud: [] };
window.__q81Requests = [];
// Todas las rutas quedan en este sandbox. Nunca apuntan a la aplicación real.
window.route = (name) => name ? `/__q81_simulado__/${name}` : { current: () => '', has: () => true };
axios.defaults.adapter = async (config) => {
    window.__q81Requests.push({ method: config.method, url: config.url });
    let data = datosTienda;
    if (config.method !== 'get') data = { message: 'Simulación: avance recibido; no se guardó en una base real.' };
    if (config.url?.includes('saldo_favor')) data = { disponible: 0, creditos: [] };
    if (config.url?.includes('detalle_json')) data = { pedido, resumen_cobertura: { total_a_cobrar: 1000, total_pagado: 0, pendiente: 1000 }, bloqueos_pago: [] };
    return { data, status: 200, statusText: 'OK', headers: {}, config };
};
// fetch solo se usa aquí para liberar la revisión simulada al cerrar.
window.fetch = async () => new Response('{}', { status: 200, headers: { 'Content-Type': 'application/json' } });
const catalogos = { origenes: [pedido.origen], almacenes: [pedido.almacen], departamentos: [], bancos: [], zonas: [],
    formulario_config: { progresivo: true }, preparacion_config: {}, tipos_operacion_envio: [] };
function Preview() {
    return <div className="gelia-pedidos-bma theme-surface-solid theme-text-main min-h-screen p-3">
        <p className="text-sm mb-3">Q8.1 · Datos ficticios · Interacciones simuladas · Sin sesión autenticada</p>
        {modo === 'tienda' && <ModalPreparacionTienda tareaId={tarea.id} auth={auth} onClose={() => {}} />}
        {modo === 'ventas' && <ModalFormPedido abierto pedido={pedido} catalogos={catalogos} onClose={() => {}} />}
        {modo === 'auxiliar' && <ModalRevisarPedido abierto pedido={{ ...pedido, estatus: { fase_ciclo: 'PENDIENTE_AUXILIAR', nombre_visual: 'Pendiente Auxiliar' } }} catalogos={catalogos} onClose={() => {}} />}
        {modo === 'cedis' && <><TarjetasCedis pedidos={{ data: [pedido,
            { ...pedido, id: 81002, folio_remision: 'ENVIO-PRUEBA-82', origen: { nombre: 'Envío', requiere_logistica: true }, es_traslado_interno_pdv: false, sucursal_destino: null, estatus: { fase_ciclo: 'EN_CEDIS' }, estatus_envio: 'pesaje_listo', consulta_actualizacion_pendiente: false, paqueteria: { nombre: 'Paquetería de prueba' }, anexar_remision: false },
        ] }} onVerDetalle={() => alert('Simulación: abrir detalle')} onResponderPesaje={() => alert('Simulación: responder consulta')} />
        <SeccionRevisionFisicaPedido pedido={pedido} /></>}
    </div>;
}
createInertiaApp({ page: { component: 'Preview', props: { auth }, url: location.pathname, version: 'q81' }, resolve: () => Preview,
    setup({ el, App, props }) { createRoot(el).render(<App {...props} />); } });
