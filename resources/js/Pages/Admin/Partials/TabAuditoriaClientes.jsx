import React, { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import {
    History, Download, FileSpreadsheet, Search, ChevronDown, Filter, Eye,
} from 'lucide-react';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import { geliaCardClass } from '../../../utils/geliaTheme';
import ModalAuditoriaImportacion from './ModalAuditoriaImportacion';

const formatMoneda = (valor) => new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
}).format(valor ?? 0);

const ORIGEN_LABELS = {
    carga_masiva: 'Carga masiva',
    solicitud_aprobacion: 'Solicitud aprobada',
    solicitud_pago: 'Pago confirmado',
    solicitud_reversion: 'Reversión',
    cron_rechazo_pago: 'Cron vencimiento',
};

const FILTRO_ORIGEN_LABELS = Object.fromEntries(
    Object.entries(ORIGEN_LABELS).filter(([key]) => key !== 'carga_masiva'),
);

const OrigenBadge = ({ origen }) => {
    const label = ORIGEN_LABELS[origen] || origen || '—';
    const colors = {
        carga_masiva: 'bg-blue-500/10 text-blue-600 border-blue-500/30',
        solicitud_aprobacion: 'bg-purple-500/10 text-purple-600 border-purple-500/30',
        solicitud_pago: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/30',
        solicitud_reversion: 'bg-amber-500/10 text-amber-600 border-amber-500/30',
        cron_rechazo_pago: 'bg-slate-500/10 text-slate-600 border-slate-500/30',
    };
    return (
        <span className={`px-2 py-1 text-[9px] font-black uppercase tracking-widest rounded-md border ${colors[origen] || 'theme-element theme-border theme-text-muted'}`}>
            {label}
        </span>
    );
};

