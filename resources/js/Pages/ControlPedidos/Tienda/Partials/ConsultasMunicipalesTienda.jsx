import React, { useState } from 'react';
import PanelRespuestaDisponibilidad from '../../Partials/PanelRespuestaDisponibilidad';
import { THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';

/** Consultas municipales pendientes que se preparan en tienda; el almacén indica la sucursal que responde. */
/** Abre el panel de respuesta de una consulta municipal desde una fila de la bandeja. */
export function PanelConsultaMunicipalTienda({ pedidoId, onClose }) {
    const [pedido, setPedido] = useState(null);
    const [error, setError] = useState('');
    React.useEffect(() => {
        let vigente = true;
        window.axios.get(`/control-pedidos/${pedidoId}/disponibilidad-tienda`)
            .then(({ data }) => { if (vigente) setPedido(data); })
            .catch((e) => { if (vigente) setError(e.response?.data?.message || 'No se pudo abrir la consulta.'); });
        return () => { vigente = false; };
    }, [pedidoId]);
    if (error) return <p className="text-sm theme-text-aviso" role="alert">{error} <button type="button" className="underline" onClick={onClose}>Cerrar</button></p>;
    if (!pedido) return null;
    return <PanelRespuestaDisponibilidad key={pedido.id} modo="dialogo" abierto
        pedido={pedido} documentos={pedido.documentos || []}
        revision={pedido.solicitud_preparacion?.revision_disponibilidad}
        origen="Tienda" puedeResponder tomada onClose={onClose}
        onEnviar={async (form) => {
            await window.axios.post(route('control_pedidos.municipal.disponibilidad', pedido.id), form, { headers: { Accept: 'application/json' } });
            window.location.reload();
        }} />;
}

export default function ConsultasMunicipalesTienda({ pedidos = [], vacio = null }) {
    const [aviso, setAviso] = useState('');
    const [seleccionado, setSeleccionado] = useState(null);
    const abrir = async (id) => {
        try {
            const { data } = await window.axios.get(`/control-pedidos/${id}/disponibilidad-tienda`);
            setSeleccionado(data);
        } catch (error) { setAviso(error.response?.data?.message || 'No se pudo abrir la consulta.'); }
    };
    if (!pedidos.length && !vacio) return null;
    return (
        <>
            {aviso && <p className="text-sm theme-text-aviso m-0" role="alert">{aviso}</p>}
            <ul className="space-y-3 m-0 p-0 list-none max-w-2xl">
                {pedidos.map((pedido) => (
                    <li key={pedido.id} className="theme-surface border theme-border rounded-2xl p-4 space-y-2">
                        <p className="font-black theme-text-main m-0">{pedido.folio_visible || pedido.folio} · {pedido.cliente}</p>
                        <p className="text-sm theme-text-main m-0">Envío municipal · Terminal destino: {pedido.terminal_destino || 'Por seleccionar'}</p>
                        {pedido.almacen && <p className="text-sm theme-text-main m-0">Almacén: {pedido.almacen}</p>}
                        <p className="text-sm theme-text-muted m-0">{pedido.etiqueta}</p>
                        <button type="button" className={THEME_BTN_PRIMARY} onClick={() => abrir(pedido.id)}>Revisar disponibilidad</button>
                    </li>
                ))}
                {pedidos.length === 0 && vacio && <li className="text-sm theme-text-muted">{vacio}</li>}
            </ul>
            {seleccionado && <PanelRespuestaDisponibilidad key={seleccionado.id} modo="dialogo" abierto
                pedido={seleccionado} documentos={seleccionado.documentos || []}
                revision={seleccionado.solicitud_preparacion?.revision_disponibilidad}
                origen="Tienda" puedeResponder tomada onClose={() => setSeleccionado(null)}
                onEnviar={async (form) => {
                    await window.axios.post(route('control_pedidos.municipal.disponibilidad', seleccionado.id), form, { headers: { Accept: 'application/json' } });
                    window.location.reload();
                }} />}
        </>
    );
}
