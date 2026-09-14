import React, { useState } from 'react';
import { GELIA_BADGE, GELIA_BTN_OUTLINE } from '../../../../utils/geliaTheme';
import EdicionManualPrecio, { irACorregirCosto } from './EdicionManualPrecio';

function celdaCampo(campo) {
    if (!campo || campo.sin_cambio) {
        return <span className="theme-text-muted">Sin cambio</span>;
    }
    return (
        <span>
            {campo.actual ?? '—'} → {campo.propuesto ?? '—'}
            {campo.diferencia ? ` (${campo.diferencia})` : ''}
        </span>
    );
}

const ESTADO_LABELS = {
    con_cambio: 'Con cambio',
    sin_cambio: 'Sin cambio',
    bloqueada: 'Bloqueada',
    excluida: 'Excluida',
    error: 'Error',
};

const ESTADO_BADGE = {
    con_cambio: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20',
    sin_cambio: 'theme-element theme-text-muted border theme-border',
    bloqueada: 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20',
    excluida: 'theme-element theme-text-muted border theme-border',
    error: 'bg-red-500/10 text-red-700 dark:text-red-400 border-red-500/20',
};

function BadgeEstado({ estado }) {
    const label = ESTADO_LABELS[estado] || estado;
    const tone = ESTADO_BADGE[estado] || 'theme-element theme-text-main border theme-border';
    return (
        <span className={`${GELIA_BADGE} ${tone}`}>
            {label}
        </span>
    );
}

export default function TablaRevisionPrecios({
    filas = [],
    meta,
    puedeVerCosto,
    destinoPrincipal = 'normal',
    onPagina,
    onExcluir,
    onAjustar,
    busy,
}) {
    const [edicion, setEdicion] = useState(null);

    return (
        <div className="space-y-2">
            <div className="overflow-auto max-h-[60vh] border theme-border rounded-xl">
                <table className="min-w-[720px] w-full text-sm">
                    <thead className="sticky top-0 z-10 theme-surface">
                        <tr className="border-b theme-border">
                            <th className="px-2 py-2 text-left sticky left-0 theme-surface text-[10px] font-black uppercase tracking-widest theme-text-muted">Producto</th>
                            <th className="px-2 py-2 text-left text-[10px] font-black uppercase tracking-widest theme-text-muted">SKU</th>
                            <th className="px-2 py-2 text-right text-[10px] font-black uppercase tracking-widest theme-text-muted">Actual → propuesto</th>
                            <th className="px-2 py-2 text-[10px] font-black uppercase tracking-widest theme-text-muted">Estado</th>
                            <th className="px-2 py-2 text-[10px] font-black uppercase tracking-widest theme-text-muted">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filas.map((fila) => (
                            <tr key={fila.id} className="border-t theme-border align-top">
                                <td className="px-2 py-2 sticky left-0 theme-surface min-w-[160px]">
                                    <div className="flex items-center gap-2">
                                        {fila.miniatura ? (
                                            <img src={fila.miniatura} alt="" className="w-8 h-8 rounded object-cover shrink-0" />
                                        ) : (
                                            <div className="w-8 h-8 rounded bg-black/10 shrink-0" />
                                        )}
                                        <div>
                                            <p className="m-0 font-medium theme-text-main">{fila.nombre || '—'}</p>
                                            <p className="m-0 text-xs theme-text-muted">{(fila.atributos || []).join(' · ')}</p>
                                        </div>
                                    </div>
                                </td>
                                <td className="px-2 py-2 whitespace-nowrap">{fila.sku || '—'}</td>
                                <td className="px-2 py-2 text-right">
                                    {celdaCampo(fila.campos?.[destinoPrincipal])}
                                    {fila.errores?.length > 0 && (
                                        <p className="text-xs text-red-700 m-0 mt-1">{fila.errores.join(', ')}</p>
                                    )}
                                    {edicion?.id === fila.id && (
                                        <EdicionManualPrecio
                                            item={fila}
                                            destino={destinoPrincipal}
                                            busy={busy}
                                            onCancelar={() => setEdicion(null)}
                                            onGuardar={async (datos) => {
                                                await onAjustar(fila, datos);
                                                setEdicion(null);
                                            }}
                                        />
                                    )}
                                </td>
                                <td className="px-2 py-2">
                                    <BadgeEstado estado={fila.estado_fila} />
                                </td>
                                <td className="px-2 py-2">
                                    <div className="flex flex-col gap-1">
                                        <button
                                            type="button"
                                            className={`${GELIA_BTN_OUTLINE} justify-start`}
                                            onClick={() => onExcluir(fila, !fila.excluido)}
                                        >
                                            {fila.excluido ? 'Reincluir' : 'Excluir'}
                                        </button>
                                        <button
                                            type="button"
                                            className={`${GELIA_BTN_OUTLINE} justify-start`}
                                            onClick={() => setEdicion({ id: fila.id })}
                                        >
                                            Editar resultado
                                        </button>
                                        {puedeVerCosto && (
                                            <button
                                                type="button"
                                                className={`${GELIA_BTN_OUTLINE} justify-start`}
                                                onClick={() => irACorregirCosto(fila.variante_id)}
                                            >
                                                Corregir costo
                                            </button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {meta && meta.last_page > 1 && (
                <div className="flex gap-2">
                    <button
                        type="button"
                        disabled={meta.current_page <= 1}
                        onClick={() => onPagina(meta.current_page - 1)}
                        className="px-2 py-1 rounded-lg border theme-border text-xs disabled:opacity-50"
                    >
                        Anterior
                    </button>
                    <span className="text-xs theme-text-muted self-center">Página {meta.current_page} de {meta.last_page}</span>
                    <button
                        type="button"
                        disabled={meta.current_page >= meta.last_page}
                        onClick={() => onPagina(meta.current_page + 1)}
                        className="px-2 py-1 rounded-lg border theme-border text-xs disabled:opacity-50"
                    >
                        Siguiente
                    </button>
                </div>
            )}
        </div>
    );
}
