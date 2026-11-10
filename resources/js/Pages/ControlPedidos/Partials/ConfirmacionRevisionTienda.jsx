import React, { useEffect, useId, useState } from 'react';
import axios from 'axios';
import { MapPin, Save, Send, Pencil, X } from 'lucide-react';
import AccionPrincipalTienda from './AccionPrincipalTienda';
import GaleriaEvidenciasPedido from './GaleriaEvidenciasPedido';
import RutaPedidoTienda from './RutaPedidoTienda';
import { GELIA_BTN_OUTLINE, THEME_BTN_PRIMARY, THEME_LABEL, THEME_SELECT } from '../../../utils/geliaTheme';

const FASES_CAPTURA = ['BORRADOR', 'PESAJE_PENDIENTE', 'PESAJE_RESPONDIDO', 'RECHAZADO_VENDEDORA'];
const mensaje = error => error?.response?.status === 419
    ? 'La sesión cambió o venció. Recargue GELIA antes de continuar; no se confirmó la operación.'
    : Object.values(error?.response?.data?.errors || {}).flat()[0] || error?.response?.data?.message || 'No se completó la operación.';

export default function ConfirmacionRevisionTienda({ pedido, sucursales = [], consultaCerrada = false, importesListos = false, procesosListos = true, onLectura, destinoAccion, onAviso, antesDeEnviar }) {
    const [sucursal, setSucursal] = useState(String(pedido?.sucursal_destino_id || ''));
    const [editando, setEditando] = useState(!pedido?.sucursal_destino_id);
    const [ocupado, setOcupado] = useState(false);
    const [aviso, setAviso] = useState('');
    const [evidencias, setEvidencias] = useState([]);
    const [previas, setPrevias] = useState([]);
    const id = useId();
    useEffect(() => setSucursal(String(pedido?.sucursal_destino_id || '')), [pedido?.id, pedido?.sucursal_destino_id]);
    const cobertura = pedido?.solicitud_preparacion?.cobertura_tienda?.tipo;
    const fase = pedido?.estatus?.fase_ciclo;
    const captura = FASES_CAPTURA.includes(fase);
    const cedis = pedido?.solicitud_preparacion?.area_revision === 'control_pedidos' || ['consultar_cedis', 'consulta_cedis', 'traspaso_cedis'].includes(pedido?.solicitud_preparacion?.caso_tienda);
    const confirma = ['transferencia', 'credito'].includes(cobertura);
    const creditoVencido = cobertura === 'credito' && pedido?.solicitud_preparacion?.credito_wizerp?.resultado === 'vencido';
    const marcarPagado = async () => {
        if (ocupado || !creditoVencido) return;
        setOcupado(true); setAviso(''); onAviso?.('');
        const cuerpo = new FormData();
        if (evidencias[0]) cuerpo.append('evidencia', evidencias[0]);
        try {
            const { data } = await axios.post(`/control-pedidos/${pedido.id}/credito-pagado`, cuerpo, { headers: { Accept: 'application/json' } });
            onLectura?.(data);
            const texto = 'Crédito pagado. El pedido volvió a revisión.';
            if (onAviso) onAviso(texto); else setAviso(texto);
        } catch (error) { if (onAviso) onAviso(mensaje(error)); else setAviso(mensaje(error)); }
        finally { setOcupado(false); }
    };
    if (!pedido?.id || (!captura && fase !== 'PENDIENTE_AUXILIAR')) return null;
    if (!cedis && !confirma && fase !== 'PENDIENTE_AUXILIAR') return null;
    const destinoPendiente = !sucursal || String(pedido.sucursal_destino_id || '') !== sucursal;
    const confirmar = async (accion) => {
        if (ocupado) return;
        setOcupado(true); setAviso(''); onAviso?.('');
        try {
            if (accion === 'enviar' && antesDeEnviar) {
                const listo = await antesDeEnviar();
                if (!listo) {
                    const texto = 'No se guardó el importe. Conserve la captura y vuelva a confirmar.';
                    if (onAviso) onAviso(texto); else setAviso(texto);
                    return;
                }
            }
            const { data } = accion === 'destino'
                ? await axios.post(`/control-pedidos/${pedido.id}/sucursal-destino`, { sucursal_destino_id: Number(sucursal) }, { headers: { Accept: 'application/json' } })
                : await axios.put(`/control-pedidos/${pedido.id}/enviar`, {}, { headers: { Accept: 'application/json' } });
            onLectura?.(data);
            if (accion === 'destino') setEditando(false);
            const texto = accion === 'destino' ? 'Sucursal de destino guardada.' : 'Pedido enviado a revisión del auxiliar.';
            if (onAviso) onAviso(texto); else setAviso(texto);
        } catch (error) { if (onAviso) onAviso(mensaje(error)); else setAviso(mensaje(error)); }
        finally { setOcupado(false); }
    };
    const nombreDestino = pedido.sucursal_destino?.nombre || pedido.sucursalDestino?.nombre || sucursales.find(item => String(item.id) === String(pedido.sucursal_destino_id))?.nombre || 'Por confirmar';
    return <section className="registrar-tienda-panel grid gap-3 tienda-destino-confirmacion" aria-label={cedis ? 'Destino y confirmación del pedido' : 'Confirmación del pedido'}>
        {cedis && <>
            <div className="tienda-destino-encabezado"><div className="registrar-tienda-seccion-titulo"><MapPin size={18} aria-hidden /><h3>Destino del pedido</h3></div>{captura && !editando && <button type="button" className={GELIA_BTN_OUTLINE} disabled={ocupado} onClick={() => setEditando(true)}><Pencil size={15} aria-hidden />Cambiar destino</button>}</div>
            <RutaPedidoTienda origen={pedido.almacen?.nombre || 'CEDIS'} destino={nombreDestino} />
            {captura && (editando || !pedido.sucursal_destino_id) && <div className="tienda-destino-edicion grid gap-2">
                <label className={THEME_LABEL} htmlFor={`${id}-sucursal`}>Sucursal de destino</label>
                <select id={`${id}-sucursal`} className={THEME_SELECT} value={sucursal} disabled={ocupado} onChange={evento => setSucursal(evento.target.value)}><option value="">Elegir sucursal</option>{sucursales.map(item => <option key={item.id} value={String(item.id)}>{item.nombre}</option>)}</select>
                <div className="flex flex-wrap gap-2">
                    <button type="button" className={GELIA_BTN_OUTLINE} disabled={ocupado || !sucursal || !destinoPendiente} onClick={() => confirmar('destino')}><Save size={16} aria-hidden />Guardar sucursal de destino</button>
                    {pedido.sucursal_destino_id && <button type="button" className={GELIA_BTN_OUTLINE} disabled={ocupado} onClick={() => { setSucursal(String(pedido.sucursal_destino_id)); setEditando(false); }}><X size={16} aria-hidden />Cancelar</button>}
                </div>
            </div>}
        </>}
        {fase === 'PENDIENTE_AUXILIAR' ? !onAviso && <p className="text-sm theme-text-main m-0" role="status">Pedido en revisión del auxiliar.</p> : confirma && <>
            {!cedis && <div className="registrar-tienda-seccion-titulo"><Send size={18} aria-hidden /><h3>Confirmación del pedido</h3></div>}
            <p className="text-sm theme-text-muted m-0">{creditoVencido ? 'El cliente tiene un crédito vencido y necesita liquidarlo. Confirma el pago para enviarlo de nuevo a revisión.' : cobertura === 'credito' ? 'El auxiliar revisará el crédito en Wizerp.' : 'El auxiliar revisará el pago y la remisión.'}</p>
            {creditoVencido && <div onPaste={(evento) => evento.stopPropagation()}><GaleriaEvidenciasPedido label="Evidencia del pago" obligatorio={false} maxArchivos={1} archivos={evidencias} previews={previas} onChange={(archivos, vistas) => { setEvidencias(archivos); setPrevias(vistas); }} disabled={ocupado} /></div>}
            {!cedis && destinoPendiente && <p className="text-sm theme-text-muted m-0">Confirma el destino del pedido antes de enviarlo a revisión.</p>}
            {!importesListos && <p className="text-sm theme-text-muted m-0">{cobertura === 'credito' ? 'El total a cubrir no está completo.' : 'Espere a que se guarden los importes antes de confirmar.'}</p>}
            {!procesosListos && (cobertura !== 'credito' || importesListos) && <p className="text-sm theme-text-muted m-0">Completa y guarda la modalidad, los importes y la cobertura del pedido antes de confirmar.</p>}
            <AccionPrincipalTienda destino={destinoAccion}>{creditoVencido && <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px] justify-self-start`} disabled={ocupado} onClick={marcarPagado}>{ocupado ? 'Enviando…' : 'Crédito pagado'}</button>}<button type="button" className={`${creditoVencido ? GELIA_BTN_OUTLINE : THEME_BTN_PRIMARY} min-h-[44px] justify-self-start`} disabled={ocupado || creditoVencido || destinoPendiente || !consultaCerrada || !importesListos || !procesosListos} onClick={() => confirmar('enviar')}><Send size={18} />{ocupado && !creditoVencido ? 'Enviando…' : 'Confirmar pedido y enviar a revisión'}</button></AccionPrincipalTienda>
        </>}
        {aviso && <p className="text-sm theme-text-main m-0" role="status">{aviso}</p>}
    </section>;
}
