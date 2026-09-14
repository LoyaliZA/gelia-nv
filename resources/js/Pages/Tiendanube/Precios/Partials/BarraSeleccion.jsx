import React from 'react';
import { GELIA_BTN_OUTLINE } from '../../../../utils/geliaTheme';

export default function BarraSeleccion({
    seleccion,
    variantesResultado = 0,
    onSeleccionarTodos,
    onQuitar,
    onConfigurarCalculo,
    onExportarProductos,
    puedeConfigurar = false,
    puedeExportar = false,
}) {
    const total = seleccion?.total_variantes || 0;
    const productos = seleccion?.total_productos || 0;
    const invalidos = seleccion?.miembros_invalidos || 0;

    if (total === 0) {
        return (
            <div className="border theme-border rounded-xl px-3 py-2 text-sm theme-text-muted">
                Seleccione variantes para el cálculo.
            </div>
        );
    }

    return (
        <div className="sticky bottom-2 z-20 border theme-border rounded-xl px-3 py-3 theme-surface flex flex-col md:flex-row md:items-center gap-3">
            <p className="text-sm theme-text-main m-0">
                {productos} productos · {total} variantes seleccionadas
                {seleccion?.modo === 'todos_resultados' ? ' (todos los resultados congelados)' : ' (página o filas marcadas)'}
                {invalidos > 0 ? ` · ${invalidos} ya no están en el catálogo` : ''}
            </p>
            <div className="flex flex-wrap gap-2 md:ml-auto">
                {variantesResultado > total && (
                    <button
                        type="button"
                        onClick={onSeleccionarTodos}
                        className={GELIA_BTN_OUTLINE}
                    >
                        Seleccionar las {variantesResultado} variantes de todos los resultados
                    </button>
                )}
                <button
                    type="button"
                    onClick={onQuitar}
                    className={GELIA_BTN_OUTLINE}
                >
                    Quitar selección
                </button>
                <button
                    type="button"
                    disabled={!puedeConfigurar}
                    onClick={onConfigurarCalculo}
                    title={puedeConfigurar ? 'Abrir editor de cálculo' : 'Requiere permiso de reglas y selección activa'}
                    className={`px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest text-white ${
                        puedeConfigurar ? '' : 'opacity-50 cursor-not-allowed'
                    }`}
                    style={{ backgroundColor: 'var(--color-primario)' }}
                >
                    Configurar cálculo
                </button>
                {onExportarProductos && (
                    <button
                        type="button"
                        disabled={!puedeExportar}
                        onClick={onExportarProductos}
                        title={puedeExportar ? 'Exportar productos seleccionados en CSV nativo' : 'Requiere permiso de exportación'}
                        className={`${GELIA_BTN_OUTLINE} disabled:opacity-50`}
                    >
                        Exportar productos
                    </button>
                )}
            </div>
        </div>
    );
}
