import React from 'react';
import { Loader2, MapPin } from 'lucide-react';
import useEstablecerSucursalActivaPdv from '@/hooks/useEstablecerSucursalActivaPdv';
import { GELIA_FIELDSET_LEGEND, GELIA_ICON_BOX, THEME_SELECT } from '@/utils/geliaTheme';

export { RELOAD_ONLY_RESGUARDOS } from '@/hooks/useEstablecerSucursalActivaPdv';

export default function SelectorSucursalActivaPdv({
    sucursalActiva = null,
    sucursalesAsignadas = [],
    variante = 'compacto',
    reloadOnly = null,
    className = '',
    value = null,
    onChange = null,
    disabled = false,
}) {
    const { cambiando, error, establecer } = useEstablecerSucursalActivaPdv({ reloadOnly });

    const multiples = sucursalesAsignadas.length > 1;
    const activaId = sucursalActiva?.id ? String(sucursalActiva.id) : '';
    const nombreActivo = sucursalActiva?.nombre ?? sucursalesAsignadas[0]?.nombre ?? '';

    if (!nombreActivo && sucursalesAsignadas.length === 0) {
        return null;
    }

    const onSelectChange = async (event) => {
        const sucursalId = event.target.value;
        if (typeof onChange === 'function') {
            onChange(sucursalId);
            return;
        }
        await establecer(sucursalId, { activaId });
    };

    const selectValue = value != null ? String(value) : activaId;
    const selectDisabled = disabled || cambiando;

    if (variante === 'pagina') {
        if (!multiples) {
            return (
                <p className={`text-sm font-bold theme-text-main m-0 ${className}`.trim()}>
                    Sucursal:{' '}
                    <span className="text-[var(--color-primario)]">{nombreActivo}</span>
                </p>
            );
        }

        return (
            <div className={`space-y-2 ${className}`.trim()}>
                <label className="block space-y-2">
                    <span className={GELIA_FIELDSET_LEGEND}>Sucursal activa</span>
                    <select
                        value={selectValue}
                        onChange={onSelectChange}
                        disabled={selectDisabled}
                        className={`${THEME_SELECT} w-full !py-3 !px-4 text-sm font-bold min-h-[48px]`}
                        aria-label="Seleccionar sucursal activa"
                    >
                        {sucursalesAsignadas.map(({ id, nombre }) => (
                            <option key={id} value={String(id)}>{nombre}</option>
                        ))}
                    </select>
                </label>
                {error && (
                    <p className="text-xs font-semibold text-red-600 dark:text-red-300 m-0">{error}</p>
                )}
            </div>
        );
    }

    if (variante === 'modal') {
        return (
            <section
                className={`gelia-panel-suave p-4 space-y-3 ${className}`.trim()}
                data-pdv-selector-sucursal-modal
            >
                <div className="flex items-start gap-3">
                    <span className={GELIA_ICON_BOX} aria-hidden>
                        <MapPin className="w-4 h-4 theme-text-primario" />
                    </span>
                    <div className="min-w-0 flex-1 space-y-1">
                        <p className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                            Sucursal activa
                        </p>
                        <p className="text-xs theme-text-muted m-0 leading-snug">
                            {multiples
                                ? 'Elige con cuál operarás en piso. Los cambios aplican a todo el módulo.'
                                : 'Esta es la única sucursal asignada para operar en piso.'}
                        </p>
                    </div>
                </div>

                {multiples ? (
                    <label className="block space-y-2">
                        <span className={GELIA_FIELDSET_LEGEND}>Seleccionar sucursal</span>
                        <div className="relative">
                            <select
                                value={selectValue}
                                onChange={onSelectChange}
                                disabled={selectDisabled}
                                className={`${THEME_SELECT} w-full !py-2.5 !px-3 text-sm font-bold min-h-[44px]`}
                                aria-label="Seleccionar sucursal activa"
                            >
                                {sucursalesAsignadas.map(({ id, nombre }) => (
                                    <option key={id} value={String(id)}>{nombre}</option>
                                ))}
                            </select>
                            {cambiando && (
                                <Loader2
                                    className="w-4 h-4 animate-spin absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none theme-text-primario"
                                    aria-hidden
                                />
                            )}
                        </div>
                    </label>
                ) : (
                    <p className="text-sm font-bold theme-text-main m-0">
                        {nombreActivo}
                    </p>
                )}

                {error && (
                    <p className="text-xs font-semibold text-red-600 dark:text-red-300 m-0" role="alert">
                        {error}
                    </p>
                )}
            </section>
        );
    }

    return (
        <div
            className={`flex flex-wrap items-center gap-x-2 gap-y-1 text-[10px] font-bold uppercase tracking-widest theme-text-muted ${className}`.trim()}
            data-pdv-selector-sucursal-compacto
        >
            <MapPin className="w-3.5 h-3.5 shrink-0 theme-text-primario" aria-hidden />
            {multiples ? (
                <>
                    <span className="shrink-0">Sucursal</span>
                    <span className="relative inline-flex items-center gap-1.5 min-w-0">
                        <select
                            value={selectValue}
                            onChange={onSelectChange}
                            disabled={selectDisabled}
                            className={`${THEME_SELECT} !py-1 !px-2 text-[10px] font-bold min-h-[32px] max-w-[10rem] w-full sm:w-auto`}
                            aria-label="Seleccionar sucursal activa"
                        >
                            {sucursalesAsignadas.map(({ id, nombre }) => (
                                <option key={id} value={String(id)}>{nombre}</option>
                            ))}
                        </select>
                        {cambiando && (
                            <Loader2 className="w-3.5 h-3.5 animate-spin shrink-0 theme-text-primario" aria-hidden />
                        )}
                    </span>
                </>
            ) : (
                <span className="min-w-0 truncate">
                    Sucursal:{' '}
                    <span className="theme-text-main">{nombreActivo}</span>
                </span>
            )}
            {error && (
                <span className="w-full text-[10px] font-semibold text-red-600 dark:text-red-300 normal-case tracking-normal">
                    {error}
                </span>
            )}
        </div>
    );
}
