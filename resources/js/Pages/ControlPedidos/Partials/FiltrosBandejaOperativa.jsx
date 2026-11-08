import React from 'react';
import { CalendarDays, RefreshCw, ArrowDownUp, UserRound } from 'lucide-react';
import CampoBusqueda from '@/Components/CampoBusqueda';
import { THEME_INPUT, THEME_SELECT, THEME_LABEL, GELIA_BTN_OUTLINE } from '../../../utils/geliaTheme';

export function IndicadoresOperacion({ opciones, activo, onCambiar, label }) {
    return <div className={`grid grid-cols-2 ${opciones.length === 5 ? 'sm:grid-cols-5' : 'sm:grid-cols-4'} gap-2`} role="group" aria-label={label}>
        {opciones.map(({ id, label: texto, valor, icon: Icon }, indice) => <button key={id} type="button" aria-pressed={activo === id} onClick={() => onCambiar(id)}
            className={`min-w-0 min-h-[72px] rounded-xl border p-3 aria-pressed:ring-1 aria-pressed:ring-[var(--color-primario)] text-left theme-surface-solid theme-no-blur ${opciones.length === 5 && indice === 0 ? 'col-span-2 sm:col-span-1' : ''}`}
            style={{ borderColor: activo === id ? 'var(--color-primario)' : 'var(--theme-border)', background: activo === id ? 'color-mix(in srgb, var(--color-primario) 10%, var(--theme-surface-solid))' : undefined }}>
            <span className="flex items-center justify-between gap-2"><Icon className="w-4 h-4 shrink-0 theme-text-primario" aria-hidden="true" /><span className="text-xl font-bold tabular-nums theme-text-main">{valor ?? 0}</span></span>
            <span className={`block mt-1 text-xs leading-snug ${activo === id ? 'theme-text-main font-bold' : 'theme-text-muted font-semibold'}`}>{texto}</span>
        </button>)}
    </div>;
}

export default function FiltrosBandejaOperativa({ id, valores, onCambiar, onLimpiar, onActualizar, buscando = false, fechaHoy }) {
    const hoy = fechaHoy || new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Mexico_City', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
    const esHoy = valores.desde === hoy && valores.hasta === hoy;
    return <div className="space-y-3">
        <div className="flex gap-2 items-end">
            <div className="min-w-0 flex-1"><label className={THEME_LABEL} htmlFor={`${id}-buscar`}>Buscar pedido</label>
                <CampoBusqueda id={`${id}-buscar`} wrapperClassName="relative mt-1" type="search" value={valores.q} onChange={e => onCambiar({ q: e.target.value }, true)} placeholder="Folio o cliente…" className={`${THEME_INPUT} w-full min-h-[44px] text-sm`} />
            </div>
            <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px] shrink-0`} aria-label="Actualizar pedidos" disabled={buscando} onClick={onActualizar}><RefreshCw className={`w-4 h-4 ${buscando ? 'animate-spin' : ''}`} aria-hidden /></button>
        </div>
        <div className="flex flex-wrap items-center gap-2" role="group" aria-label="Accesos de fecha">
            <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px] aria-pressed:ring-1 aria-pressed:ring-[var(--color-primario)]`} aria-pressed={esHoy} onClick={() => onCambiar({ desde: hoy, hasta: hoy })}><CalendarDays className="w-4 h-4" aria-hidden />Hoy</button>
            <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px] aria-pressed:ring-1 aria-pressed:ring-[var(--color-primario)]`} aria-pressed={!valores.desde && !valores.hasta} onClick={() => onCambiar({ desde: '', hasta: '' })}>Todas las fechas</button>
            <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px] aria-pressed:ring-1 aria-pressed:ring-[var(--color-primario)]`} aria-pressed={!valores.desde && !valores.hasta && valores.orden === 'antiguos'} onClick={() => onCambiar({ desde: '', hasta: '', orden: 'antiguos' })}><ArrowDownUp className="w-4 h-4" aria-hidden />Más antiguos</button>
        </div>
        <details className="border-t theme-border pt-2">
            <summary className="min-h-[44px] cursor-pointer text-sm font-semibold theme-text-main py-3">Fechas, vendedora y orden{(valores.desde || valores.hasta || valores.vendedora || valores.orden === 'recientes') ? ' · Filtros activos' : ''}</summary>
            <div className="space-y-3 pt-2">
            <div className="min-w-0"><label className={THEME_LABEL} htmlFor={`${id}-vendedora`}><span className="flex items-center gap-1.5"><UserRound className="w-3.5 h-3.5" aria-hidden />Vendedora</span></label>
                <input id={`${id}-vendedora`} type="search" value={valores.vendedora} onChange={e => onCambiar({ vendedora: e.target.value }, true)} placeholder="Nombre de la vendedora…" className={`${THEME_INPUT} mt-1 w-full min-h-[44px] text-sm`} />
            </div>
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 items-end">
                    {[['desde', 'Desde'], ['hasta', 'Hasta']].map(([clave, texto]) => <div key={clave} className="min-w-0"><label className={THEME_LABEL} htmlFor={`${id}-${clave}`}>{texto}</label><input id={`${id}-${clave}`} type="date" value={valores[clave]} onChange={e => onCambiar({ [clave]: e.target.value })} className={`${THEME_INPUT} block mt-1 w-full min-w-0 min-h-[44px] text-sm`} /></div>)}
                    <div className="col-span-2 sm:col-span-1 min-w-0"><label className={THEME_LABEL} htmlFor={`${id}-orden`}>Orden de solicitud</label><select id={`${id}-orden`} value={valores.orden} onChange={e => onCambiar({ orden: e.target.value })} className={`${THEME_SELECT} mt-1 w-full min-h-[44px] text-sm`}><option value="antiguos">Más antiguos primero</option><option value="recientes">Más recientes primero</option></select></div>
                </div>
                <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px]`} onClick={onLimpiar}>Limpiar filtros</button>
            </div>
        </details>
        <p className="text-xs theme-text-muted m-0">Jerarquía de atención vigente; fecha de solicitud dentro de cada prioridad.</p>
    </div>;
}
