import { uuidSolicitud } from '../../../utils/uuidSolicitud';
import React, { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { CheckCircle2, PackageMinus, ArrowRightLeft, Send, X, Search, FileImage, MoreHorizontal } from 'lucide-react';
import { GELIA_BTN_OUTLINE, THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';
import SelectorProducto from '../../../Components/Almacenes/SelectorProducto';
import GaleriaEvidenciasPedido from './GaleriaEvidenciasPedido';
const etiquetas = { aceptar_condicion: 'Estado confirmado con el cliente', retirar: 'Retiro registrado', sustituir: 'Sustitución solicitada' };

export default function AccionesRapidasDisponibilidad({ pedidoId, pieza, almacenId, onActualizar, onVer, soloLectura = false, compacta = false }) {
    const menuRef = useRef(null);
    useEffect(() => {
        const cerrar = e => { if (menuRef.current && !menuRef.current.contains(e.target)) menuRef.current.open = false; };
        const teclado = e => { if (e.key === 'Escape' && menuRef.current?.open) { e.preventDefault(); e.stopImmediatePropagation(); menuRef.current.open = false; menuRef.current.querySelector('summary')?.focus(); } };
        document.addEventListener('pointerdown', cerrar); window.addEventListener('keydown', teclado, true);
        return () => { document.removeEventListener('pointerdown', cerrar); window.removeEventListener('keydown', teclado, true); };
    }, []);
    const elegir = valor => { if (menuRef.current) menuRef.current.open = false; setAccion(valor); setError(''); };
    const [accion, setAccion] = useState('');
    const [entrada, setEntrada] = useState('catalogo');
    const [producto, setProducto] = useState(null);
    const [archivos, setArchivos] = useState([]);
    const [previews, setPreviews] = useState([]);
    const [ocupado, setOcupado] = useState(false);
    const [error, setError] = useState('');
    const clave = useRef(uuidSolicitud());
    const vistasRef = useRef([]); vistasRef.current = previews;
    useEffect(() => () => vistasRef.current.forEach(p => { if (p.url?.startsWith('blob:')) URL.revokeObjectURL(p.url); }), []);
    const enviar = async () => {
        if (ocupado) return;
        if (accion === 'sustituir' && ((entrada === 'catalogo' && !producto) || (entrada === 'documento' && !archivos.length))) return setError('Seleccione la alternativa por SKU o adjunte su captura.');
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
    return <div className={`grid gap-3 min-w-0 ${compacta ? 'tienda-acciones-compactas' : 'mt-3'}`} aria-label={`Acciones rápidas de ${pieza.descripcion_producto || pieza.descripcion}`}>
        {!accion ? compacta ? <details ref={menuRef} className="tienda-respuesta-menu">
            <summary className={GELIA_BTN_OUTLINE} aria-label={`Acciones de ${pieza.descripcion_producto || pieza.descripcion}`}><MoreHorizontal size={20} aria-hidden /></summary>
            <div className="tienda-respuesta-menu-lista">
                {pieza.estado_fisico !== 'sin_existencia' && <button type="button" onClick={() => elegir('confirmar')}><CheckCircle2 size={16} aria-hidden />Confirmar estado</button>}
                <button type="button" onClick={() => elegir('retirar')}><PackageMinus size={16} aria-hidden />Retirar</button>
                <button type="button" onClick={() => elegir('sustituir')}><ArrowRightLeft size={16} aria-hidden />Sustituir</button>
            </div>
        </details> : <div className="flex flex-wrap gap-2">
            {pieza.estado_fisico !== 'sin_existencia' && <button type="button" className={GELIA_BTN_OUTLINE} onClick={() => elegir('confirmar')}><CheckCircle2 size={16} />Confirmar estado</button>}
            <button type="button" className={GELIA_BTN_OUTLINE} onClick={() => elegir('retirar')}><PackageMinus size={16} />Retirar</button>
            <button type="button" className={GELIA_BTN_OUTLINE} onClick={() => elegir('sustituir')}><ArrowRightLeft size={16} />Sustituir</button>
        </div> : <div className="tienda-accion-formulario grid gap-3 rounded-xl border theme-border p-3">
            <p className="text-sm font-semibold m-0 theme-text-main">{accion === 'confirmar' ? 'Confirmar la pieza en el estado reportado con el cliente' : accion === 'retirar' ? 'Retirar esta pieza del pedido' : 'Solicitar otra pieza en su lugar'}</p>
            {accion === 'sustituir' && <><div className="flex flex-wrap gap-2"><button type="button" aria-pressed={entrada === 'catalogo'} className={GELIA_BTN_OUTLINE} onClick={() => setEntrada('catalogo')}><Search size={16} />Por SKU</button><button type="button" aria-pressed={entrada === 'documento'} className={GELIA_BTN_OUTLINE} onClick={() => setEntrada('documento')}><FileImage size={16} />Por captura o PDF</button></div>
                {entrada === 'catalogo' ? <SelectorProducto almacenId={almacenId} incluirSinInventario value={producto?.id || ''} onChange={(_, p) => setProducto(p)} disabled={ocupado} /> : <GaleriaEvidenciasPedido compacto label="Pieza alternativa" maxArchivos={1} archivos={archivos} previews={previews} onChange={(a,p) => { setArchivos(a); setPreviews(p); }} onVer={onVer} disabled={ocupado} />}
                <p className="text-xs theme-text-muted m-0">Quien revisó confirmará la disponibilidad de la alternativa. No cambia el pago ni confirma separación.</p>
            </>}
            <div className="flex gap-2 flex-wrap"><button type="button" className={THEME_BTN_PRIMARY} disabled={ocupado} onClick={enviar}><Send size={16} />{ocupado ? 'Enviando…' : accion === 'confirmar' ? 'Confirmar estado' : accion === 'retirar' ? 'Confirmar retiro' : 'Solicitar sustitución'}</button><button type="button" className={GELIA_BTN_OUTLINE} disabled={ocupado} onClick={() => setAccion('')}><X size={16} />Volver</button></div>
        </div>}
        {error && <p role="alert" className="tienda-alerta-disponibilidad text-sm m-0 p-3 rounded-xl">{error}</p>}
    </div>;
}
