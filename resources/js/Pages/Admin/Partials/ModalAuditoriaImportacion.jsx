import React, { useState, useEffect, useCallback } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { X, History, AlertTriangle, ChevronRight, TrendingUp } from 'lucide-react';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import GeliaLoader from '../../../Components/GeliaLoader';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';

const formatMoneda = (valor) => new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
}).format(valor ?? 0);

const nombreUsuario = (usuario) => {
    if (!usuario) return 'Sistema';
    return [usuario.name, usuario.apellido_paterno].filter(Boolean).join(' ');
};

const TIPO_CAMBIO_LABELS = {
    ascenso: 'Ascenso',
    descenso: 'Descenso',
    cambio: 'Cambio',
};

const TipoCambioBadge = ({ tipo }) => {
    const label = TIPO_CAMBIO_LABELS[tipo] || tipo || '—';
    const colors = {
        ascenso: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/30',
        descenso: 'bg-amber-500/10 text-amber-600 border-amber-500/30',
        cambio: 'bg-slate-500/10 text-slate-600 border-slate-500/30',
    };
    return (
        <span className={`px-2 py-1 text-[9px] font-black uppercase tracking-widest rounded-md border ${colors[tipo] || 'theme-element theme-border theme-text-muted'}`}>
            {label}
        </span>
    );
};