export default function TabAuditoriaClientes({ puedeDescargarImportaciones = false }) {
    const activeCardClass = geliaCardClass('relative z-10');

    const [importaciones, setImportaciones] = useState(null);
    const [auditoriaMontos, setAuditoriaMontos] = useState(null);
    const [usuariosFiltro, setUsuariosFiltro] = useState([]);
    const [cargando, setCargando] = useState(false);
    const [importacionAuditoriaId, setImportacionAuditoriaId] = useState(null);

    const [qInput, setQInput] = useState('');
    const [origen, setOrigen] = useState('');
    const [usuarioId, setUsuarioId] = useState('');
    const [fechaInicio, setFechaInicio] = useState('');
    const [fechaFin, setFechaFin] = useState('');

    const cargarDatos = useCallback(async (params = {}) => {
        setCargando(true);
        try {
            const { data } = await axios.get(route('admin.clientes.auditoria.datos'), { params });
            setImportaciones(data.importaciones);
            setAuditoriaMontos(data.auditoriaMontos);
            setUsuariosFiltro(data.usuariosFiltro || []);
            if (data.filtrosAuditoria?.q_auditoria !== undefined) {
                setQInput(data.filtrosAuditoria.q_auditoria || '');
            }
        } finally {
            setCargando(false);
        }
    }, []);

    useEffect(() => {
        cargarDatos();
    }, [cargarDatos]);

    const aplicarFiltros = () => {
        cargarDatos({
            q_auditoria: qInput.trim() || undefined,
            origen: origen || undefined,
            usuario_id: usuarioId || undefined,
            fecha_inicio: fechaInicio || undefined,
            fecha_fin: fechaFin || undefined,
            auditoria_page: 1,
        });
    };

    const irPaginaImportaciones = (pagina) => {
        cargarDatos({
            q_auditoria: qInput.trim() || undefined,
            origen: origen || undefined,
            usuario_id: usuarioId || undefined,
            fecha_inicio: fechaInicio || undefined,
            fecha_fin: fechaFin || undefined,
            importaciones_page: pagina,
            auditoria_page: auditoriaMontos?.current_page || 1,
        });
    };

    const irPaginaAuditoria = (pagina) => {
        cargarDatos({
            q_auditoria: qInput.trim() || undefined,
            origen: origen || undefined,
            usuario_id: usuarioId || undefined,
            fecha_inicio: fechaInicio || undefined,
            fecha_fin: fechaFin || undefined,
            auditoria_page: pagina,
            importaciones_page: importaciones?.current_page || 1,
        });
    };

    const nombreUsuario = (usuario) => {
        if (!usuario) return 'Sistema';
        return [usuario.name, usuario.apellido_paterno].filter(Boolean).join(' ');
    };

    return (
        <div className="space-y-8">
            {importacionAuditoriaId !== null && (
                <ModalAuditoriaImportacion
                    importacionId={importacionAuditoriaId}
                    onClose={() => setImportacionAuditoriaId(null)}
                />
            )}

            {/* Historial de cargas masivas */}
            <section className={`${activeCardClass} p-8`}>
                <div className="flex items-center gap-3 mb-6">
                    <FileSpreadsheet className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                    <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0">
                        Historial de cargas masivas_
                    </h2>
                </div>

                <div className={`overflow-x-auto ${cargando ? 'opacity-60 pointer-events-none' : ''}`}>
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="text-[9px] font-black uppercase tracking-widest theme-text-muted border-b theme-border">
                                <th className="pb-3 pr-4">Fecha</th>
                                <th className="pb-3 pr-4">Usuario</th>
                                <th className="pb-3 pr-4">Archivo</th>
                                <th className="pb-3 pr-4 text-center">Procesadas</th>
                                <th className="pb-3 pr-4 text-center">Errores</th>
                                <th className="pb-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(importaciones?.data?.length ?? 0) === 0 ? (
                                <tr>
                                    <td colSpan={6} className="py-8 text-center text-[10px] font-bold uppercase theme-text-muted">
                                        Sin cargas registradas
                                    </td>
                                </tr>
                            ) : (
                                importaciones.data.map((imp) => (
                                    <tr key={imp.id} className="border-b theme-border/50 hover:bg-black/[0.02] dark:hover:bg-white/[0.02]">
                                        <td className="py-3 pr-4 text-xs font-bold theme-text-main whitespace-nowrap">
                                            {new Date(imp.created_at).toLocaleString('es-MX')}
                                        </td>
                                        <td className="py-3 pr-4 text-xs font-bold theme-text-main">
                                            {nombreUsuario(imp.usuario)}
                                        </td>
                                        <td className="py-3 pr-4 text-xs font-bold theme-text-muted truncate max-w-[200px]">
                                            {imp.nombre_archivo_original}
                                        </td>
                                        <td className="py-3 pr-4 text-center text-xs font-black theme-text-main">
                                            {imp.filas_procesadas}
                                        </td>
                                        <td className="py-3 pr-4 text-center text-xs font-black text-red-500">
                                            {imp.errores}
                                        </td>
                                        <td className="py-3 text-right">
                                            <div className="inline-flex items-center gap-2 justify-end">
                                                <button
                                                    type="button"
                                                    onClick={() => setImportacionAuditoriaId(imp.id)}
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-[9px] font-black uppercase tracking-widest theme-element border theme-border rounded-lg hover:shadow-md transition-all"
                                                >
                                                    <Eye className="w-3 h-3" />
                                                    Ver Auditoría
                                                </button>
                                                {puedeDescargarImportaciones ? (
                                                    <a
                                                        href={route('admin.clientes.importaciones.archivo', imp.id)}
                                                        className="inline-flex items-center gap-1.5 px-3 py-1.5 text-[9px] font-black uppercase tracking-widest theme-element border theme-border rounded-lg hover:shadow-md transition-all"
                                                    >
                                                        <Download className="w-3 h-3" />
                                                        CSV
                                                    </a>
                                                ) : null}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {importaciones && importaciones.last_page > 1 && (
                    <div className="pt-4">
                        <GeliaPaginacion paginator={importaciones} onIrAPagina={irPaginaImportaciones} embedded />
                    </div>
                )}
            </section>

            {/* Cambios de montos por solicitudes */}
            <section className={`${activeCardClass} p-8`}>
                <div className="flex items-center gap-3 mb-2">
                    <History className="w-6 h-6 text-purple-500" />
                    <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0">
                        Auditoría de cambios de montos_
                    </h2>
                </div>
                <p className="text-[10px] font-bold uppercase tracking-widest theme-text-muted mb-6 ml-9">
                    Cambios por solicitudes y pagos
                </p>

                <div className="grid grid-cols-1 md:grid-cols-12 gap-3 mb-6">
                    <div className="md:col-span-4 relative">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted pointer-events-none" />
                        <input
                            type="text"
                            placeholder="Buscar cliente..."
                            value={qInput}
                            onChange={(e) => setQInput(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && aplicarFiltros()}
                            className="w-full pl-10 pr-4 py-3 theme-element border theme-border rounded-xl text-xs font-bold theme-text-main outline-none"
                        />
                    </div>
                    <div className="md:col-span-2 relative">
                        <select
                            value={origen}
                            onChange={(e) => setOrigen(e.target.value)}
                            className="w-full pl-4 pr-8 py-3 theme-element border theme-border rounded-xl text-[10px] font-black uppercase appearance-none cursor-pointer"
                        >
                            <option value="">Todos los orígenes</option>
                            {Object.entries(FILTRO_ORIGEN_LABELS).map(([key, label]) => (
                                <option key={key} value={key}>{label}</option>
                            ))}
                        </select>
                        <ChevronDown className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted pointer-events-none" />
                    </div>
                    <div className="md:col-span-2 relative">
                        <select
                            value={usuarioId}
                            onChange={(e) => setUsuarioId(e.target.value)}
                            className="w-full pl-4 pr-8 py-3 theme-element border theme-border rounded-xl text-[10px] font-black uppercase appearance-none cursor-pointer"
                        >
                            <option value="">Todos los usuarios</option>
                            {usuariosFiltro.map((u) => (
                                <option key={u.id} value={String(u.id)}>
                                    {nombreUsuario(u)}
                                </option>
                            ))}
                        </select>
                        <ChevronDown className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted pointer-events-none" />
                    </div>
                    <input
                        type="date"
                        value={fechaInicio}
                        onChange={(e) => setFechaInicio(e.target.value)}
                        className="md:col-span-2 py-3 px-4 theme-element border theme-border rounded-xl text-[10px] font-bold theme-text-main"
                    />
                    <input
                        type="date"
                        value={fechaFin}
                        onChange={(e) => setFechaFin(e.target.value)}
                        className="md:col-span-1 py-3 px-4 theme-element border theme-border rounded-xl text-[10px] font-bold theme-text-main"
                    />
                    <button
                        type="button"
                        onClick={aplicarFiltros}
                        className="md:col-span-1 py-3 px-4 text-white rounded-xl font-black uppercase tracking-widest text-[10px] flex items-center justify-center gap-1.5"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        <Filter className="w-3.5 h-3.5" />
                        Aplicar
                    </button>
                </div>

                <div className={`overflow-x-auto ${cargando ? 'opacity-60 pointer-events-none' : ''}`}>
                    <table className="w-full text-left text-sm min-w-[800px]">
                        <thead>
                            <tr className="text-[9px] font-black uppercase tracking-widest theme-text-muted border-b theme-border">
                                <th className="pb-3 pr-3">Fecha</th>
                                <th className="pb-3 pr-3">Cliente</th>
                                <th className="pb-3 pr-3 text-right">Anterior</th>
                                <th className="pb-3 pr-3 text-right">Nuevo</th>
                                <th className="pb-3 pr-3 text-right">Diferencia</th>
                                <th className="pb-3 pr-3">Origen</th>
                                <th className="pb-3 pr-3">Usuario</th>
                                <th className="pb-3">Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(auditoriaMontos?.data?.length ?? 0) === 0 ? (
                                <tr>
                                    <td colSpan={8} className="py-8 text-center text-[10px] font-bold uppercase theme-text-muted">
                                        Sin registros de auditoría
                                    </td>
                                </tr>
                            ) : (
                                auditoriaMontos.data.map((row) => (
                                    <tr key={row.id} className="border-b theme-border/50 hover:bg-black/[0.02] dark:hover:bg-white/[0.02]">
                                        <td className="py-3 pr-3 text-[10px] font-bold theme-text-muted whitespace-nowrap">
                                            {new Date(row.created_at).toLocaleString('es-MX')}
                                        </td>
                                        <td className="py-3 pr-3">
                                            <p className="text-xs font-black theme-text-main uppercase">{row.cliente?.nombre}</p>
                                            <p className="text-[9px] font-bold theme-text-muted">{row.cliente?.numero_cliente}</p>
                                        </td>
                                        <td className="py-3 pr-3 text-right text-xs font-bold theme-text-muted">
                                            {formatMoneda(row.monto_anterior)}
                                        </td>
                                        <td className="py-3 pr-3 text-right text-xs font-black text-emerald-600">
                                            {formatMoneda(row.monto_nuevo)}
                                        </td>
                                        <td className="py-3 pr-3 text-right text-xs font-bold theme-text-main">
                                            {formatMoneda(row.diferencia_aplicada)}
                                        </td>
                                        <td className="py-3 pr-3">
                                            <OrigenBadge origen={row.origen} />
                                        </td>
                                        <td className="py-3 pr-3 text-[10px] font-bold theme-text-main">
                                            {nombreUsuario(row.usuario)}
                                        </td>
                                        <td className="py-3 text-[9px] font-bold theme-text-muted max-w-[180px]">
                                            {row.solicitud_id && (
                                                <span>
                                                    FOL-{row.solicitud_id}
                                                    {row.monto_operacion != null && ` · ${formatMoneda(row.monto_operacion)}`}
                                                    {row.solicitud?.vendedor && ` · ${row.solicitud.vendedor.name}`}
                                                </span>
                                            )}
                                            {row.notas && <span className="block mt-0.5 italic">{row.notas}</span>}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {auditoriaMontos && auditoriaMontos.last_page > 1 && (
                    <div className="pt-4">
                        <GeliaPaginacion paginator={auditoriaMontos} onIrAPagina={irPaginaAuditoria} embedded />
                    </div>
                )}
            </section>
        </div>
    );
}
