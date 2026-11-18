import React, { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaTituloCard from '../../../Components/GeliaTituloCard';
import PanelRespuestaDisponibilidad from '../Partials/PanelRespuestaDisponibilidad';
import { THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';

export default function ConsultasTienda({ pedidos = [] }) {
    const [aviso, setAviso] = useState('');
    const [seleccionado, setSeleccionado] = useState(null);
    const abrir = async (id) => {
        try {
            const { data } = await window.axios.get(`/control-pedidos/${id}/disponibilidad-tienda`);
            setSeleccionado(data);
        } catch (error) { setAviso(error.response?.data?.message || 'No se pudo abrir la consulta.'); }
    };
    return (
        <AppLayout><GeliaPageShell>
            <GeliaTituloCard eyebrow="Municipal" title="Consulta pendiente: Tienda" description="Confirme disponibilidad o reporte las piezas con excepción y su evidencia." />
            {aviso && <p className="text-sm theme-text-aviso">{aviso}</p>}
            <ul className="space-y-3 m-0 p-0 list-none max-w-2xl">
                {pedidos.map((pedido) => (
                    <li key={pedido.id} className="theme-surface border theme-border rounded-2xl p-4 space-y-2">
                        <p className="font-black theme-text-main m-0">{pedido.folio} · {pedido.cliente}</p>
                        <p className="text-sm theme-text-main m-0">Envío municipal · Terminal destino: {pedido.terminal_destino || 'Por seleccionar'}</p>
                        <p className="text-sm theme-text-muted m-0">{pedido.etiqueta}</p>
                        <button type="button" className={THEME_BTN_PRIMARY} onClick={() => abrir(pedido.id)}>Revisar disponibilidad</button>
                    </li>
                ))}
                {pedidos.length === 0 && <li className="text-sm theme-text-muted">No hay consultas pendientes en Tienda.</li>}
            </ul>
            {seleccionado && <PanelRespuestaDisponibilidad key={seleccionado.id} modo="dialogo" abierto
                pedido={seleccionado} documentos={seleccionado.documentos || []}
                revision={seleccionado.solicitud_preparacion?.revision_disponibilidad}
                origen="Tienda" puedeResponder tomada onClose={() => setSeleccionado(null)}
                onEnviar={async (form) => {
                    await window.axios.post(route('control_pedidos.municipal.disponibilidad', seleccionado.id), form, { headers: { Accept: 'application/json' } });
                    window.location.reload();
                }} />}
        </GeliaPageShell></AppLayout>
    );
}
