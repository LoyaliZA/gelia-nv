// Vista aislada QF-01: componentes reales, datos ficticios. Sin sesión ni base.
import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import axios from 'axios';
import '../../../resources/css/app.css';
import TarjetasCedis from '../../../resources/js/Pages/ControlPedidos/Cedis/Partials/TarjetasCedis';
import ModalResponderPesaje from '../../../resources/js/Pages/ControlPedidos/Cedis/Partials/ModalResponderPesaje';
import ModalDetalleCedis from '../../../resources/js/Pages/ControlPedidos/Cedis/Partials/ModalDetalleCedis';

const params = new URLSearchParams(location.search);
const caso = params.get('caso') || 'bolsas';
if (params.get('tema') === 'oscuro') document.documentElement.classList.add('dark');

const auth = {
    user: {
        id: 91001,
        name: 'Operador QF-01',
        permissions: ['control_pedidos.cedis', 'control_pedidos.cedis.enviar'],
        roles: [],
        departamentos: [],
    },
};

const base = {
    folio: 'GELIA-QF01',
    fecha: '2026-10-16',
    cliente: { id: 1, nombre: 'Cliente QF-01', numero_cliente: 'QF-01' },
    vendedor: { name: 'Ventas prueba', departamentos: [{ nombre: 'Ventas' }] },
    almacen: { id: 1, nombre: 'CEDIS prueba' },
    almacen_id: 1,
    estatus: { fase_ciclo: 'EN_CEDIS', nombre_visual: 'En CEDIS' },
    estatus_envio: 'pendiente_pesaje',
    numero_cajas: 3,
    cajas: [],
    documentos: [],
    anexar_remision: false,
    total_mercancia: 500,
    costo_envio: 0,
    total_a_cobrar: 500,
    revisiones_producto: [],
};

/** Traslado interno / sucursal → Bolsas */
const pedidoBolsas = {
    ...base,
    id: 91001,
    folio_remision: 'SUCURSAL-BOLSAS',
    origen: { id: 1, nombre: 'Tienda', requiere_logistica: false },
    es_traslado_interno_pdv: true,
    requiere_bultos_empaque: true,
    requiere_sucursal_destino: true,
    sucursal_destino_id: 2,
    sucursal_destino: { id: 2, nombre: 'Sucursal destino' },
    paqueteria: null,
};

/** Paquetería con requiere_caja → Cajas (sin señales de sucursal) */
const pedidoCajas = {
    ...base,
    id: 91002,
    folio_remision: 'PAQ-CAJAS',
    origen: { id: 2, nombre: 'Envío regional', requiere_logistica: true },
    es_traslado_interno_pdv: false,
    requiere_bultos_empaque: false,
    requiere_sucursal_destino: false,
    sucursal_destino: null,
    paqueteria: {
        id: 4,
        nombre: 'Paquetería regional',
        categoria: 'local_regional',
        requiere_peso: false,
        requiere_caja: true,
    },
    consulta_cedis_modo_revision_mercancia: true,
};

/** Sin clasificación segura → Bultos */
const pedidoBultos = {
    ...base,
    id: 91003,
    folio_remision: 'GEN-BULTOS',
    origen: { id: 3, nombre: 'Origen regional', requiere_logistica: false },
    es_traslado_interno_pdv: false,
    requiere_bultos_empaque: false,
    requiere_sucursal_destino: false,
    sucursal_destino: null,
    paqueteria: {
        id: 5,
        nombre: 'Regional sin caja',
        categoria: 'local_regional',
        requiere_peso: false,
        requiere_caja: false,
    },
    consulta_cedis_modo_revision_mercancia: true,
};

const porCaso = { bolsas: pedidoBolsas, cajas: pedidoCajas, bultos: pedidoBultos };
const pedidoActivo = porCaso[caso] || pedidoBolsas;
const pedidosLista = [pedidoBolsas, pedidoCajas, pedidoBultos];

window.__qf01Requests = [];
window.route = (name) => (name ? `/__qf01_simulado__/${name}` : { current: () => '', has: () => true });
axios.defaults.adapter = async (config) => {
    window.__qf01Requests.push({ method: config.method, url: config.url });
    return { data: {}, status: 200, statusText: 'OK', headers: {}, config };
};
window.fetch = async () => new Response('{}', { status: 200, headers: { 'Content-Type': 'application/json' } });

function Preview() {
    const [modal, setModal] = useState(params.get('modal') === '1');
    const [detalle, setDetalle] = useState(params.get('detalle') === '1');
    const [seleccionado, setSeleccionado] = useState(pedidoActivo);

    return (
        <div className="gelia-pedidos-bma theme-surface-solid theme-text-main min-h-screen p-3">
            <p className="text-sm mb-2 m-0">
                QF-01 · Empaques CEDIS · caso=
                <strong>{caso}</strong>
                {' '}
                · Datos ficticios · Sin sesión
            </p>
            <p className="text-xs theme-text-muted mb-3 m-0">
                Enlaces:
                {' '}
                <a href="?caso=bolsas">bolsas</a>
                {' · '}
                <a href="?caso=cajas">cajas</a>
                {' · '}
                <a href="?caso=bultos">bultos</a>
                {' · '}
                <a href={`?caso=${caso}&modal=1`}>abrir modal consulta</a>
                {' · '}
                <a href={`?caso=${caso}&detalle=1`}>abrir detalle</a>
            </p>

            <TarjetasCedis
                pedidos={{ data: pedidosLista }}
                onVerDetalle={(p) => { setSeleccionado(p); setDetalle(true); }}
                onResponderPesaje={(p) => { setSeleccionado(p); setModal(true); }}
            />

            {modal && (
                <ModalResponderPesaje
                    abierto
                    pedido={seleccionado}
                    tiposCaja={[{ id: 1, nombre: 'Caja mediana' }]}
                    almacenesBusqueda={[]}
                    onClose={() => setModal(false)}
                />
            )}
            {detalle && (
                <ModalDetalleCedis
                    abierto
                    pedido={seleccionado}
                    onClose={() => setDetalle(false)}
                />
            )}
        </div>
    );
}

createInertiaApp({
    page: { component: 'Preview', props: { auth }, url: location.pathname, version: 'qf01' },
    resolve: () => Preview,
    setup({ el, App, props }) { createRoot(el).render(<App {...props} />); },
});
