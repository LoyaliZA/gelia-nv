import React from 'react';
import { ChevronDown } from 'lucide-react';

const ACCIONES = [
    { id: 'publicacion_detectada', label: 'Publicación detectada' },
    { id: 'consulta_ok', label: 'Consulta del vigilante' },
    { id: 'consulta_fallida', label: 'Consulta fallida' },
    { id: 'recarga_iniciada', label: 'Recarga iniciada' },
    { id: 'recarga_en_espera', label: 'Recarga en espera' },
];

const SUPERFICIES = [
    { id: 'sala', label: 'Vista de TV' },
    { id: 'turnos', label: 'Turnos' },
];

function etiquetaAccion(accion) {
    return ACCIONES.find((item) => item.id === accion)?.label || accion || '—';
}

function etiquetaSuperficie(superficie) {
    if (!superficie) return 'Todas';
    return SUPERFICIES.find((item) => item.id === superficie)?.label || superficie;
}

function resumenDetalle(detalles) {
    if (!detalles || typeof detalles !== 'object') return '—';
    if (detalles.detalle) return String(detalles.detalle);
    if (detalles.consultas != null) {
        return `${detalles.consultas} consulta(s) por la pantalla abierta`;
    }
    if (detalles.actual?.surfaces) {
        const sala = detalles.actual.surfaces.sala || '—';
        const turnos = detalles.actual.surfaces.turnos || '—';
        return `TV ${sala} · Turnos ${turnos}`;
    }
    if (detalles.version) return `Versión ${detalles.version}`;
    return detalles.canal === 'vigilante_pantalla' ? 'Pantalla abierta' : '—';
}

function formatearFecha(valor) {
    if (!valor) return '—';
    return new Date(valor).toLocaleString('es-MX', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function TabAuditoriaDespliegues({
    auditorias = {},
    filtros = {},
    onFiltrar,
    paginador: Paginador,
}) {
    const filas = auditorias?.data || [];

    return (
        <>
            <p className="text-sm theme-text-muted m-0">
                La pantalla abierta consulta la versión cada 8 minutos. Ese vigilante no es un worker de cola.
                Aquí queda cada publicación y si la consulta respondió.
            </p>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <label className="block">
                    <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-1 block ml-1">Acción</span>
                    <span className="relative block">
                        <select
                            value={filtros.accion || ''}
                            onChange={(event) => onFiltrar({ accion: event.target.value })}
                            className="w-full pl-5 pr-10 py-4 theme-element border theme-border rounded-xl theme-text-main text-xs font-bold uppercase tracking-widest outline-none"
                        >
                            <option value="">Todas</option>
                            {ACCIONES.map((accion) => (
                                <option key={accion.id} value={accion.id}>{accion.label}</option>
                            ))}
                        </select>
                        <ChevronDown className="w-4 h-4 theme-text-muted absolute bottom-4 right-4 pointer-events-none" aria-hidden />
                    </span>
                </label>
                <label className="block">
                    <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-1 block ml-1">Superficie</span>
                    <span className="relative block">
                        <select
                            value={filtros.superficie || ''}
                            onChange={(event) => onFiltrar({ superficie: event.target.value })}
                            className="w-full pl-5 pr-10 py-4 theme-element border theme-border rounded-xl theme-text-main text-xs font-bold uppercase tracking-widest outline-none"
                        >
                            <option value="">Todas</option>
                            {SUPERFICIES.map((superficie) => (
                                <option key={superficie.id} value={superficie.id}>{superficie.label}</option>
                            ))}
                        </select>
                        <ChevronDown className="w-4 h-4 theme-text-muted absolute bottom-4 right-4 pointer-events-none" aria-hidden />
                    </span>
                </label>
                <label className="block">
                    <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-1 block ml-1">Desde</span>
                    <input
                        type="date"
                        value={filtros.fecha_inicio || ''}
                        onChange={(event) => onFiltrar({ fecha_inicio: event.target.value })}
                        className="w-full px-5 py-4 theme-element border theme-border rounded-xl theme-text-main text-xs font-bold outline-none"
                    />
                </label>
                <label className="block">
                    <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-1 block ml-1">Hasta</span>
                    <input
                        type="date"
                        value={filtros.fecha_fin || ''}
                        onChange={(event) => onFiltrar({ fecha_fin: event.target.value })}
                        className="w-full px-5 py-4 theme-element border theme-border rounded-xl theme-text-main text-xs font-bold outline-none"
                    />
                </label>
            </div>

            <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse">
                    <thead>
                        <tr className="text-[10px] font-black uppercase tracking-widest theme-text-muted">
                            <th className="py-3 pr-4">Cuándo</th>
                            <th className="py-3 pr-4">Acción</th>
                            <th className="py-3 pr-4">Superficie</th>
                            <th className="py-3 pr-4">Origen</th>
                            <th className="py-3">Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filas.length === 0 && (
                            <tr>
                                <td colSpan={5} className="py-8 theme-text-muted text-sm">
                                    Todavía no hay registros de despliegue.
                                </td>
                            </tr>
                        )}
                        {filas.map((fila) => (
                            <tr key={fila.id} className="border-t theme-border text-sm theme-text-main">
                                <td className="py-3 pr-4 whitespace-nowrap">{formatearFecha(fila.created_at)}</td>
                                <td className="py-3 pr-4">{etiquetaAccion(fila.accion)}</td>
                                <td className="py-3 pr-4">{etiquetaSuperficie(fila.superficie)}</td>
                                <td className="py-3 pr-4">{fila.origen === 'cliente' ? 'Pantalla' : 'Servidor'}</td>
                                <td className="py-3">{resumenDetalle(fila.detalles)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {Paginador ? <Paginador data={auditorias} /> : null}
        </>
    );
}
