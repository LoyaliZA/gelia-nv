import React, { useState } from 'react';
import { RefreshCw, Loader2, X, SlidersHorizontal, CalendarDays, Warehouse } from 'lucide-react';
import CampoBusqueda from '@/Components/CampoBusqueda';
import { THEME_INPUT, THEME_LABEL, THEME_SELECT } from '../../../utils/geliaTheme';

const BTN_TOOLBAR =
    'inline-flex items-center justify-center gap-2 rounded-xl border theme-border theme-element shrink-0 '
    + 'min-h-[44px] md:min-h-[40px] min-w-[44px] px-3 text-sm font-semibold theme-text-main hover:bg-black/5 dark:hover:bg-white/5 '
    + 'outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primario)] '
    + 'disabled:opacity-60';

/** Conteo por bandeja: ver bandejasPedidos.js (OBS_CEDIS: metricas.obs_cedis). */
export default function FiltrosPedidos({
    filtros = {},
    busqueda,
    onBuscar,
    onActualizar,
    buscando = false,
    filtrosExtra = {},
    onCambiarFiltros,
    almacenes = [],
}) {
    const valor = busqueda ?? filtros.q ?? '';
    const [abierto, setAbierto] = useState(false);
    const nombreAlmacen = (id) => almacenes.find((a) => String(a.id) === String(id))?.nombre || `Almacén ${id}`;
    const activos = [
        filtrosExtra.mios ? { key: 'mios', texto: 'Solo mis pedidos', quitar: { mios: '' } } : null,
        filtrosExtra.almacen_id ? { key: 'almacen_id', texto: nombreAlmacen(filtrosExtra.almacen_id), quitar: { almacen_id: '' } } : null,
        filtrosExtra.desde ? { key: 'desde', texto: `Desde ${filtrosExtra.desde}`, quitar: { desde: '' } } : null,
        filtrosExtra.hasta ? { key: 'hasta', texto: `Hasta ${filtrosExtra.hasta}`, quitar: { hasta: '' } } : null,
    ].filter(Boolean);
    const hoy = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Mexico_City' }).format(new Date());
    const esHoy = filtrosExtra.desde === hoy && filtrosExtra.hasta === hoy;
    const limpiarTodo = { mios: '', almacen_id: '', desde: '', hasta: '' };

    return (
        <div className="space-y-3 min-w-0">
        <div className="flex items-center gap-2 min-w-0">
            <div className="relative flex-1 min-w-0">
                <label htmlFor="pedidos-busqueda" className="sr-only">Buscar folio, cliente o número</label>
                <CampoBusqueda
                    wrapperClassName="relative"
                    id="pedidos-busqueda"
                    name="q"
                    enterKeyHint="search"
                    type="search"
                    value={valor}
                    onChange={(e) => onBuscar(e.target.value)}
                    placeholder="Buscar por folio, cliente o remisión…"
                    className={`${THEME_INPUT} w-full py-2.5 text-sm font-semibold pr-10`}
                    aria-busy={buscando}
                    autoComplete="off"
                    suffix={buscando ? (
                        <Loader2
                            className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 animate-spin theme-text-muted"
                            aria-label="Buscando"
                        />
                    ) : null}
                />
            </div>
            {valor ? (
                <button type="button" onClick={() => onBuscar('')} className={BTN_TOOLBAR} aria-label="Limpiar búsqueda">
                    <X className="w-4 h-4" aria-hidden />
                    <span className="hidden sm:inline">Limpiar</span>
                </button>
            ) : null}
            {onCambiarFiltros && almacenes.length > 0 && (
                <div className="relative hidden md:block shrink-0">
                    <label htmlFor="pedidos-toolbar-almacen" className="sr-only">Almacén</label>
                    <Warehouse className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted pointer-events-none" aria-hidden />
                    <select
                        id="pedidos-toolbar-almacen"
                        value={filtrosExtra.almacen_id || ''}
                        onChange={(e) => onCambiarFiltros({ almacen_id: e.target.value })}
                        className={`${THEME_SELECT} min-h-[40px] pl-9 text-sm font-semibold max-w-[12rem]`}
                    >
                        <option value="">Todos los almacenes</option>
                        {almacenes.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}
                    </select>
                </div>
            )}
            {onCambiarFiltros && (
                <button
                    type="button"
                    aria-pressed={esHoy}
                    onClick={() => onCambiarFiltros(esHoy ? { desde: '', hasta: '' } : { desde: hoy, hasta: hoy })}
                    className={`${BTN_TOOLBAR} ${esHoy ? 'border-[color:var(--color-primario)] bg-[color:color-mix(in_srgb,var(--color-primario)_10%,var(--theme-element-bg))]' : ''}`}
                >
                    <CalendarDays className="w-4 h-4" aria-hidden />
                    <span className="hidden sm:inline">Hoy</span>
                </button>
            )}
            {onCambiarFiltros && (
                <button
                    type="button"
                    onClick={() => setAbierto((v) => !v)}
                    aria-expanded={abierto}
                    aria-controls="pedidos-panel-filtros"
                    className={BTN_TOOLBAR}
                >
                    <SlidersHorizontal className="w-4 h-4" aria-hidden />
                    <span className="hidden sm:inline">Filtros</span>
                    {activos.length > 0 ? (
                        <span className="min-w-[1.25rem] px-1 rounded-full text-xs font-bold tabular-nums bg-[color-mix(in_srgb,var(--color-primario)_18%,transparent)] theme-text-primario">{activos.length}</span>
                    ) : null}
                </button>
            )}
            {onActualizar && (
                <button
                    type="button"
                    onClick={onActualizar}
                    disabled={buscando}
                    aria-label="Actualizar listado"
                    className={BTN_TOOLBAR}
                >
                    <RefreshCw className={`w-4 h-4 ${buscando ? 'animate-spin' : ''}`} aria-hidden />
                    <span className="hidden md:inline">Actualizar</span>
                </button>
            )}
        </div>
        {onCambiarFiltros && abierto ? (
            <div id="pedidos-panel-filtros" className="rounded-xl border theme-border theme-element p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                <div className="min-w-0 md:hidden">
                    <label htmlFor="pedidos-filtro-almacen" className={THEME_LABEL}>Almacén</label>
                    <select
                        id="pedidos-filtro-almacen"
                        value={filtrosExtra.almacen_id || ''}
                        onChange={(e) => onCambiarFiltros({ almacen_id: e.target.value })}
                        className={`${THEME_SELECT} mt-1 w-full min-h-[44px] text-sm`}
                    >
                        <option value="">Todos</option>
                        {almacenes.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}
                    </select>
                </div>
                {[['desde', 'Desde'], ['hasta', 'Hasta']].map(([clave, texto]) => (
                    <div key={clave} className="min-w-0">
                        <label htmlFor={`pedidos-filtro-${clave}`} className={THEME_LABEL}>{texto}</label>
                        <input
                            id={`pedidos-filtro-${clave}`}
                            type="date"
                            value={filtrosExtra[clave] || ''}
                            max={clave === 'desde' ? (filtrosExtra.hasta || undefined) : undefined}
                            min={clave === 'hasta' ? (filtrosExtra.desde || undefined) : undefined}
                            onChange={(e) => onCambiarFiltros({ [clave]: e.target.value })}
                            className={`${THEME_INPUT} block mt-1 w-full min-w-0 min-h-[44px] text-sm`}
                        />
                    </div>
                ))}
                <div className="flex flex-wrap items-center gap-2 min-h-[44px]">
                    <button
                        type="button"
                        aria-pressed={Boolean(filtrosExtra.mios)}
                        onClick={() => onCambiarFiltros({ mios: filtrosExtra.mios ? '' : '1' })}
                        className={`${BTN_TOOLBAR} ${filtrosExtra.mios ? 'border-[color:var(--color-primario)]' : ''}`}
                    >
                        Solo míos
                    </button>
                </div>
            </div>
        ) : null}
        {activos.length > 0 ? (
            <div className="flex flex-wrap items-center gap-2" role="group" aria-label="Filtros activos">
                {activos.map((f) => (
                    <button
                        key={f.key}
                        type="button"
                        onClick={() => onCambiarFiltros(f.quitar)}
                        aria-label={`Quitar filtro: ${f.texto}`}
                        className="inline-flex items-center gap-1.5 min-h-[44px] md:min-h-[32px] px-3 rounded-full border theme-border theme-element text-xs font-semibold theme-text-main outline-none focus-visible:outline-2 focus-visible:outline-[var(--color-primario)]"
                    >
                        {f.texto}
                        <X className="w-3 h-3" aria-hidden />
                    </button>
                ))}
                <button type="button" onClick={() => onCambiarFiltros(limpiarTodo)} className="text-xs font-semibold theme-text-main underline underline-offset-4 min-h-[44px] md:min-h-[32px]">
                    Limpiar filtros
                </button>
            </div>
        ) : null}
        </div>
    );
}
