import { uuidSolicitud } from '../../../utils/uuidSolicitud';
import React, { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { CheckCircle2, PackageMinus, ArrowRightLeft, Send, X, Search, FileImage, MoreHorizontal } from 'lucide-react';
import { GELIA_BTN_OUTLINE, THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';
import SelectorProducto from '../../../Components/Almacenes/SelectorProducto';
import GaleriaEvidenciasPedido from './GaleriaEvidenciasPedido';
import usePedidoDialog from './usePedidoDialog';
const etiquetas = { aceptar_condicion: 'Estado confirmado con el cliente', retirar: 'Retiro registrado', sustituir: 'Sustitución solicitada' };
const acciones = {
    confirmar: { titulo: 'Confirmar estado', Icono: CheckCircle2 },
    retirar: { titulo: 'Retirar', Icono: PackageMinus },
    sustituir: { titulo: 'Sustituir', Icono: ArrowRightLeft },
};

function MenuCompactoDisponibilidad({ descripcion, permiteConfirmar, onElegir }) {
    const id = useId();
    const [destino, setDestino] = useState(null);
    const botonRef = useRef(null);
    const menuRef = useRef(null);

    useLayoutEffect(() => {
        if (!destino) return undefined;
        const boton = botonRef.current;
        const menu = menuRef.current;
        const cuerpo = boton.closest('.gelia-modal-body');
        const contenedor = cuerpo || boton.closest('[role="dialog"]');
        const lista = boton.closest('.tienda-respuesta-lista[data-desplazable]');
        const viewport = window.visualViewport;
        const cerrar = () => setDestino(null);
        const posicionar = () => {
            const ancla = boton.getBoundingClientRect();
            // La capa fija puede tomar la máscara del diálogo como origen.
            const capa = menu.parentElement.getBoundingClientRect();
            const limite = contenedor?.getBoundingClientRect();
            const margen = 8;
            const separacion = 6;
            const izquierda = Math.max(viewport?.offsetLeft ?? 0, limite?.left ?? 0) + margen;
            const arriba = Math.max(viewport?.offsetTop ?? 0, limite?.top ?? 0) + margen;
            const derecha = Math.min((viewport?.offsetLeft ?? 0) + (viewport?.width ?? window.innerWidth), limite?.right ?? Infinity) - margen;
            const abajo = Math.min((viewport?.offsetTop ?? 0) + (viewport?.height ?? window.innerHeight), limite?.bottom ?? Infinity) - margen;
            const visible = lista?.getBoundingClientRect();
            if (ancla.bottom < Math.max(arriba - margen, visible?.top ?? -Infinity) ||
                ancla.top > Math.min(abajo + margen, visible?.bottom ?? Infinity)) {
                if (menu.contains(document.activeElement)) boton.focus({ preventScroll: true });
                cerrar();
                return false;
            }
            menu.style.maxWidth = `${Math.max(0, derecha - izquierda)}px`;
            const alto = menu.scrollHeight + menu.offsetHeight - menu.clientHeight;
            const espacioAbajo = abajo - ancla.bottom - separacion;
            const espacioArriba = ancla.top - arriba - separacion;
            const haciaArriba = alto > espacioAbajo && espacioArriba > espacioAbajo;
            menu.style.maxHeight = `${Math.max(0, haciaArriba ? espacioArriba : espacioAbajo)}px`;
            menu.style.left = `${Math.max(izquierda, Math.min(ancla.right, derecha) - menu.offsetWidth) - capa.left}px`;
            const top = haciaArriba ? ancla.top - separacion - menu.offsetHeight : ancla.bottom + separacion;
            menu.style.top = `${Math.max(arriba, top) - capa.top}px`;
            return true;
        };
        const fuera = event => {
            if (!boton.contains(event.target) && !menu.contains(event.target)) cerrar();
        };
        const cerrarConTeclado = event => {
            if (event.key !== 'Escape' && (event.key !== 'Tab' || !menu.contains(event.target))) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            cerrar();
            boton.focus({ preventScroll: true });
        };

        if (!posicionar()) return undefined;
        menu.querySelector('button')?.focus({ preventScroll: true });
        window.addEventListener('keydown', cerrarConTeclado, true);
        window.addEventListener('scroll', posicionar, true);
        window.addEventListener('resize', posicionar);
        viewport?.addEventListener('scroll', posicionar);
        viewport?.addEventListener('resize', posicionar);
        document.addEventListener('pointerdown', fuera);
        return () => {
            window.removeEventListener('keydown', cerrarConTeclado, true);
            window.removeEventListener('scroll', posicionar, true);
            window.removeEventListener('resize', posicionar);
            viewport?.removeEventListener('scroll', posicionar);
            viewport?.removeEventListener('resize', posicionar);
            document.removeEventListener('pointerdown', fuera);
        };
    }, [destino]);

    const alternar = () => {
        const boton = botonRef.current;
        setDestino(destino ? null : boton.closest('[role="dialog"]') || boton.parentElement);
    };
    const elegir = accion => {
        setDestino(null);
        botonRef.current.focus({ preventScroll: true });
        onElegir(accion);
    };
    const teclado = event => {
        if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
        const opciones = [...menuRef.current.querySelectorAll('button')];
        const indice = opciones.indexOf(document.activeElement);
        const siguiente = {
            ArrowDown: (indice + 1) % opciones.length,
            ArrowUp: (indice + opciones.length - 1) % opciones.length,
            Home: 0,
            End: opciones.length - 1,
        }[event.key];
        event.preventDefault();
        opciones[siguiente].focus();
    };

    return <div className="tienda-respuesta-menu">
        <button ref={botonRef} type="button" className={GELIA_BTN_OUTLINE} aria-label={`Acciones de ${descripcion}`}
            aria-haspopup="menu" aria-expanded={Boolean(destino)} aria-controls={destino ? id : undefined}
            onClick={alternar}><MoreHorizontal size={20} aria-hidden /></button>
        {destino && createPortal(
            <div className="tienda-respuesta-menu-capa">
                <div ref={menuRef} id={id} className="tienda-respuesta-menu-lista" role="menu" aria-label={`Acciones de ${descripcion}`} onKeyDown={teclado}>
                    {permiteConfirmar && <button role="menuitem" tabIndex={-1} type="button" onClick={() => elegir('confirmar')}><CheckCircle2 size={16} aria-hidden />Confirmar estado</button>}
                    <button role="menuitem" tabIndex={-1} type="button" onClick={() => elegir('retirar')}><PackageMinus size={16} aria-hidden />Retirar</button>
                    <button role="menuitem" tabIndex={-1} type="button" onClick={() => elegir('sustituir')}><ArrowRightLeft size={16} aria-hidden />Sustituir</button>
                </div>
            </div>,
            destino,
        )}
    </div>;
}

function DialogoAccionDisponibilidad({ accion, ocupado, onClose, children }) {
    const tituloId = useId();
    const dialogo = usePedidoDialog({ abierto: true, onClose, bloqueado: ocupado });
    const { titulo, Icono } = acciones[accion];

    return createPortal(
        <div className="gelia-modal-overlay tienda-accion-capa" onClick={event => event.stopPropagation()}>
            <div {...dialogo} className="gelia-modal-shell tienda-accion-dialogo" data-accion={accion} aria-labelledby={tituloId}>
                <header className="tienda-accion-encabezado">
                    <span className="tienda-accion-icono theme-element border theme-border"><Icono size={22} aria-hidden /></span>
                    <h2 id={tituloId}>{titulo}</h2>
                    <button type="button" className={GELIA_BTN_OUTLINE} aria-label={`Cerrar ${titulo.toLowerCase()}`} disabled={ocupado} onClick={onClose}>
                        <X size={19} aria-hidden />
                    </button>
                </header>
                <div className="tienda-accion-cuerpo">{children}</div>
            </div>
        </div>,
        document.body,
    );
}

export default function AccionesRapidasDisponibilidad({ pedidoId, pieza, descripcionVisible = pieza.descripcion_producto || pieza.descripcion, almacenId, onActualizar, onVer, soloLectura = false, compacta = false, onOcupado = null, ejecutor = null }) {
    const elegir = valor => { setAccion(valor); setError(''); };
    const [accion, setAccion] = useState('');
    const [entrada, setEntrada] = useState('documento');
    const [producto, setProducto] = useState(null);
    const [archivos, setArchivos] = useState([]);
    const [previews, setPreviews] = useState([]);
    const [ocupado, setOcupado] = useState(false);
    useEffect(() => { onOcupado?.(ocupado || Boolean(accion), pieza.id); }, [ocupado, accion, onOcupado, pieza.id]);
    // Al resolver una pieza su control desaparece: no debe dejar pausado al pedido.
    useEffect(() => () => onOcupado?.(false, pieza.id), [onOcupado, pieza.id]);
    const [error, setError] = useState('');
    const clave = useRef(uuidSolicitud());
    const vistasRef = useRef([]); vistasRef.current = previews;
    useEffect(() => () => vistasRef.current.forEach(p => { if (p.url?.startsWith('blob:')) URL.revokeObjectURL(p.url); }), []);
    const enviar = async () => {
        if (ocupado) return;
        if (accion === 'sustituir' && ((entrada === 'catalogo' && !producto) || (entrada === 'documento' && !archivos.length))) return setError('Seleccione la alternativa por SKU o adjunte su captura.');
        if (ejecutor) {
            setOcupado(true); setError('');
            try { const data = await ejecutor({ accion, entrada, producto, archivo: archivos[0] || null, solicitudId: clave.current }); setAccion(''); onActualizar?.(data); }
            catch (e) { setError(Object.values(e?.response?.data?.errors || {}).flat()[0] || e?.response?.data?.message || 'No se pudo registrar la decisión.'); }
            finally { setOcupado(false); }
            return;
        }
        const form = new FormData();
        form.append('revision_id', pieza.id); form.append('accion', accion); form.append('solicitud_id', clave.current);
        if (accion === 'sustituir') { form.append('entrada', entrada); if (producto && entrada === 'catalogo') form.append('producto_id', producto.id); if (archivos[0] && entrada === 'documento') form.append('evidencia', archivos[0]); }
        setOcupado(true); setError('');
        try { const { data } = await axios.post(`/control-pedidos/${pedidoId}/disponibilidad-tienda`, form, { headers: { Accept: 'application/json' } }); setAccion(''); onActualizar?.(data); }
        catch (e) { setError(Object.values(e?.response?.data?.errors || {}).flat()[0] || e?.response?.data?.message || 'No se pudo registrar la decisión.'); }
        finally { setOcupado(false); }
    };
    if (pieza.resolucion) return <p role="status" className="gelia-pedidos-atributo text-xs font-semibold theme-text-main m-0">{etiquetas[pieza.resolucion] || 'Decisión registrada'}</p>;
    if (soloLectura) return null;
    const permiteConfirmar = pieza.estado_fisico !== 'sin_existencia';
    const volver = () => setAccion('');
    const formulario = accion && <div className="tienda-accion-formulario grid gap-3 rounded-xl border theme-border p-3">
            <p className="text-sm font-semibold m-0 theme-text-main">{accion === 'confirmar' ? 'Confirmar la pieza en el estado reportado con el cliente' : accion === 'retirar' ? 'Retirar esta pieza del pedido' : 'Solicitar otra pieza en su lugar'}</p>
            {accion === 'sustituir' && <><div className="tienda-sustitucion-modos" role="group" aria-label="Cómo indicar la pieza alternativa"><button type="button" aria-pressed={entrada === 'documento'} onClick={() => setEntrada('documento')}><FileImage size={16} aria-hidden />Por captura o PDF</button><button type="button" aria-pressed={entrada === 'catalogo'} onClick={() => setEntrada('catalogo')}><Search size={16} aria-hidden />Por SKU</button></div>
                <p className="text-xs theme-text-muted m-0">{entrada === 'documento' ? 'Adjunta la captura o el PDF donde aparece la pieza que el cliente quiere en su lugar.' : 'Busca la pieza alternativa por SKU o nombre.'}</p>
                {entrada === 'catalogo' ? <SelectorProducto almacenId={almacenId} incluirSinInventario value={producto?.id || ''} onChange={(_, p) => setProducto(p)} disabled={ocupado} /> : <GaleriaEvidenciasPedido compacto accionesCamara zonaPegado mensajeLimite="La sustitución admite una sola captura o PDF. Si necesitas más piezas, usa «Modificar pedido» más abajo." label="Captura de la pieza alternativa" maxArchivos={1} archivos={archivos} previews={previews} onChange={(a,p) => { setArchivos(a); setPreviews(p); }} onVer={onVer} disabled={ocupado} />}
                <p className="text-xs theme-text-muted m-0">Quien revisó confirmará la disponibilidad de la alternativa. No cambia el pago ni confirma separación.</p>
            </>}
            <div className="flex gap-2 flex-wrap"><button type="button" className={THEME_BTN_PRIMARY} disabled={ocupado} onClick={enviar}><Send size={16} />{ocupado ? 'Enviando…' : accion === 'confirmar' ? 'Confirmar estado' : accion === 'retirar' ? 'Confirmar retiro' : 'Solicitar sustitución'}</button><button type="button" className={GELIA_BTN_OUTLINE} disabled={ocupado} onClick={volver}><X size={16} />Volver</button></div>
    </div>;
    const aviso = error && <p role="alert" className="tienda-alerta-disponibilidad text-sm m-0 p-3 rounded-xl">{error}</p>;

    return <div className={`grid gap-3 min-w-0 ${compacta ? 'tienda-acciones-compactas' : 'mt-3'}`} aria-label={`Acciones rápidas de ${descripcionVisible}`}>
        {compacta ? <>
            <MenuCompactoDisponibilidad descripcion={descripcionVisible} permiteConfirmar={permiteConfirmar} onElegir={elegir} />
            {accion && <DialogoAccionDisponibilidad accion={accion} ocupado={ocupado} onClose={volver}>
                {formulario}
                {aviso}
            </DialogoAccionDisponibilidad>}
        </> : <>
            {accion ? formulario : <div className="flex flex-wrap gap-2">
                {permiteConfirmar && <button type="button" className={GELIA_BTN_OUTLINE} onClick={() => elegir('confirmar')}><CheckCircle2 size={16} />Confirmar estado</button>}
                <button type="button" className={GELIA_BTN_OUTLINE} onClick={() => elegir('retirar')}><PackageMinus size={16} />Retirar</button>
                <button type="button" className={GELIA_BTN_OUTLINE} onClick={() => elegir('sustituir')}><ArrowRightLeft size={16} />Sustituir</button>
            </div>}
            {aviso}
        </>}
    </div>;
}
