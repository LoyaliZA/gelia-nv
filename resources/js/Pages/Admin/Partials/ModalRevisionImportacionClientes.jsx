import React, { useCallback, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { X } from 'lucide-react';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import GeliaLoader from '../../../Components/GeliaLoader';
import { THEME_BTN_PRIMARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';
import { FASES_CARGA } from '../../../utils/backgroundTaskAdapters';

const TEXTOS = {
    inactivacion: 'Hay inactivaciones propuestas. GELIA no las escribe hasta definir una señal explícita de baja.',
    codigo_vacio: 'Un código de lista vacío cambiaría el estado o la lista.',
    lista_bloqueada: 'El archivo reemplazaría una lista protegida.',
    archivo_repetido: 'Este archivo ya fue importado.',
    archivo_anterior: 'Hay montos más recientes que la fecha del archivo.',
    archivo_invalido: 'El archivo completo no se puede confirmar. Hay que corregirlo y revisarlo de nuevo.',
    descenso_lista: 'Hay descensos de lista. Quedan a la vista en esta revisión.',
    diferencia_monto: 'Hay diferencias de monto de 1000 o más. No se corrigen solas.',
};

const CONTEOS = [
    ['filas', 'Registros totales'],
    ['nuevos', 'Nuevos clientes'],
    ['actualizaciones', 'Actualizaciones'],
    ['sin_cambios', 'Sin cambios'],
    ['errores', 'Errores'],
    ['activaciones', 'Activaciones'],
    ['inactivaciones', 'Inactivaciones propuestas'],
    ['cambios_lista', 'Cambios de lista'],
    ['descensos', 'Descensos de lista'],
    ['cambios_monto', 'Diferencias monetarias'],
];

const FILTROS = [
    ['', 'Todos'],
    ['nuevo', 'Nuevos'],
    ['actualizacion', 'Actualizaciones'],
    ['sin_cambios', 'Sin cambios'],
    ['error', 'Errores'],
];

const formatoMoneda = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

export default function ModalRevisionImportacionClientes({ importacionId, onClose, onElegirOtro, onResultados }) {
    const [progreso, setProgreso] = useState(null);
    const [filas, setFilas] = useState(null);
    const [q, setQ] = useState('');
    const [categoria, setCategoria] = useState('');
    const [incidencias, setIncidencias] = useState(false);
    const [cargando, setCargando] = useState(true);
    const [confirmando, setConfirmando] = useState(false);
    const [error, setError] = useState(null);

    const cargar = useCallback(async (pagina = 1) => {
        if (!importacionId) return;
        const { data } = await axios.get(route('admin.clientes.importaciones.revision', importacionId), {
            params: { page: pagina, q, categoria, incidencias: incidencias ? 1 : 0 },
        });
        setProgreso(data.progreso);
        setFilas(data.filas);
    }, [importacionId, q, categoria, incidencias]);

    useEffect(() => {
        let cancelled = false;
        const tick = async () => {
            try {
                const { data } = await axios.get(route('admin.clientes.importaciones.progreso', importacionId));
                if (!cancelled) setProgreso(data);
            } catch (e) {
                if (!cancelled) setError(e.response?.data?.message || 'No se pudo consultar la revisión.');
            } finally {
                if (!cancelled) setCargando(false);
            }
        };
        tick();
        const timer = setInterval(tick, 2000);
        return () => {
            cancelled = true;
            clearInterval(timer);
        };
    }, [importacionId]);

    useEffect(() => {
        const fase = progreso?.fase;
        if (!fase || fase === 'analizando' || fase === 'archivo_recibido' || fase === 'procesando') return undefined;
        let cancelled = false;
        cargar().catch((e) => {
            if (!cancelled) setError(e.response?.data?.message || 'No se pudo consultar el detalle.');
        });
        return () => { cancelled = true; };
    }, [progreso?.fase, cargar]);

    useEffect(() => {
        const previo = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => { document.body.style.overflow = previo; };
    }, []);

    const confirmar = async () => {
        if (!progreso?.puede_confirmar || !progreso.token || confirmando) return;
        setConfirmando(true);
        setError(null);
        try {
            const { data } = await axios.post(route('admin.clientes.importar.confirmar'), {
                importacion_id: importacionId,
                token: progreso.token,
            });
            setProgreso(data);
        } catch (e) {
            setError(e.response?.data?.mensaje || e.response?.data?.errors?.archivo?.[0] || 'No se pudo confirmar la revisión.');
        } finally {
            setConfirmando(false);
        }
    };

    const conteos = progreso?.conteos || {};
    const esperando = progreso?.fase === 'esperando_confirmacion';
    const terminal = ['completado', 'completado_con_incidencias', 'error'].includes(progreso?.fase);

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} z-[200]`} onClick={onClose}>
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="revision-importacion-titulo"
                className={`${THEME_MODAL_SHELL} max-w-4xl w-[min(100vw-1.5rem,56rem)] max-h-[min(100dvh-1.5rem,44rem)] flex flex-col`}
                onClick={(event) => event.stopPropagation()}
            >
                <header className="flex items-start justify-between gap-3 p-4 sm:p-5 border-b theme-border">
                    <div className="min-w-0">
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Revisión de carga masiva</p>
                        <h2 id="revision-importacion-titulo" className="text-lg font-black theme-text-main m-0 truncate">
                            {progreso?.nombre_archivo || 'Archivo de clientes'}
                        </h2>
                        <p className="text-sm theme-text-main m-0 mt-1">{FASES_CARGA[progreso?.fase] || 'Consultando'}</p>
                    </div>
                    <button type="button" onClick={onClose} aria-label="Cerrar revisión" className="p-2 rounded-full theme-element theme-text-muted">
                        <X className="w-5 h-5" />
                    </button>
                </header>

                <div className="flex-1 overflow-y-auto p-4 sm:p-5 space-y-4">
                    {cargando && !progreso ? <GeliaLoader /> : null}
                    {progreso?.indeterminado && !esperando && (
                        <p className="text-sm theme-text-muted m-0">El avance todavía no tiene un total confirmado.</p>
                    )}
                    {progreso && !progreso.indeterminado && (
                        <p className="text-sm theme-text-main m-0">Procesado {progreso.porcentaje}% · {progreso.filas_procesadas}/{progreso.filas_leidas}</p>
                    )}
                    {esperando && (
                        <dl className="grid grid-cols-2 sm:grid-cols-3 gap-3 m-0">
                            {CONTEOS.map(([clave, etiqueta]) => (
                                <div key={clave}>
                                    <dt className="text-xs theme-text-muted">{etiqueta}</dt>
                                    <dd className="text-lg font-bold theme-text-main m-0">{conteos[clave] ?? 0}</dd>
                                </div>
                            ))}
                        </dl>
                    )}
                    {(progreso?.bloqueos || []).map((bloqueo) => (
                        <p key={bloqueo.codigo} className="text-sm theme-text-main m-0">{TEXTOS[bloqueo.codigo] || bloqueo.codigo} ({bloqueo.filas})</p>
                    ))}
                    {(progreso?.avisos || []).map((aviso) => (
                        <p key={aviso.codigo} className="text-sm theme-text-main m-0">{TEXTOS[aviso.codigo] || aviso.codigo} ({aviso.filas})</p>
                    ))}
                    {(error || progreso?.mensaje) && <p className="text-sm font-bold theme-text-peligro m-0">{error || progreso.mensaje}</p>}

                    <div className="flex flex-col sm:flex-row gap-2">
                        <input
                            value={q}
                            onChange={(event) => setQ(event.target.value)}
                            placeholder="Buscar número, nombre o motivo"
                            aria-label="Buscar en la revisión"
                            className="theme-element border theme-border rounded-xl px-3 py-2 text-sm theme-text-main flex-1"
                        />
                        <select value={categoria} onChange={(event) => setCategoria(event.target.value)} aria-label="Filtrar registros" className="theme-element border theme-border rounded-xl px-3 py-2 text-sm theme-text-main">
                            {FILTROS.map(([valor, etiqueta]) => <option key={valor || 'todos'} value={valor}>{etiqueta}</option>)}
                        </select>
                        <button type="button" onClick={() => setIncidencias((valor) => !valor)} className="theme-element border theme-border rounded-xl px-3 py-2 text-xs font-bold theme-text-main">
                            {incidencias ? 'Ver todos' : 'Solo incidencias'}
                        </button>
                    </div>

                    <div className="space-y-2">
                        {(filas?.data || []).map((fila) => (
                            <article key={fila.id} className="theme-element border theme-border rounded-xl p-3">
                                <p className="text-sm font-bold theme-text-main m-0 break-words">
                                    {fila.numero_cliente || 'Sin número'} {fila.nombre ? `· ${fila.nombre}` : ''}
                                </p>
                                <p className="text-xs theme-text-muted m-0 mt-1">{fila.categoria}{fila.motivo ? ` · ${fila.motivo}` : ''}</p>
                                {(fila.monto_anterior != null || fila.monto_nuevo != null) && (
                                    <p className="text-xs theme-text-main m-0 mt-1">
                                        {formatoMoneda.format(fila.monto_anterior || 0)} → {formatoMoneda.format(fila.monto_nuevo || 0)}
                                    </p>
                                )}
                            </article>
                        ))}
                        {filas && filas.total === 0 && <p className="text-sm theme-text-muted m-0">No hay registros para este filtro.</p>}
                    </div>
                    {filas && <GeliaPaginacion paginator={filas} embedded onIrAPagina={(pagina) => cargar(pagina)} />}
                </div>

                <footer className="p-4 sm:p-5 border-t theme-border flex flex-col sm:flex-row gap-2">
                    <button type="button" onClick={onClose} className="theme-element border theme-border rounded-xl px-4 py-3 text-xs font-bold theme-text-main">Cerrar</button>
                    {(error || progreso?.fase === 'error') && (
                        <button type="button" onClick={onElegirOtro} className="theme-element border theme-border rounded-xl px-4 py-3 text-xs font-bold theme-text-main">Revisar otro archivo</button>
                    )}
                    {terminal && progreso?.fase !== 'error' && (
                        <button type="button" onClick={() => onResultados(importacionId)} className="theme-element border theme-border rounded-xl px-4 py-3 text-xs font-bold theme-text-main">Consultar resultados</button>
                    )}
                    <button type="button" onClick={confirmar} disabled={!progreso?.puede_confirmar || confirmando} className={`${THEME_BTN_PRIMARY} rounded-xl px-4 py-3 text-xs font-black uppercase sm:ml-auto disabled:opacity-50`}>
                        {progreso?.bloqueado ? 'Escritura detenida' : confirmando ? 'Confirmando…' : 'Confirmar importación'}
                    </button>
                </footer>
            </div>
        </div>,
        document.body,
    );
}
