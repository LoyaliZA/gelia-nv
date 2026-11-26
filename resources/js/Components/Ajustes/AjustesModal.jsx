import React, { useCallback, useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { AlertTriangle, BellRing, CheckCheck, Loader2, Palette, PanelLeft, Save, Settings, X } from 'lucide-react';
import useAjustes from './useAjustes';
import AjustesApariencia from './AjustesApariencia';
import AjustesAlertas from './AjustesAlertas';
import AjustesNavegacion from './AjustesNavegacion';
import {
    AJUSTES_OPEN_EVENT,
    AJUSTES_QUERY_PARAM,
    AJUSTES_TABS,
    normalizarTabAjustes,
} from './ajustesEvents';

const PESTANAS = [
    { id: 'apariencia', label: 'Apariencia', icon: Palette, Panel: AjustesApariencia },
    { id: 'alertas', label: 'Alertas', icon: BellRing, Panel: AjustesAlertas },
    { id: 'navegacion', label: 'Navegación', icon: PanelLeft, Panel: AjustesNavegacion },
];

/** Debe coincidir con la duración de geliaAjustesOut en ajustes.css. */
const SALIDA_MS = 160;

const FOCUSABLE = 'a[href], button:not([disabled]), select, input:not([type="hidden"]):not([disabled]), textarea, [tabindex]:not([tabindex="-1"])';

function AjustesDialogo({ tabInicial, onClose }) {
    const ajustes = useAjustes();
    const [tab, setTab] = useState(tabInicial);
    const [confirmando, setConfirmando] = useState(false);
    const [saliendo, setSaliendo] = useState(false);
    const dialogRef = useRef(null);
    const previoRef = useRef(typeof document !== 'undefined' ? document.activeElement : null);
    const titleId = useId();

    // Cierre suave: la salida se anima y recién después se desmonta el modal.
    const cerrarSuave = useCallback(() => {
        setConfirmando(false);
        setSaliendo(true);
        setTimeout(onClose, SALIDA_MS);
    }, [onClose]);

    const intentarCerrar = useCallback(() => {
        if (ajustes.sinGuardar) setConfirmando(true);
        else cerrarSuave();
    }, [ajustes.sinGuardar, cerrarSuave]);

    const descartarYCerrar = () => {
        ajustes.descartar();
        cerrarSuave();
    };

    const guardarYCerrar = async () => {
        const guardado = await ajustes.guardar();
        // Deja ver el botón en verde un instante antes de cerrar.
        if (guardado) setTimeout(cerrarSuave, 650);
    };

    // Bloqueo de scroll del documento y devolución del foco al cerrar.
    useEffect(() => {
        const overflowPrevio = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        dialogRef.current?.focus();
        const previo = previoRef.current;
        return () => {
            document.body.style.overflow = overflowPrevio;
            previo?.focus?.();
        };
    }, []);

    useEffect(() => {
        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                event.stopPropagation();
                if (confirmando) setConfirmando(false);
                else intentarCerrar();
                return;
            }
            if (event.key !== 'Tab') return;
            const items = [...(dialogRef.current?.querySelectorAll(FOCUSABLE) || [])].filter((el) => el.offsetParent !== null);
            if (items.length === 0) return;
            const primero = items[0];
            const ultimo = items[items.length - 1];
            if (event.shiftKey && (document.activeElement === primero || document.activeElement === dialogRef.current)) {
                event.preventDefault();
                ultimo.focus();
            } else if (!event.shiftKey && document.activeElement === ultimo) {
                event.preventDefault();
                primero.focus();
            }
        };
        document.addEventListener('keydown', onKeyDown, true);
        return () => document.removeEventListener('keydown', onKeyDown, true);
    }, [confirmando, intentarCerrar]);

    const moverPestana = (event, indice) => {
        const salto = { ArrowRight: 1, ArrowLeft: -1 }[event.key];
        if (!salto) return;
        event.preventDefault();
        const siguiente = PESTANAS[(indice + salto + PESTANAS.length) % PESTANAS.length];
        setTab(siguiente.id);
        document.getElementById(`ajustes-tab-${siguiente.id}`)?.focus();
    };

    const activa = PESTANAS.find((p) => p.id === tab) || PESTANAS[0];
    const Panel = activa.Panel;
    const ocupado = ajustes.guardando || ajustes.comprimiendo;

    return createPortal(
        <div className="gelia-ajustes-overlay" data-saliendo={saliendo} onMouseDown={(e) => { if (e.target === e.currentTarget) intentarCerrar(); }}>
            <div
                ref={dialogRef}
                className="gelia-ajustes"
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                tabIndex={-1}
            >
                <header className="gelia-ajustes__head">
                    <span className="gelia-ajustes__tile gelia-ajustes__tile--lg" aria-hidden><Settings className="w-5 h-5" /></span>
                    <div className="min-w-0 flex-1">
                        <h2 id={titleId} className="gelia-ajustes__title">Ajustes_</h2>
                        <p className="gelia-ajustes__subtitle">Los cambios se ven al instante. Guarda para conservarlos en tu cuenta.</p>
                    </div>
                    <button type="button" className="gelia-ajustes__cerrar" onClick={intentarCerrar} aria-label="Cerrar ajustes">
                        <X className="w-5 h-5" aria-hidden />
                    </button>
                </header>

                <div className="gelia-ajustes__tabs" role="tablist" aria-label="Secciones de ajustes">
                    {PESTANAS.map((p, i) => {
                        const Icon = p.icon;
                        const seleccionada = p.id === activa.id;
                        return (
                            <button
                                key={p.id}
                                type="button"
                                role="tab"
                                id={`ajustes-tab-${p.id}`}
                                aria-selected={seleccionada}
                                aria-controls={`ajustes-panel-${p.id}`}
                                tabIndex={seleccionada ? 0 : -1}
                                className="gelia-ajustes__tab"
                                data-active={seleccionada}
                                onClick={() => setTab(p.id)}
                                onKeyDown={(e) => moverPestana(e, i)}
                            >
                                <Icon className="w-4 h-4" aria-hidden />
                                <span>{p.label}</span>
                            </button>
                        );
                    })}
                </div>

                <div
                    className="gelia-ajustes__body custom-scrollbar"
                    role="tabpanel"
                    id={`ajustes-panel-${activa.id}`}
                    aria-labelledby={`ajustes-tab-${activa.id}`}
                >
                    <Panel ajustes={ajustes} />
                </div>

                {confirmando && (
                    <div className="gelia-ajustes__confirmar" role="alertdialog" aria-label="Cambios sin guardar">
                        <p><AlertTriangle className="w-4 h-4" aria-hidden /> Tienes cambios sin guardar. Si cierras, la vista previa se revierte.</p>
                        <div className="gelia-ajustes__acciones">
                            <button type="button" className="gelia-ajustes__btn" onClick={() => setConfirmando(false)}>Seguir editando</button>
                            <button type="button" className="gelia-ajustes__btn gelia-ajustes__btn--peligro" onClick={descartarYCerrar} disabled={ocupado}>Descartar cambios</button>
                            <button
                                type="button"
                                className="gelia-ajustes__btn gelia-ajustes__btn--primario"
                                data-estado={ajustes.estadoGuardado}
                                onClick={guardarYCerrar}
                                disabled={ocupado}
                            >
                                {ajustes.guardando ? <Loader2 className="w-4 h-4 gelia-ajustes__spin" aria-hidden /> : <Save className="w-4 h-4" aria-hidden />}
                                Guardar y cerrar
                            </button>
                        </div>
                    </div>
                )}

                <footer className="gelia-ajustes__foot">
                    <p
                        className="gelia-ajustes__estado"
                        data-tipo={ajustes.aviso?.tipo || (ajustes.sinGuardar ? 'pendiente' : 'limpio')}
                        role="status"
                        aria-live="polite"
                    >
                        {ajustes.aviso ? (
                            <>
                                <AlertTriangle className="w-4 h-4" aria-hidden />
                                {ajustes.aviso.texto}
                            </>
                        ) : ajustes.sinGuardar ? 'Vista previa activa: hay cambios sin guardar.' : 'Sin cambios pendientes.'}
                    </p>
                    <div className="gelia-ajustes__acciones">
                        <button type="button" className="gelia-ajustes__btn" onClick={intentarCerrar}>Cerrar</button>
                        <button
                            type="button"
                            className="gelia-ajustes__btn gelia-ajustes__btn--primario"
                            data-estado={ajustes.estadoGuardado}
                            onClick={ajustes.guardar}
                            disabled={ocupado || (!ajustes.sinGuardar && ajustes.estadoGuardado !== 'guardado')}
                            aria-live="polite"
                        >
                            {ajustes.estadoGuardado === 'guardado'
                                ? <CheckCheck className="w-4 h-4 gelia-ajustes__palomas" aria-hidden />
                                : ocupado
                                    ? <Loader2 className="w-4 h-4 gelia-ajustes__spin" aria-hidden />
                                    : <Save className="w-4 h-4" aria-hidden />}
                            {ajustes.estadoGuardado === 'guardado'
                                ? 'Guardado'
                                : ajustes.guardando ? 'Guardando…' : 'Guardar cambios'}
                        </button>
                    </div>
                </footer>
            </div>
        </div>,
        document.body
    );
}