export default function ModalAuditoriaImportacion({ importacionId, onClose }) {
    const [cargando, setCargando] = useState(true);
    const [importacion, setImportacion] = useState(null);
    const [cambiosMontos, setCambiosMontos] = useState(null);
    const [cambiosLista, setCambiosLista] = useState(null);
    const [errores, setErrores] = useState(null);
    const [erroresDetalleDisponible, setErroresDetalleDisponible] = useState(true);
    const [cambiosListaDisponible, setCambiosListaDisponible] = useState(true);

    const cargarDatos = useCallback(async (params = {}) => {
        setCargando(true);
        try {
            const { data } = await axios.get(
                route('admin.clientes.importaciones.auditoria', importacionId),
                { params },
            );
            setImportacion(data.importacion);
            setCambiosMontos(data.cambiosMontos);
            setCambiosLista(data.cambiosLista);
            setErrores(data.errores);
            setErroresDetalleDisponible(data.erroresDetalleDisponible ?? true);
            setCambiosListaDisponible(data.cambiosListaDisponible ?? true);
        } finally {
            setCargando(false);
        }
    }, [importacionId]);

    useEffect(() => {
        cargarDatos();
    }, [cargarDatos]);

    useEffect(() => {
        const prevOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = prevOverflow;
        };
    }, []);

    const irPaginaCambios = (pagina) => {
        cargarDatos({
            cambios_page: pagina,
            listas_page: cambiosLista?.current_page || 1,
            errores_page: errores?.current_page || 1,
        });
    };

    const irPaginaListas = (pagina) => {
        cargarDatos({
            cambios_page: cambiosMontos?.current_page || 1,
            listas_page: pagina,
            errores_page: errores?.current_page || 1,
        });
    };

    const irPaginaErrores = (pagina) => {
        cargarDatos({
            cambios_page: cambiosMontos?.current_page || 1,
            listas_page: cambiosLista?.current_page || 1,
            errores_page: pagina,
        });
    };

    const modal = (
        <div
            className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`}
            onClick={onClose}
        >
            <div
                className={`${THEME_MODAL_SHELL} max-w-5xl modal-pop p-6 md:p-8 flex flex-col max-h-[min(90vh,calc(100dvh-2rem))]`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex justify-between items-start mb-6 shrink-0">
                    <div>
                        <h3 className="text-xl font-black italic uppercase theme-text-main m-0">
                            AUDITORÍA DE <span style={{ color: 'var(--color-primario)' }}>CARGA</span>
                        </h3>
                        {importacion && (
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mt-1">
                                {importacion.nombre_archivo_original} · Import #{importacion.id}
                            </p>
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-2 rounded-full theme-element theme-text-muted hover:theme-text-main transition-colors outline-none"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                {cargando && !importacion ? (
                    <div className="flex justify-center py-12">
                        <GeliaLoader />
                    </div>
                ) : (
                    <div className={`overflow-y-auto custom-scrollbar pr-2 flex-1 space-y-6 ${cargando ? 'opacity-60 pointer-events-none' : ''}`}>
                        {importacion && (
                            <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
                                {[
                                    { label: 'Procesadas', value: importacion.filas_procesadas },
                                    { label: 'Errores', value: importacion.errores, danger: true },
                                    { label: 'Omitidas', value: importacion.filas_omitidas },
                                    { label: 'Ascensos', value: importacion.ascensos, success: true },
                                    { label: 'Descensos', value: importacion.descensos ?? 0, warning: true },
                                ].map((stat) => (
                                    <div key={stat.label} className="theme-element border theme-border rounded-xl p-3 text-center">
                                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">{stat.label}</p>
                                        <p className={`text-lg font-black m-0 mt-1 ${
                                            stat.danger ? 'text-red-500' : stat.success ? 'text-emerald-600' : stat.warning ? 'text-amber-600' : 'theme-text-main'
                                        }`}>
                                            {stat.value ?? 0}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        )}

                        {importacion && (
                            <p className="text-[10px] font-bold theme-text-muted m-0">
                                {new Date(importacion.created_at).toLocaleString('es-MX')}
                                {' · '}
                                {nombreUsuario(importacion.usuario)}
                                {importacion.clientes_marcados_inactivos > 0 && (
                                    <> · {importacion.clientes_marcados_inactivos} inactivos</>
                                )}
                            </p>
                        )}

                        <section>
                            <div className="flex items-center gap-2 mb-3">
                                <TrendingUp className="w-4 h-4 text-emerald-500" />
                                <h4 className="text-sm font-black uppercase theme-text-main m-0">
                                    Cambios de lista de descuento
                                </h4>
                            </div>

                            {!cambiosListaDisponible && ((importacion?.ascensos ?? 0) > 0 || (importacion?.descensos ?? 0) > 0) ? (
                                <p className="text-[10px] font-bold theme-text-muted italic m-0 py-4">
                                    Detalle no disponible para importaciones anteriores (ascensos: {importacion.ascensos ?? 0}, descensos: {importacion.descensos ?? 0})
                                </p>
                            ) : (
                                <>
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-left text-sm min-w-[700px]">
                                            <thead>
                                                <tr className="text-[9px] font-black uppercase tracking-widest theme-text-muted border-b theme-border">
                                                    <th className="pb-2 pr-3">Cliente</th>
                                                    <th className="pb-2 pr-3">Código lista</th>
                                                    <th className="pb-2 pr-3">Lista anterior</th>
                                                    <th className="pb-2 pr-3">Lista nueva</th>
                                                    <th className="pb-2 pr-3 text-right">Monto</th>
                                                    <th className="pb-2">Tipo</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {(cambiosLista?.data?.length ?? 0) === 0 ? (
                                                    <tr>
                                                        <td colSpan={6} className="py-6 text-center text-[10px] font-bold uppercase theme-text-muted">
                                                            Sin cambios de lista en esta carga
                                                        </td>
                                                    </tr>
                                                ) : (
                                                    cambiosLista.data.map((row) => (
                                                        <tr key={row.id} className="border-b theme-border/50">
                                                            <td className="py-2 pr-3">
                                                                <p className="text-xs font-black theme-text-main uppercase m-0">{row.nombre_cliente}</p>
                                                                <p className="text-[9px] font-bold theme-text-muted m-0">{row.numero_cliente}</p>
                                                            </td>
                                                            <td className="py-2 pr-3 text-[10px] font-bold theme-text-muted">
                                                                {row.codigo_lista ?? '—'}
                                                            </td>
                                                            <td className="py-2 pr-3">
                                                                <span className="text-[9px] font-black uppercase tracking-widest bg-slate-500/10 text-slate-600 px-2 py-1 rounded-md border border-slate-500/20">
                                                                    {row.lista_anterior}
                                                                </span>
                                                            </td>
                                                            <td className="py-2 pr-3">
                                                                <div className="flex items-center gap-1.5">
                                                                    <ChevronRight className="w-3.5 h-3.5 theme-text-muted shrink-0" />
                                                                    <span className={`text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-md border ${
                                                                        row.tipo_cambio === 'descenso'
                                                                            ? 'bg-amber-500/10 text-amber-600 border-amber-500/30'
                                                                            : 'bg-emerald-500/10 text-emerald-600 border-emerald-500/30'
                                                                    }`}>
                                                                        {row.lista_nueva}
                                                                    </span>
                                                                </div>
                                                            </td>
                                                            <td className="py-2 pr-3 text-right text-xs font-bold theme-text-main">
                                                                {row.monto_nuevo != null ? formatMoneda(row.monto_nuevo) : '—'}
                                                            </td>
                                                            <td className="py-2">
                                                                <TipoCambioBadge tipo={row.tipo_cambio} />
                                                            </td>
                                                        </tr>
                                                    ))
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                    {cambiosLista && cambiosLista.last_page > 1 && (
                                        <div className="pt-3">
                                            <GeliaPaginacion paginator={cambiosLista} onIrAPagina={irPaginaListas} embedded />
                                        </div>
                                    )}
                                </>
                            )}
                        </section>

                        <section>
                            <div className="flex items-center gap-2 mb-3">
                                <History className="w-4 h-4 text-purple-500" />
                                <h4 className="text-sm font-black uppercase theme-text-main m-0">
                                    Cambios de montos
                                </h4>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm min-w-[600px]">
                                    <thead>
                                        <tr className="text-[9px] font-black uppercase tracking-widest theme-text-muted border-b theme-border">
                                            <th className="pb-2 pr-3">Fecha</th>
                                            <th className="pb-2 pr-3">Cliente</th>
                                            <th className="pb-2 pr-3 text-right">Anterior</th>
                                            <th className="pb-2 pr-3 text-right">Nuevo</th>
                                            <th className="pb-2 pr-3 text-right">Diferencia</th>
                                            <th className="pb-2">Usuario</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {(cambiosMontos?.data?.length ?? 0) === 0 ? (
                                            <tr>
                                                <td colSpan={6} className="py-6 text-center text-[10px] font-bold uppercase theme-text-muted">
                                                    Sin cambios de monto en esta carga
                                                </td>
                                            </tr>
                                        ) : (
                                            cambiosMontos.data.map((row) => (
                                                <tr key={row.id} className="border-b theme-border/50">
                                                    <td className="py-2 pr-3 text-[10px] font-bold theme-text-muted whitespace-nowrap">
                                                        {new Date(row.created_at).toLocaleString('es-MX')}
                                                    </td>
                                                    <td className="py-2 pr-3">
                                                        <p className="text-xs font-black theme-text-main uppercase m-0">{row.cliente?.nombre}</p>
                                                        <p className="text-[9px] font-bold theme-text-muted m-0">{row.cliente?.numero_cliente}</p>
                                                    </td>
                                                    <td className="py-2 pr-3 text-right text-xs font-bold theme-text-muted">
                                                        {formatMoneda(row.monto_anterior)}
                                                    </td>
                                                    <td className="py-2 pr-3 text-right text-xs font-black text-emerald-600">
                                                        {formatMoneda(row.monto_nuevo)}
                                                    </td>
                                                    <td className="py-2 pr-3 text-right text-xs font-bold theme-text-main">
                                                        {formatMoneda(row.diferencia_aplicada)}
                                                    </td>
                                                    <td className="py-2 text-[10px] font-bold theme-text-main">
                                                        {nombreUsuario(row.usuario)}
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            {cambiosMontos && cambiosMontos.last_page > 1 && (
                                <div className="pt-3">
                                    <GeliaPaginacion paginator={cambiosMontos} onIrAPagina={irPaginaCambios} embedded />
                                </div>
                            )}
                        </section>

                        <section>
                            <div className="flex items-center gap-2 mb-3">
                                <AlertTriangle className="w-4 h-4 text-red-500" />
                                <h4 className="text-sm font-black uppercase theme-text-main m-0">
                                    Errores
                                </h4>
                            </div>

                            {!erroresDetalleDisponible && (importacion?.errores ?? 0) > 0 ? (
                                <p className="text-[10px] font-bold theme-text-muted italic m-0 py-4">
                                    Detalle no disponible para importaciones anteriores (solo contador: {importacion.errores})
                                </p>
                            ) : (
                                <>
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-left text-sm min-w-[500px]">
                                            <thead>
                                                <tr className="text-[9px] font-black uppercase tracking-widest theme-text-muted border-b theme-border">
                                                    <th className="pb-2 pr-3">Fila</th>
                                                    <th className="pb-2 pr-3">Cliente</th>
                                                    <th className="pb-2">Mensaje</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {(errores?.data?.length ?? 0) === 0 ? (
                                                    <tr>
                                                        <td colSpan={3} className="py-6 text-center text-[10px] font-bold uppercase theme-text-muted">
                                                            Sin errores en esta carga
                                                        </td>
                                                    </tr>
                                                ) : (
                                                    errores.data.map((err) => (
                                                        <tr key={err.id} className="border-b theme-border/50">
                                                            <td className="py-2 pr-3 text-xs font-bold theme-text-muted">
                                                                {err.numero_fila ?? '—'}
                                                            </td>
                                                            <td className="py-2 pr-3 text-xs font-bold theme-text-main">
                                                                {err.numero_cliente ?? '—'}
                                                            </td>
                                                            <td className="py-2 text-[10px] font-bold text-red-600">
                                                                {err.mensaje}
                                                            </td>
                                                        </tr>
                                                    ))
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                    {errores && errores.last_page > 1 && (
                                        <div className="pt-3">
                                            <GeliaPaginacion paginator={errores} onIrAPagina={irPaginaErrores} embedded />
                                        </div>
                                    )}
                                </>
                            )}
                        </section>
                    </div>
                )}

                <div className="pt-6 mt-4 border-t theme-border flex justify-end shrink-0">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-8 py-3 text-white rounded-xl font-black uppercase tracking-widest text-[11px] shadow-lg transition-all hover:scale-105"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    );

    if (typeof document === 'undefined') {
        return null;
    }

    return createPortal(modal, document.body);
}
