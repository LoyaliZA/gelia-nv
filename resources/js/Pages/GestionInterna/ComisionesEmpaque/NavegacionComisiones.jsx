import { Banknote, BarChart3, CalendarDays, ClipboardCheck, Coins, History, KeyRound, LayoutGrid, Package, RefreshCw, Settings, UsersRound, Wallet } from 'lucide-react';
import { GELIA_SEGMENT_TABS_SCROLL, GELIA_SEGMENT_TABS_TRACK_COMPACT, GELIA_SEGMENT_TABS_TRACK_SCROLL } from '@/utils/geliaTheme';

export const SECCIONES_PRINCIPALES = [
    { id: 'acumulado', nombre: 'Empaques', Icono: Package },
    { id: 'periodos', nombre: 'Liquidaciones', Icono: Wallet },
    { id: 'productividad', nombre: 'Productividad', Icono: BarChart3 },
    { id: 'configuracion', nombre: 'Configuración', Icono: Settings },
];

export const PASOS_LIQUIDACION = [
    { id: 'periodos', nombre: 'Periodos', Icono: CalendarDays, paso: 1 },
    { id: 'preparar', nombre: 'Preparar corte', Icono: ClipboardCheck, paso: 2 },
    { id: 'pagos', nombre: 'Por pagar', Icono: Banknote, paso: 3 },
    { id: 'cortes', nombre: 'Historial', Icono: History, paso: 4 },
];

export const SECCIONES_CONFIGURACION = [
    { id: 'tarifas', nombre: 'Tarifa por caja', Icono: Coins },
    { id: 'equipo', nombre: 'Equipo y porcentajes', Icono: UsersRound },
    { id: 'areas', nombre: 'Áreas', Icono: LayoutGrid },
    { id: 'accesos', nombre: 'Accesos', Icono: KeyRound },
    { id: 'sincronizacion', nombre: 'Sincronización e históricos', Icono: RefreshCw },
];

const ADMIN_CONFIG = ['areas', 'accesos', 'sincronizacion'];

export function tieneContextoCorte(tab, filtros = {}) {
    return tab === 'preparar' || Boolean(filtros.retorno_corte) || Boolean(filtros.limite_desde) || Boolean(filtros.limite_hasta);
}

export function seccionConfiguracionActiva(tab, filtros = {}, cargaRevision = null) {
    if (cargaRevision) return 'sincronizacion';
    const inicial = tab === 'equipo' ? 'equipo' : filtros.seccion;
    return SECCIONES_CONFIGURACION.some((item) => item.id === inicial) ? inicial : 'tarifas';
}

export function itemsPrincipales({ principal, puedeConfigurar, onNavegar, onPrecargar }) {
    return SECCIONES_PRINCIPALES
        .filter((item) => item.id !== 'configuracion' || puedeConfigurar)
        .map((item) => ({
            ...item,
            actual: principal === item.id,
            onPrecargar: () => onPrecargar?.(item.id),
            onClick: () => onNavegar(item.id),
        }));
}

export function itemsLiquidacion({ tab, filtros, onNavegar }) {
    const contexto = tieneContextoCorte(tab, filtros);
    return PASOS_LIQUIDACION.map((item) => {
        const deshabilitado = item.id === 'preparar' && !contexto;
        return {
            ...item,
            actual: tab === item.id,
            deshabilitado,
            tituloDeshabilitado: deshabilitado ? 'Prepare el corte desde un periodo.' : undefined,
            ariaLabel: item.id === 'preparar' ? 'Paso 2 de 4: Preparar corte' : undefined,
            onClick: () => onNavegar(item.id),
        };
    });
}

export function itemsConfiguracion({ seccion, simple, onSeccion }) {
    const visibles = simple && !ADMIN_CONFIG.includes(seccion) ? SECCIONES_CONFIGURACION.slice(0, 2) : SECCIONES_CONFIGURACION;
    return visibles.map((item) => ({
        ...item,
        actual: seccion === item.id,
        onClick: () => onSeccion(item.id),
    }));
}

const BOTON = 'gelia-segment-btn min-h-11 min-w-0 px-1 motion-reduce:transition-none focus-visible:!outline-2 focus-visible:outline-offset-2';

function BotonNav({ id, nombre, Icono, actual, deshabilitado, tituloDeshabilitado, ariaLabel, paso, onClick, onPrecargar }) {
    const boton = (
        <button
            type="button"
            data-paso={paso || undefined}
            className={`${BOTON} ${paso ? 'before:mr-1 before:inline-flex before:size-5 before:shrink-0 before:items-center before:justify-center before:rounded-full before:border before:border-current before:text-[10px] before:font-black before:leading-none before:tabular-nums before:content-[attr(data-paso)]' : ''} ${deshabilitado ? 'cursor-not-allowed opacity-50' : ''}`}
            aria-current={actual && !deshabilitado ? 'page' : undefined}
            aria-disabled={deshabilitado || undefined}
            aria-label={ariaLabel}
            aria-describedby={deshabilitado && tituloDeshabilitado ? `nav-comisiones-${id}-ayuda` : undefined}
            data-active={actual && !deshabilitado}
            onPointerEnter={deshabilitado ? undefined : onPrecargar}
            onFocus={deshabilitado ? undefined : onPrecargar}
            onClick={() => { if (!deshabilitado) onClick?.(); }}
        >
            {Icono && <Icono className="size-4 shrink-0" aria-hidden="true" />}
            {nombre}
        </button>
    );

    if (!deshabilitado || !tituloDeshabilitado) return boton;

    return (
        <div className="group relative min-w-0">
            {boton}
            <span id={`nav-comisiones-${id}-ayuda`} role="tooltip" className="pointer-events-none invisible absolute left-1/2 top-full z-10 mt-1 -translate-x-1/2 whitespace-nowrap rounded-md border theme-border theme-element px-2 py-1 text-xs group-hover:visible group-focus-within:visible">
                {tituloDeshabilitado}
            </span>
        </div>
    );
}

export default function NavegacionComisiones({ ariaLabel, compacto = false, items }) {
    return (
        <nav className={`gelia-segment min-w-0 p-1 ${compacto ? GELIA_SEGMENT_TABS_SCROLL : ''}`.trim()} aria-label={ariaLabel}>
            <div className={compacto ? `${GELIA_SEGMENT_TABS_TRACK_COMPACT} ${GELIA_SEGMENT_TABS_TRACK_SCROLL}` : 'grid w-full min-w-0 grid-cols-2 gap-1 sm:grid-cols-4'}>
                {items.map((item) => <BotonNav key={item.id} {...item} />)}
            </div>
        </nav>
    );
}
