import { useEffect, useMemo, useState } from 'react';
import { Filter, SlidersHorizontal, X } from 'lucide-react';
import { GELIA_BADGE, GELIA_BTN_OUTLINE, THEME_INPUT, THEME_LABEL, THEME_SELECT, geliaToggleBtnClass } from '../../../utils/geliaTheme';
import { OPCIONES_FILTRO_RESULTADO } from './escalonamientoUi';

export default function FiltrosEscalonamientoClientes({
    filtros = {},
    opcionesListas = [],
    onAplicar,
    onLimpiar,
}) {
    const [abierto, setAbierto] = useState(false);
    const [local, setLocal] = useState(() => estadoLocal(filtros));

    const conteoActivos = useMemo(() => contarFiltrosAvanzados(filtros), [filtros]);

    useEffect(() => {
        setLocal(estadoLocal(filtros));
    }, [filtros]);

    const aplicar = () => {
        onAplicar({
            lista_vigente_id: local.lista_vigente_id || undefined,
            lista_calculada_id: local.lista_calculada_id || undefined,
            ventas_min: local.ventas_min || undefined,
            ventas_max: local.ventas_max || undefined,
            devoluciones_min: local.devoluciones_min || undefined,
            devoluciones_max: local.devoluciones_max || undefined,
            ocultar_inactivos: local.ocultar_inactivos ? 1 : undefined,
            resultado: local.resultado.length > 0 ? local.resultado.join(',') : undefined,
            page: 1,
        });
        setAbierto(false);
    };

    const toggleResultado = (id) => {
        setLocal((prev) => {
            const tiene = prev.resultado.includes(id);
            return {
                ...prev,
                resultado: tiene ? prev.resultado.filter((r) => r !== id) : [...prev.resultado, id],
            };
        });
    };

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap gap-3 items-center">
                <button
                    type="button"
                    className={GELIA_BTN_OUTLINE}
                    onClick={() => setAbierto((v) => !v)}
                    aria-expanded={abierto}
                >
                    <SlidersHorizontal className="w-4 h-4" aria-hidden />
                    Filtros
                    {conteoActivos > 0 && (
                        <span className={`${GELIA_BADGE} bg-[var(--color-primario)] text-white`}>
                            {conteoActivos}
                        </span>
                    )}
                </button>
                {conteoActivos > 0 && (
                    <button type="button" className={GELIA_BTN_OUTLINE} onClick={onLimpiar}>
                        <X className="w-4 h-4" aria-hidden />
                        Limpiar filtros
                    </button>
                )}
            </div>

            {abierto && (
                <div className="theme-element border theme-border rounded-2xl p-5 md:p-6 space-y-6 animate-page-reveal">
                    <div className="flex items-center gap-3 theme-text-main">
                        <Filter className="w-5 h-5" aria-hidden />
                        <span className="text-base font-bold">Filtros avanzados</span>
                    </div>

                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <label className="space-y-1.5">
                            <span className={THEME_LABEL}>Lista vigente</span>
                            <select
                                className={THEME_SELECT}
                                value={local.lista_vigente_id}
                                onChange={(e) => setLocal((p) => ({ ...p, lista_vigente_id: e.target.value }))}
                            >
                                <option value="">Todas</option>
                                {opcionesListas.map((l) => (
                                    <option key={l.id} value={l.id}>{l.nombre}</option>
                                ))}
                            </select>
                        </label>
                        <label className="space-y-1.5">
                            <span className={THEME_LABEL}>Lista calculada</span>
                            <select
                                className={THEME_SELECT}
                                value={local.lista_calculada_id}
                                onChange={(e) => setLocal((p) => ({ ...p, lista_calculada_id: e.target.value }))}
                            >
                                <option value="">Todas</option>
                                {opcionesListas.map((l) => (
                                    <option key={l.id} value={l.id}>{l.nombre}</option>
                                ))}
                            </select>
                        </label>
                        <label className="space-y-1.5 flex items-end">
                            <button
                                type="button"
                                className={geliaToggleBtnClass(local.ocultar_inactivos)}
                                onClick={() => setLocal((p) => ({ ...p, ocultar_inactivos: !p.ocultar_inactivos }))}
                            >
                                Ocultar clientes inactivos
                            </button>
                        </label>
                    </div>

                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <label className="space-y-1.5">
                            <span className={THEME_LABEL}>Ventas mín.</span>
                            <input type="number" step="0.01" min="0" className={THEME_INPUT} value={local.ventas_min} onChange={(e) => setLocal((p) => ({ ...p, ventas_min: e.target.value }))} />
                        </label>
                        <label className="space-y-1.5">
                            <span className={THEME_LABEL}>Ventas máx.</span>
                            <input type="number" step="0.01" min="0" className={THEME_INPUT} value={local.ventas_max} onChange={(e) => setLocal((p) => ({ ...p, ventas_max: e.target.value }))} />
                        </label>
                        <label className="space-y-1.5">
                            <span className={THEME_LABEL}>Devoluciones mín.</span>
                            <input type="number" step="0.01" min="0" className={THEME_INPUT} value={local.devoluciones_min} onChange={(e) => setLocal((p) => ({ ...p, devoluciones_min: e.target.value }))} />
                        </label>
                        <label className="space-y-1.5">
                            <span className={THEME_LABEL}>Devoluciones máx.</span>
                            <input type="number" step="0.01" min="0" className={THEME_INPUT} value={local.devoluciones_max} onChange={(e) => setLocal((p) => ({ ...p, devoluciones_max: e.target.value }))} />
                        </label>
                    </div>

                    <fieldset className="border-0 m-0 p-0 space-y-3">
                        <legend className={THEME_LABEL}>Resultado</legend>
                        <div className="flex flex-wrap gap-3">
                            {OPCIONES_FILTRO_RESULTADO.map((opt) => (
                                <button
                                    key={opt.id}
                                    type="button"
                                    className={geliaToggleBtnClass(local.resultado.includes(opt.id))}
                                    onClick={() => toggleResultado(opt.id)}
                                >
                                    {opt.label}
                                </button>
                            ))}
                        </div>
                    </fieldset>

                    <div className="flex justify-end pt-1">
                        <button type="button" className={GELIA_BTN_OUTLINE} onClick={aplicar}>
                            Aplicar filtros
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

function estadoLocal(filtros) {
    const resultado = Array.isArray(filtros.resultado)
        ? filtros.resultado
        : (filtros.resultado ? String(filtros.resultado).split(',').filter(Boolean) : []);

    return {
        lista_vigente_id: filtros.lista_vigente_id ? String(filtros.lista_vigente_id) : '',
        lista_calculada_id: filtros.lista_calculada_id ? String(filtros.lista_calculada_id) : '',
        ventas_min: filtros.ventas_min || '',
        ventas_max: filtros.ventas_max || '',
        devoluciones_min: filtros.devoluciones_min || '',
        devoluciones_max: filtros.devoluciones_max || '',
        ocultar_inactivos: Boolean(filtros.ocultar_inactivos),
        resultado,
    };
}

function contarFiltrosAvanzados(filtros) {
    let n = 0;
    if (filtros.lista_vigente_id) n++;
    if (filtros.lista_calculada_id) n++;
    if (filtros.ventas_min) n++;
    if (filtros.ventas_max) n++;
    if (filtros.devoluciones_min) n++;
    if (filtros.devoluciones_max) n++;
    if (filtros.ocultar_inactivos) n++;
    const res = Array.isArray(filtros.resultado) ? filtros.resultado : (filtros.resultado ? String(filtros.resultado).split(',') : []);
    if (res.length > 0) n++;
    return n;
}
