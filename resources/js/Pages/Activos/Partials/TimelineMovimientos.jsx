import React, { useState } from 'react';
import { Link } from '@inertiajs/react';
import { Wrench, ChevronDown, ChevronUp } from 'lucide-react';

const TIPO_LABELS = {
    creacion: 'Registro',
    edicion: 'Edición',
    asignacion: 'Asignación',
    reasignacion: 'Reasignación',
    devolucion: 'Devolución',
    transferencia: 'Transferencia',
    cambio_estado: 'Cambio de estado',
    baja: 'Baja',
};

const MANT_TIPO = { preventivo: 'Preventivo', correctivo: 'Correctivo', garantia: 'Garantía' };

function SnapshotDetalle({ snapshot }) {
    const [abierto, setAbierto] = useState(false);

    if (!snapshot || typeof snapshot !== 'object') return null;

    const atributos = snapshot.atributos || {};
    const atributosLista = Object.entries(atributos).filter(([k]) => !['marca_id', 'modelo_id'].includes(k));

    return (
        <div className="mt-2">
            <button
                type="button"
                onClick={() => setAbierto((v) => !v)}
                className="inline-flex items-center gap-1 text-[10px] font-black uppercase theme-text-muted hover:theme-text-main"
            >
                {abierto ? <ChevronUp className="w-3 h-3" /> : <ChevronDown className="w-3 h-3" />}
                Ver datos al momento
            </button>
            {abierto && (
                <div className="mt-2 p-3 rounded-xl theme-element border theme-border text-xs space-y-2">
                    {snapshot.nombre && (
                        <p className="m-0"><span className="font-black uppercase theme-text-muted">Nombre: </span>{snapshot.nombre}</p>
                    )}
                    {snapshot.valor != null && snapshot.valor !== '' && (
                        <p className="m-0"><span className="font-black uppercase theme-text-muted">Valor: </span>${Number(snapshot.valor).toLocaleString('es-MX')}</p>
                    )}
                    {snapshot.folio && (
                        <p className="m-0"><span className="font-black uppercase theme-text-muted">Folio: </span>{snapshot.folio}</p>
                    )}
                    {atributosLista.length > 0 && (
                        <div>
                            <p className="font-black uppercase theme-text-muted mb-1 m-0">Atributos</p>
                            <ul className="space-y-1 m-0 pl-0 list-none">
                                {atributosLista.map(([key, value]) => (
                                    <li key={key} className="flex gap-2">
                                        <span className="theme-text-muted shrink-0">{key}:</span>
                                        <span className="theme-text-main break-all">{String(value)}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

export default function TimelineMovimientos({ movimientos = [], mantenimientos = [] }) {
    const eventos = [
        ...movimientos.map((m) => ({ ...m, kind: 'movimiento', sortDate: m.created_at })),
        ...mantenimientos.map((m) => ({ ...m, kind: 'mantenimiento', sortDate: m.created_at })),
    ].sort((a, b) => new Date(b.sortDate) - new Date(a.sortDate));

    if (!eventos.length) {
        return <p className="text-sm theme-text-muted italic">Sin movimientos registrados.</p>;
    }

    return (
        <div className="space-y-4">
            {eventos.map((ev) => (
                <div key={`${ev.kind}-${ev.id}`} className="flex gap-4">
                    <div className="w-2 rounded-full shrink-0" style={{ backgroundColor: ev.kind === 'mantenimiento' ? '#f59e0b' : 'var(--color-primario)' }} />
                    <div className="flex-1 pb-4 border-b theme-border last:border-0">
                        <div className="flex flex-wrap items-center gap-2 mb-1">
                            {ev.kind === 'mantenimiento' && <Wrench className="w-3 h-3 text-amber-500" />}
                            <span className="text-[10px] font-black uppercase tracking-widest" style={{ color: ev.kind === 'mantenimiento' ? '#f59e0b' : 'var(--color-primario)' }}>
                                {ev.kind === 'mantenimiento' ? `Mantenimiento ${MANT_TIPO[ev.tipo] || ev.tipo}` : (TIPO_LABELS[ev.tipo] || ev.tipo)}
                            </span>
                            <span className="text-[10px] theme-text-muted">{new Date(ev.created_at).toLocaleString('es-MX')}</span>
                        </div>
                        {ev.kind === 'movimiento' ? (
                            <>
                                <p className="text-sm theme-text-main">
                                    Por: <strong>{ev.usuario?.name}</strong>
                                    {ev.user_destino && <> → <strong>{ev.user_destino.name}</strong></>}
                                </p>
                                {ev.estado_anterior && ev.estado_nuevo && (
                                    <p className="text-xs theme-text-muted">{ev.estado_anterior} → {ev.estado_nuevo}</p>
                                )}
                                {ev.motivo && <p className="text-xs theme-text-muted">Motivo: {ev.motivo}</p>}
                                <SnapshotDetalle snapshot={ev.datos_snapshot} />
                            </>
                        ) : (
                            <>
                                <p className="text-sm theme-text-main">Por: <strong>{ev.usuario?.name}</strong> · {ev.estado}</p>
                                {ev.proveedor && <p className="text-xs theme-text-muted">Proveedor: {ev.proveedor}</p>}
                                {ev.descripcion && <p className="text-xs theme-text-muted italic">{ev.descripcion}</p>}
                            </>
                        )}
                        {ev.notas && <p className="text-xs theme-text-muted italic">{ev.notas}</p>}
                    </div>
                </div>
            ))}
        </div>
    );
}

export function HistorialAsignaciones({ asignaciones = [] }) {
    if (!asignaciones.length) return null;

    return (
        <div className="space-y-3 mt-4">
            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Historial de pertenencia</p>
            {asignaciones.map((a) => (
                <div key={a.id} className="rounded-xl px-4 py-3 theme-element border theme-border text-sm">
                    <div className="flex justify-between gap-2">
                        <Link href={route('activos.index', { responsable_user_id: a.usuario?.id })} className="font-bold hover:underline" style={{ color: 'var(--color-primario)' }}>
                            {a.usuario?.name}
                        </Link>
                        <span className={`text-[10px] font-black uppercase ${a.activa ? 'text-green-600 dark:text-green-400' : 'theme-text-muted'}`}>
                            {a.activa ? 'Activa' : 'Cerrada'}
                        </span>
                    </div>
                    <p className="text-[10px] theme-text-muted mt-1">
                        {a.fecha_inicio}{a.fecha_fin ? ` → ${a.fecha_fin}` : ' → presente'}
                    </p>
                </div>
            ))}
        </div>
    );
}
