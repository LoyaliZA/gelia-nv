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
    codigo_vacio: 'Un código de lista vacío cambiaría el estado, la lista o la condición de menudeo.',
    lista_bloqueada: 'El archivo reemplazaría una lista protegida.',
    archivo_repetido: 'Este archivo ya fue importado.',
    archivo_anterior: 'Hay montos más recientes que la fecha del archivo.',
    archivo_invalido: 'El archivo completo no se puede confirmar. Hay que corregirlo y revisarlo de nuevo.',
    descenso_lista: 'Hay descensos de lista. Quedan a la vista en esta revisión.',
    diferencia_monto: 'Si confirmas el archivo y la fila es válida, el monto se actualiza con el valor importado. Una diferencia de 1000 o más queda visible para revisión.',
    lista_mayorista_sin_conversion: 'Un código mayorista no retira Menudeo ni asigna esa lista sin una conversión autorizada.',
    conversion_autorizada: 'Una importación con Público General no vuelve a marcar Menudeo si la conversión ya fue autorizada.',
};

const CONTEOS = [
    ['filas', 'Registros totales', false],
    ['nuevos', 'Clientes nuevos', false],
    ['actualizaciones', 'Actualizaciones', true],
    ['sin_cambios', 'Sin cambios', false],
    ['errores', 'Errores', true],
    ['cambios_lista', 'Cambios de lista', true],
    ['inactivaciones', 'Inactivaciones propuestas', true],
    ['cambios_monto', 'Diferencias monetarias', true],
];

const FILTROS = [
    ['con_cambios', 'Con cambios'],
    ['', 'Todos'],
    ['nuevo', 'Nuevos'],
    ['actualizacion', 'Actualizaciones'],
    ['sin_cambios', 'Sin cambios'],
    ['error', 'Errores'],
];

const TIPOS = {
    nuevo: 'Nuevo',
    actualizacion: 'Actualización',
    sin_cambios: 'Sin cambios',
    error: 'Error',
};

const formatoMoneda = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

const dinero = (valor) => formatoMoneda.format(Number(valor) || 0);

function etapaDe(fase) {
    if (fase === 'esperando_confirmacion') return 'revision';
    if (['completado', 'completado_con_incidencias', 'error'].includes(fase)) return 'resultados';
    return 'proceso';
}

function tonoAviso(codigo, bloqueo) {
    if (bloqueo || codigo === 'archivo_invalido' || codigo === 'inactivacion' || codigo === 'lista_bloqueada' || codigo === 'archivo_anterior') {
        return 'theme-text-peligro';
    }
    if (codigo === 'diferencia_monto' || codigo === 'descenso_lista' || codigo === 'lista_mayorista_sin_conversion' || codigo === 'conversion_autorizada') {
        return 'theme-text-aviso';
    }
    return 'theme-text-info';
}

