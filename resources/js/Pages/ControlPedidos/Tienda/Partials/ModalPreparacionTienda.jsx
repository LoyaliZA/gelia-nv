import useActualizacionesPedidos from '../../Partials/useActualizacionesPedidos';
import React, { useCallback, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { Loader2, X } from 'lucide-react';
import usePedidoDialog from '../../Partials/usePedidoDialog';
import { BTN_PRIMARY, BTN_SECONDARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../Partials/pedidosBmaStyles';
import PrepararApartadoRecoge from './PrepararApartadoRecoge';
import ShowTienda from '../Show';
import ModalConfirmarAccion from '../../Partials/ModalConfirmarAccion';
import AvisoResultadoTienda from './AvisoResultadoTienda';

export default function ModalPreparacionTienda({ tareaId, atender = false, auth, onClose, onActualizado }) {
    const [datos, setDatos] = useState(null);
    const [cargando, setCargando] = useState(true);
    const [procesando, setProcesando] = useState(false);
    const [error, setError] = useState('');
    const [mensaje, setMensaje] = useState('');
    const [dirty, setDirty] = useState(false);
    const [confirmarCerrar, setConfirmarCerrar] = useState(false);
    const [capturaVersion, setCapturaVersion] = useState(0);
    const ocupado = cargando || procesando;
    const pedirCerrar = () => dirty ? setConfirmarCerrar(true) : onClose();
    const dialog = usePedidoDialog({ abierto: true, onClose: pedirCerrar, bloqueado: procesando });
    useEffect(() => {
        const prevenir = (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } };
        window.addEventListener('beforeunload', prevenir);
        return () => window.removeEventListener('beforeunload', prevenir);
    }, [dirty]);
    const cargar = useCallback(async (signal, conservarCaptura = false) => {
        const respuesta = await axios.get(route('control_pedidos.tienda.show', tareaId), { headers: { Accept: 'application/json' }, signal });
        setDatos(respuesta.data);
        if (!conservarCaptura) setCapturaVersion((v) => v + 1);
        return respuesta.data;
    }, [tareaId]);

    useEffect(() => {
        const controller = new AbortController();
        const abrir = async () => {
            try {
                const respuesta = await cargar(controller.signal);
                if (controller.signal.aborted) return;
                if (atender && respuesta.tarea.estado === 'PENDIENTE' && respuesta.tarea.puede_tomar) {
                    setProcesando(true);
                    await axios.post(route('control_pedidos.tienda.tomar', tareaId), { version: respuesta.tarea.version }, { headers: { Accept: 'application/json' }, signal: controller.signal });
                    if (!controller.signal.aborted) await cargar(controller.signal);
                }
            } catch (e) {
                if (!controller.signal.aborted) setError(e.response?.data?.message || 'No se pudo abrir la preparación. Intente de nuevo.');
            } finally {
                if (!controller.signal.aborted) { setCargando(false); setProcesando(false); }
            }
        };
        abrir();
        return () => controller.abort();
    }, [tareaId, atender, cargar]);

    const enviar = async (url, payload, opciones = {}) => {
        if (ocupado) return;
        setProcesando(true);
        setError('');
        setMensaje('');
        try {
            const respuesta = await axios.post(url, payload, { headers: { Accept: 'application/json' } });
            await cargar();
            setMensaje(respuesta.data.message || 'Preparación actualizada.');
            setDirty(false);
            onActualizado?.();
            opciones.onSuccess?.();
        } catch (e) {
            const errores = e.response?.data?.errors;
            if (errores) opciones.onError?.(errores);
            setError(errores ? Object.values(errores).flat().join(' ') : (e.response?.data?.message || 'No se pudo guardar la preparación.'));
        } finally {
            setProcesando(false);
            opciones.onFinish?.();
        }
    };
    useActualizacionesPedidos({ activo: Boolean(datos?.tarea?.pedido?.id), pedidoId: datos?.tarea?.pedido?.id, pausado: dirty || procesando || cargando, onActualizar: () => cargar().catch(() => setError('No se pudo actualizar la preparación.')), onPendiente: () => setMensaje('La preparación cambió. Se conserva su captura; al guardar se comprobará la versión.') });
    const tarea = datos?.tarea;
    const folio = tarea?.pedido?.folio_visible || tarea?.pedido?.folio || 'Preparación en tienda';
    const caratula = datos?.documentos?.filter((d) => d.tipo_evidencia === 'caratula_recoge_tienda').sort((a, b) => b.id - a.id)[0];

    return createPortal(<div className={`gelia-pedidos-bma ${THEME_MODAL_OVERLAY} items-center p-2 md:p-4`}>
        <div {...dialog} aria-label={`Preparar ${folio}`} className={`${THEME_MODAL_SHELL} gelia-preparacion-modal w-full max-w-7xl max-h-[95dvh] flex flex-col overflow-hidden`}>
            <header className="flex items-center justify-between gap-3 p-4 border-b theme-border theme-surface-solid shrink-0">
                <div><h2 className="text-lg font-bold theme-text-main m-0">{folio}</h2><p className="text-sm theme-text-muted m-0">{tarea?.estado_label || 'Cargando preparación…'}</p></div>
                <button type="button" className={BTN_SECONDARY} onClick={pedirCerrar} disabled={procesando} aria-label="Cerrar preparación"><X className="w-5 h-5" /></button>
            </header>
            <div className="gelia-modal-body flex flex-col">
                <AvisoResultadoTienda flash={{ error, success: mensaje }} />
                {cargando ? <div role="status" className="flex items-center gap-2 py-8 px-4 theme-text-muted"><Loader2 className="w-5 h-5 animate-spin" /> Abriendo pedido…</div> : tarea && <>
                    {tarea.estado === 'PENDIENTE' && tarea.puede_tomar && <button type="button" className={BTN_PRIMARY} disabled={ocupado}
                        onClick={() => enviar(route('control_pedidos.tienda.tomar', tarea.id), { version: tarea.version })}>Atender pedido</button>}
                    {['PENDIENTE', 'EN_ATENCION'].includes(tarea.estado) ? <PrepararApartadoRecoge key={`${tarea.id}-${capturaVersion}`} tarea={tarea} auth={auth}
                        documentos={datos.documentos_solicitud} documentosTarea={datos.documentos} requisitos={datos.requisitos} tiposCaja={datos.tipos_caja} onEnviar={enviar} bloqueado={ocupado} onDirtyChange={setDirty} onRefrescarDocumentos={() => cargar(undefined, true).catch(() => setError('No se pudieron actualizar las fotos.'))} />
                        : <ShowTienda {...datos} auth={auth} embedded onEnviar={enviar} />}
                    {caratula && tarea.apartado?.estado === 'APARTADO_CONFIRMADO' && <a className="inline-block underline text-sm font-bold theme-text-primario px-4 pb-3"
                        href={route('control_pedidos.tienda.evidencia.descargar', [tarea.id, caratula.id])}>Descargar carátula del apartado</a>}
                </>}
            </div>
        </div>
        <ModalConfirmarAccion abierto={confirmarCerrar} titulo="Hay cambios sin guardar" mensaje="Puede continuar la preparación o cerrar y descartar la captura pendiente." etiquetaConfirmar="Cerrar sin guardar" onClose={() => setConfirmarCerrar(false)} onConfirm={onClose} />
    </div>, document.body);
}