/**
 * Modal de Ajustes: se abre desde cualquier vista con `abrirAjustes()` o con `?ajustes=<pestaña>` en la URL.
 * El contenido (y su estado) solo existe mientras el modal está abierto.
 */
export default function AjustesModal() {
    const [abierto, setAbierto] = useState(false);
    const [tab, setTab] = useState('apariencia');

    const abrir = useCallback((destino) => {
        setTab(normalizarTabAjustes(destino));
        setAbierto(true);
    }, []);

    useEffect(() => {
        const onAbrir = (event) => abrir(event.detail?.tab);
        window.addEventListener(AJUSTES_OPEN_EVENT, onAbrir);
        return () => window.removeEventListener(AJUSTES_OPEN_EVENT, onAbrir);
    }, [abrir]);

    // Enlaces antiguos (/perfil/preferencias) llegan como ?ajustes=… y se limpian de la URL.
    useEffect(() => {
        const url = new URL(window.location.href);
        if (!url.searchParams.has(AJUSTES_QUERY_PARAM)) return;
        const pedido = url.searchParams.get(AJUSTES_QUERY_PARAM);
        const hash = url.hash.replace('#', '');
        abrir(AJUSTES_TABS.includes(pedido) ? pedido : hash);
        url.searchParams.delete(AJUSTES_QUERY_PARAM);
        window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`);
    }, [abrir]);

    if (!abierto) return null;
    return <AjustesDialogo tabInicial={tab} onClose={() => setAbierto(false)} />;
}