export default function ModalRevisionImportacionClientes({ importacionId, onClose, onElegirOtro, onResultados }) {
    const [progreso, setProgreso] = useState(null);
    const [filas, setFilas] = useState(null);
    const [q, setQ] = useState('');
    const [categoria, setCategoria] = useState('con_cambios');
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
        if (!fase || etapaDe(fase) !== 'revision') return undefined;
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
            setFilas(null);
        } catch (e) {
            setError(e.response?.data?.mensaje || e.response?.data?.errors?.archivo?.[0] || 'No se pudo confirmar la revisión.');
        } finally {
            setConfirmando(false);
        }
    };

    const conteos = progreso?.conteos || {};
    const etapa = etapaDe(progreso?.fase);
    const atendidas = (progreso?.filas_procesadas || 0) + (progreso?.filas_omitidas || 0) + (progreso?.errores || 0);
    const porcentaje = progreso?.indeterminado ? null : (progreso?.porcentaje ?? 0);

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} z-[200]`} onClick={onClose}>
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="revision-importacion-titulo"
                className={`${THEME_MODAL_SHELL} w-[min(100vw-1rem,72rem)] max-h-[min(100dvh-1rem,52rem)] flex flex-col`}
                onClick={(event) => event.stopPropagation()}
            >
                <header className="shrink-0 flex items-start justify-between gap-3 p-4 sm:p-5 border-b theme-border">
                    <div className="min-w-0">
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Carga masiva de clientes</p>
                        <h2 id="revision-importacion-titulo" className="text-lg font-black theme-text-main m-0 truncate">
                            {progreso?.nombre_archivo || 'Archivo de clientes'}
                        </h2>
                        <p className="text-sm theme-text-main m-0 mt-1">
                            {FASES_CARGA[progreso?.fase] || 'Consultando'}
                            <span className="theme-text-muted"> · Importación {importacionId}</span>
                        </p>
                    </div>
                    <button type="button" onClick={onClose} aria-label="Cerrar revisión" className="p-2 rounded-full theme-element theme-text-muted shrink-0">
                        <X className="w-5 h-5" />
                    </button>
                </header>

                <div className="flex-1 min-h-0 overflow-y-auto p-4 sm:p-5 space-y-4">
                    {cargando && !progreso ? <GeliaLoader /> : null}
                    {(error || progreso?.mensaje) && <p className="text-sm font-bold theme-text-peligro m-0">{error || progreso.mensaje}</p>}

                    {etapa === 'revision' && progreso && (
                        <>
                            <dl className="grid grid-cols-2 sm:grid-cols-4 gap-2 m-0">
                                {CONTEOS.map(([clave, etiqueta, destaca]) => (
                                    <div key={clave} className={`rounded-xl border theme-border p-3 ${destaca && (conteos[clave] ?? 0) > 0 ? 'theme-element' : ''}`}>
                                        <dt className="text-[11px] theme-text-muted m-0">{etiqueta}</dt>
                                        <dd className={`text-xl font-black m-0 tabular-nums ${destaca && (conteos[clave] ?? 0) > 0 ? 'theme-text-aviso' : 'theme-text-main'} ${clave === 'sin_cambios' ? 'theme-text-muted' : ''}`}>{conteos[clave] ?? 0}</dd>
                                    </div>
                                ))}
                            </dl>
                            {(progreso.bloqueos || []).map((bloqueo) => (
                                <p key={bloqueo.codigo} className={`text-sm m-0 ${tonoAviso(bloqueo.codigo, true)}`}>
                                    Bloqueo de archivo. {TEXTOS[bloqueo.codigo] || bloqueo.codigo} ({bloqueo.filas})
                                </p>
                            ))}
                            {(progreso.avisos || []).map((aviso) => (
                                <p key={aviso.codigo} className={`text-sm m-0 ${tonoAviso(aviso.codigo, false)}`}>
                                    {aviso.codigo === 'diferencia_monto' ? 'Aviso.' : 'Advertencia.'} {TEXTOS[aviso.codigo] || aviso.codigo} ({aviso.filas})
                                </p>
                            ))}
                            <div className="flex flex-col sm:flex-row gap-2">
                                <input
                                    value={q}
                                    onChange={(event) => setQ(event.target.value)}
                                    placeholder="Buscar número, nombre o motivo"
                                    aria-label="Buscar en la revisión"
                                    className="theme-element border theme-border rounded-xl px-3 py-2 text-sm theme-text-main flex-1 min-w-0"
                                />
                                <select value={categoria} onChange={(event) => setCategoria(event.target.value)} aria-label="Filtrar registros" className="theme-element border theme-border rounded-xl px-3 py-2 text-sm theme-text-main">
                                    {FILTROS.map(([valor, etiqueta]) => <option key={valor || 'todos'} value={valor}>{etiqueta}</option>)}
                                </select>
                                <button type="button" onClick={() => setIncidencias((valor) => !valor)} className="theme-element border theme-border rounded-xl px-3 py-2 text-xs font-bold theme-text-main">
                                    {incidencias ? 'Ver todos' : 'Solo incidencias'}
                                </button>
                            </div>
                            <div className="space-y-2">
                                {(filas?.data || []).map((fila) => {
                                    const cambios = Array.isArray(fila.cambios) ? fila.cambios : [];
                                    const tono = fila.categoria === 'error' || fila.es_bloqueo ? 'theme-text-peligro' : fila.es_aviso ? 'theme-text-aviso' : 'theme-text-info';
                                    const etiqueta = fila.categoria === 'error' || fila.es_bloqueo ? 'Error de fila' : fila.es_aviso ? 'Advertencia' : 'Aviso';
                                    return (
                                        <article key={fila.id} className="theme-element border theme-border rounded-xl p-3 min-w-0">
                                            <p className="text-sm font-bold theme-text-main m-0 break-words">
                                                {fila.numero_cliente || 'Sin número'} {fila.nombre ? `· ${fila.nombre}` : ''}
                                            </p>
                                            <p className="text-xs theme-text-muted m-0 mt-1">{TIPOS[fila.categoria] || fila.categoria}</p>
                                            {cambios.map((cambio) => (
                                                <p key={`${fila.id}-${cambio.campo}`} className="text-xs theme-text-main m-0 mt-1 break-words">
                                                    {cambio.campo}: {cambio.campo === 'Monto' ? `${dinero(cambio.anterior)} → ${dinero(cambio.nuevo)}` : `${cambio.anterior} → ${cambio.nuevo}`}
                                                </p>
                                            ))}
                                            {cambios.length === 0 && (fila.monto_anterior != null || fila.monto_nuevo != null) && (
                                                <p className="text-xs theme-text-main m-0 mt-1">
                                                    Monto: {dinero(fila.monto_anterior)} → {dinero(fila.monto_nuevo)}
                                                </p>
                                            )}
                                            {fila.motivo && <p className={`text-xs m-0 mt-1 break-words ${tono}`}>{etiqueta}. {fila.motivo}</p>}
                                        </article>
                                    );
                                })}
                                {filas && filas.total === 0 && <p className="text-sm theme-text-muted m-0">No hay registros para este filtro.</p>}
                            </div>
                            {filas && <GeliaPaginacion paginator={filas} embedded onIrAPagina={(pagina) => cargar(pagina)} />}
                        </>
                    )}

                    {etapa === 'proceso' && progreso && (
                        <div className="space-y-4">
                            <p className="text-sm theme-text-main m-0">{FASES_CARGA[progreso.fase] || 'Procesando'}</p>
                            <div className="h-2 rounded-full theme-element overflow-hidden" role="progressbar" aria-valuenow={porcentaje ?? 0} aria-valuemin={0} aria-valuemax={100}>
                                <div className="h-full bg-[var(--color-primario)] transition-all" style={{ width: `${porcentaje ?? 8}%` }} />
                            </div>
                            <p className="text-sm font-bold theme-text-main m-0 tabular-nums">
                                {porcentaje === null ? 'El avance todavía no tiene un total confirmado.' : `${porcentaje}% · ${atendidas}/${progreso.filas_leidas || 0}`}
                            </p>
                            <dl className="grid grid-cols-2 sm:grid-cols-3 gap-2 m-0">
                                {[
                                    ['Pendientes', progreso.pendientes ?? '—'],
                                    ['Actualizadas', progreso.actualizados ?? 0],
                                    ['Omitidas', progreso.filas_omitidas ?? 0],
                                    ['Errores', progreso.errores ?? 0],
                                    ['Atendidas', atendidas],
                                    ['Totales', progreso.filas_leidas ?? 0],
                                ].map(([etiqueta, valor]) => (
                                    <div key={etiqueta} className="rounded-xl border theme-border p-3">
                                        <dt className="text-[11px] theme-text-muted m-0">{etiqueta}</dt>
                                        <dd className="text-lg font-black theme-text-main m-0 tabular-nums">{valor}</dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    )}

                    {etapa === 'resultados' && progreso && (
                        <div className="space-y-4">
                            <p className={`text-sm font-bold m-0 ${progreso.fase === 'completado' ? 'theme-text-exito' : 'theme-text-aviso'}`}>
                                {progreso.fase === 'completado'
                                    ? 'La importación terminó.'
                                    : progreso.fase === 'error'
                                        ? 'La importación no terminó.'
                                        : 'La importación terminó con incidencias. Los registros omitidos o con error no se escribieron otra vez.'}
                            </p>
                            <dl className="grid grid-cols-2 sm:grid-cols-4 gap-2 m-0">
                                {[
                                    ['Procesados', progreso.filas_procesadas ?? 0],
                                    ['Omitidos', progreso.filas_omitidas ?? 0],
                                    ['Errores', progreso.errores ?? 0],
                                    ['Actualizados', progreso.actualizados ?? 0],
                                    ['Cambios de lista', conteos.cambios_lista ?? 0],
                                    ['Diferencias monetarias', conteos.cambios_monto ?? 0],
                                    ['Nuevos', conteos.nuevos ?? progreso.creados ?? 0],
                                    ['Sin cambios', conteos.sin_cambios ?? progreso.sin_cambios ?? 0],
                                ].map(([etiqueta, valor]) => (
                                    <div key={etiqueta} className="rounded-xl border theme-border p-3">
                                        <dt className="text-[11px] theme-text-muted m-0">{etiqueta}</dt>
                                        <dd className="text-lg font-black theme-text-main m-0 tabular-nums">{valor}</dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    )}
                </div>

                <footer className="shrink-0 p-4 sm:p-5 border-t theme-border flex flex-col sm:flex-row gap-2">
                    {etapa === 'proceso' ? (
                        <button type="button" onClick={onClose} className={`${THEME_BTN_PRIMARY} rounded-xl px-4 py-3 text-xs font-black uppercase`}>
                            Continuar en segundo plano
                        </button>
                    ) : (
                        <button type="button" onClick={onClose} className="theme-element border theme-border rounded-xl px-4 py-3 text-xs font-bold theme-text-main">Cerrar</button>
                    )}
                    {(etapa === 'resultados' && (error || progreso?.fase === 'error')) && (
                        <button type="button" onClick={onElegirOtro} className="theme-element border theme-border rounded-xl px-4 py-3 text-xs font-bold theme-text-main">Revisar otro archivo</button>
                    )}
                    {etapa === 'resultados' && progreso?.fase !== 'error' && (
                        <button type="button" onClick={() => onResultados(importacionId)} className="theme-element border theme-border rounded-xl px-4 py-3 text-xs font-bold theme-text-main">Consultar resultados</button>
                    )}
                    {etapa === 'revision' && (
                        <button type="button" onClick={confirmar} disabled={!progreso?.puede_confirmar || confirmando} className={`${THEME_BTN_PRIMARY} rounded-xl px-4 py-3 text-xs font-black uppercase sm:ml-auto disabled:opacity-50`}>
                            {progreso?.bloqueado ? 'Escritura detenida' : confirmando ? 'Confirmando…' : 'Confirmar importación'}
                        </button>
                    )}
                </footer>
            </div>
        </div>,
        document.body,
    );
}
