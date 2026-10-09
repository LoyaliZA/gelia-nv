import React from 'react';
import { CheckCircle2, ChevronDown } from 'lucide-react';

export const MAX_PRODUCTOS_OK_ABIERTOS = 3;

/** One row per piece; four or more pieces start in a collapsed native disclosure. */
export default function ListaProductosOk({ productos = [], etiquetaInstancia = () => null, compactos = productos.length > MAX_PRODUCTOS_OK_ABIERTOS }) {
    if (productos.length === 0) return null;
    const lista = (
        <ul className="gelia-pedidos-productos-ok" aria-label="Productos OK">
            {productos.map((producto, indice) => {
                const instancia = etiquetaInstancia(producto);
                return (
                    <li key={producto.id || producto.client_uuid || `${producto.sku}-${indice}`} className="gelia-pedidos-producto-ok">
                        <CheckCircle2 className="w-4 h-4 shrink-0" style={{ color: 'var(--color-exito)' }} aria-hidden="true" />
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-medium theme-text-main m-0 break-words">{producto.descripcion_producto || producto.sku || 'Producto sin descripción'}</p>
                            {instancia && <span className="text-xs theme-text-muted tabular-nums">Pieza {instancia}</span>}
                        </div>
                        <span className="text-xs font-semibold theme-text-muted shrink-0">Bueno</span>
                    </li>
                );
            })}
        </ul>
    );
    if (!compactos) return lista;
    return (
        <details className="gelia-pedidos-productos-expandibles border theme-border rounded-xl overflow-hidden">
            <summary className="flex items-center justify-between gap-3 px-3 py-3 min-h-[48px] cursor-pointer list-none theme-element">
                <div className="min-w-0">
                    <p className="text-sm font-semibold theme-text-main m-0">{productos.length} productos OK</p>
                    <p className="text-xs theme-text-muted m-0 mt-1 gelia-pedidos-lista-indicacion">
                        <span className="gelia-pedidos-lista-abrir">Ver lista de productos</span>
                        <span className="gelia-pedidos-lista-cerrar">Ocultar lista de productos</span>
                    </p>
                </div>
                <ChevronDown className="gelia-pedidos-lista-chevron w-4 h-4 theme-text-muted shrink-0" aria-hidden="true" />
            </summary>
            <div className="p-3 border-t theme-border">{lista}</div>
        </details>
    );
}
