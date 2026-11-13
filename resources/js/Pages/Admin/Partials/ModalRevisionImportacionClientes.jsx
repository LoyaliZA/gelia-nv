import React, { useCallback, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { AlertTriangle, X } from 'lucide-react';
import { fechaLocal } from '../../../utils/fechaCalendario';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import GeliaLoader from '../../../Components/GeliaLoader';
import { THEME_BTN_PRIMARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';
import { FASES_CARGA, motivoCargaCliente } from '../../../utils/backgroundTaskAdapters';

const TEXTOS = {
    inactivacion: 'Hay inactivaciones previstas. Un código vacío con monto cero deja inactivo al cliente y conserva la lista almacenada.',
    codigo_vacio: 'Un código de lista vacío cambiaría el estado, la lista o la condición de menudeo.',
    lista_bloqueada: 'El archivo reemplazaría una lista protegida.',
    archivo_repetido: 'Este archivo ya fue importado.',
    archivo_anterior: 'Hay montos más recientes que la fecha del archivo.',
    archivo_invalido: 'El archivo completo no se puede confirmar. Hay que corregirlo y revisarlo de nuevo.',
    descenso_lista: 'Hay descensos de lista. Quedan a la vista en esta revisión.',
    diferencia_monto: 'Si confirmas el archivo y la fila es válida, el monto se actualiza con el valor importado. Una diferencia de 1000 o más queda visible para revisión.',
    provisional_vigente: 'Hay provisionales vigentes. El oficial nuevo puede incluir ya esos importes. Esta actualización no los cierra.',
    lista_mayorista_sin_conversion: 'Un código mayorista con monto explícito pasa el cliente a Mayoreo, salvo un conflicto real.',
    conversion_autorizada: 'Una importación con Público General no vuelve a marcar Menudeo si la conversión ya fue autorizada.',
};

const CONTEOS = [
    ['filas', 'Registros totales', false],
    ['nuevos', 'Clientes nuevos', false],
    ['actualizaciones', 'Actualizaciones', true],
    ['sin_cambios', 'Sin cambios', false],
    ['errores', 'Errores', true],
    ['cambios_lista', 'Cambios de lista', true],
    ['inactivaciones', 'Inactivaciones', true],
    ['activaciones', 'Reactivaciones', true],
    ['omitidas', 'Omitidos', false],
    ['conflictos', 'Conflictos', true],
    ['cambios_monto', 'Diferencias monetarias', true],
];

const FILTROS = [
    ['con_cambios', 'Con cambios'],
    ['', 'Todos'],
    ['nuevo', 'Nuevos'],
    ['actualizacion', 'Actualizaciones'],
    ['sin_cambios', 'Sin cambios'],
    ['error', 'Errores'],
    ['inactivacion', 'Inactivación'],
    ['reactivacion', 'Reactivación'],
    ['omision', 'Omisión'],
    ['conflicto', 'Conflicto'],
];

const TIPOS = {
    nuevo: 'Nuevo',
    actualizacion: 'Actualización',
    sin_cambios: 'Sin cambios',
    error: 'Error',
    omitida: 'Omitido',
    conflicto: 'Conflicto pendiente',
};

const formatoMoneda = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

const dinero = (valor) => formatoMoneda.format(Number(valor) || 0);

function etapaDe(fase) {
    if (fase === 'esperando_confirmacion') return 'revision';
    if (['completado', 'completado_con_incidencias', 'error'].includes(fase)) return 'resultados';
    return 'proceso';
}

function partesCorte(valor) {
    const normal = String(valor || '').replace('T', ' ').trim();
    const [fecha = '', hora = ''] = normal.split(' ');
    let fechaLegible = fecha;
    if (/^\d{4}-\d{2}-\d{2}$/.test(fecha)) {
        fechaLegible = fechaLocal(fecha).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' });
    }
    return { fecha: fechaLegible, hora: hora.slice(0, 5) };
}

function senalesDeFila(motivo) {
    const texto = motivo || '';
    const senales = [];
    if (/pasaría a inactivo/i.test(texto)) senales.push({ id: 'inactivo', texto: 'Cambio a Inactivo', tono: 'theme-element border theme-border theme-text-main' });
    if (/pasaría a activo/i.test(texto)) senales.push({ id: 'activo', texto: 'Cambio a Activo', tono: 'bg-emerald-500/15 text-emerald-600 border border-emerald-500/40' });
    if (/diferencia de monto supera 1000/i.test(texto)) senales.push({ id: 'mil', texto: 'Cambio superior a mil detectado', tono: 'bg-amber-500/15 text-amber-600 border border-amber-500/40' });
    const resto = texto
        .replace(/El cliente pasaría a inactivo\./gi, '')
        .replace(/El cliente pasaría a activo\./gi, '')
        .replace(/La diferencia de monto supera 1000\. Si la fila es válida, el monto importado se aplica al confirmar\./gi, '')
        .replace(/\s+/g, ' ')
        .trim();
    return { senales, resto };
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
                modalidad_carga: progreso.modalidad_carga || 'actualizacion_normal',
                fecha_corte: progreso.fecha_corte || null,
            });
            setProgreso(data);
            setFilas(null);
        } catch (e) {
            setError(e.response?.data?.mensaje
                || e.response?.data?.errors?.modalidad_carga?.[0]
                || e.response?.data?.errors?.fecha_corte?.[0]
                || e.response?.data?.errors?.archivo?.[0]
                || 'No se pudo confirmar la revisión.');
        } finally {
            setConfirmando(false);
        }
    };

    const conteos = progreso?.conteos || {};
    const etapa = etapaDe(progreso?.fase);
    const modalidadGuardada = progreso?.modalidad_carga || 'actualizacion_normal';
    const corte = partesCorte(progreso?.fecha_corte);
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
                    {(error || progreso?.mensaje) && <p className="text-sm font-bold theme-text-peligro m-0 break-words">{motivoCargaCliente(error || progreso.mensaje)}</p>}

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
                            <fieldset className="rounded-xl border theme-border p-3 space-y-3">
                                <legend className="text-[11px] font-bold theme-text-muted px-1">Modalidad de esta revisión</legend>
                                <p className="text-xs theme-text-muted m-0">Quedó definida antes del análisis. Confirmar usa esta misma opción.</p>
                                <div className="flex flex-wrap gap-2">
                                    <span className={`px-3 py-2 rounded-xl text-xs font-bold border ${modalidadGuardada === 'actualizacion_normal' ? 'text-white border-transparent' : 'theme-element theme-border theme-text-muted'}`} style={modalidadGuardada === 'actualizacion_normal' ? { backgroundColor: 'var(--color-primario)' } : {}}>
                                        Actualización normal
                                    </span>
                                    <span className={`px-3 py-2 rounded-xl text-xs font-bold border ${modalidadGuardada === 'actualizacion_con_corte' ? 'text-white border-transparent' : 'theme-element theme-border theme-text-muted'}`} style={modalidadGuardada === 'actualizacion_con_corte' ? { backgroundColor: 'var(--color-primario)' } : {}}>
                                        Actualización con corte
                                    </span>
                                </div>
                                {modalidadGuardada === 'actualizacion_con_corte' && (
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <div className="theme-element border theme-border rounded-xl px-3 py-2">
                                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Fecha</p>
                                            <p className="text-sm font-bold theme-text-main m-0 capitalize">{corte.fecha || 'Sin fecha'}</p>
                                        </div>
                                        <div className="theme-element border theme-border rounded-xl px-3 py-2">
                                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Hora</p>
                                            <p className="text-sm font-bold theme-text-main m-0 tabular-nums">{corte.hora || 'Sin hora'}</p>
                                        </div>
                                    </div>
                                )}
                            </fieldset>
                            {(progreso.avisos || []).map((aviso) => (
                                aviso.codigo === 'diferencia_monto' ? (
                                    <div key={aviso.codigo} className="relative overflow-hidden rounded-2xl border border-amber-500/40 bg-amber-500/10 p-4">
                                        <div className="flex items-center gap-4 min-w-0">
                                            <AlertTriangle className="gelia-alerta-pulso w-12 h-12 shrink-0 text-amber-500" aria-hidden="true" />
                                            <div className="min-w-0">
                                                <p className="text-sm font-black theme-text-main m-0">Diferencia superior a mil</p>
                                                <p className="text-xs theme-text-main m-0 mt-1">{aviso.filas} filas. Si la fila es válida, el monto importado se aplica al confirmar.</p>
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    <p key={aviso.codigo} className={`text-sm m-0 ${tonoAviso(aviso.codigo, false)}`}>
                                        {TEXTOS[aviso.codigo] || aviso.codigo} ({aviso.filas})
                                    </p>
                                )
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
                                    const { senales, resto } = senalesDeFila(fila.motivo);
                                    return (
                                        <article key={fila.id} className="theme-element border theme-border rounded-xl p-3 min-w-0">
                                            <p className="text-sm font-bold theme-text-main m-0 break-words">
                                                {fila.numero_cliente || 'Sin número'} {fila.nombre ? `· ${fila.nombre}` : ''}
                                            </p>
                                            <p className="text-xs theme-text-muted m-0 mt-1">{TIPOS[fila.categoria] || fila.categoria}</p>
                                            {cambios.map((cambio) => (
                                                cambio.campo === 'Inactividad' || cambio.campo === 'Menudeo' ? (
                                                    <p key={`${fila.id}-${cambio.campo}`} className="flex flex-wrap items-center gap-2 m-0 mt-2">
                                                        <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">{cambio.campo}</span>
                                                        <span className="px-2 py-1 rounded-md text-[10px] font-black uppercase theme-element border theme-border theme-text-main">{cambio.anterior}</span>
                                                        <span className="text-xs theme-text-muted" aria-hidden="true">→</span>
                                                        <span className={`px-2 py-1 rounded-md text-[10px] font-black uppercase border ${cambio.nuevo === 'Inactivo' ? 'bg-amber-500/15 text-amber-600 border-amber-500/40' : 'bg-emerald-500/15 text-emerald-600 border-emerald-500/40'}`}>{cambio.nuevo}</span>
                                                    </p>
                                                ) : (
                                                    <p key={`${fila.id}-${cambio.campo}`} className="text-xs theme-text-main m-0 mt-1 break-words">
                                                        {cambio.campo}: {cambio.campo === 'Monto' ? `${dinero(cambio.anterior)} → ${dinero(cambio.nuevo)}` : `${cambio.anterior} → ${cambio.nuevo}`}
                                                    </p>
                                                )
                                            ))}
                                            {cambios.length === 0 && (fila.monto_anterior != null || fila.monto_nuevo != null) && (
                                                <p className="text-xs theme-text-main m-0 mt-1">
                                                    Monto: {dinero(fila.monto_anterior)} → {dinero(fila.monto_nuevo)}
                                                </p>
                                            )}
                                            {senales.length > 0 && (
                                                <div className="flex flex-wrap gap-2 mt-2">
                                                    {senales.map((senal) => (
                                                        <span key={senal.id} className={`inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-black uppercase tracking-wide ${senal.tono}`}>
                                                            {senal.id === 'mil' && <AlertTriangle className="w-3.5 h-3.5" aria-hidden="true" />}
                                                            {senal.texto}
                                                        </span>
                                                    ))}
                                                </div>
                                            )}
                                            {fila.categoria === 'error' && fila.motivo && <p className="text-xs m-0 mt-1 break-words theme-text-peligro">Error de fila. {fila.motivo}</p>}
                                            {fila.categoria !== 'error' && resto && <p className="text-xs m-0 mt-1 break-words theme-text-muted">{resto}</p>}
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
                                <div className="h-full bg-[var(--color-primario)] transition-all" style={{ width: `${porcentaje == null ? 0 : porcentaje}%` }} />
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
