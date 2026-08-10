import React from 'react';
import { User } from 'lucide-react';
import {
    badgeDepartamentoVendedor,
    nombresDepartamentosVendedor,
} from './pedidosBmaStyles';

/**
 * @param {'completo'|'nombre'|'etiquetas'} variante
 */
export default function BloqueVendedorPedido({ pedido, className = 'mt-1.5', variante = 'completo' }) {
    const vendedor = pedido?.vendedor;
    if (!vendedor?.name && variante !== 'etiquetas') return null;

    const nombresDepto = nombresDepartamentosVendedor(vendedor);
    const badges = nombresDepto.map((n) => badgeDepartamentoVendedor(n)).filter(Boolean);
    const usaPrincipal = Boolean(vendedor?.departamento?.nombre);
    const mostrarNombre = variante === 'completo' || variante === 'nombre';
    const mostrarEtiquetas = variante === 'completo' || variante === 'etiquetas';

    if (mostrarNombre && !vendedor?.name) return null;
    if (mostrarEtiquetas && !mostrarNombre && badges.length === 0) return null;

    return (
        <div className={`flex flex-wrap items-center gap-1.5 ${className}`}>
            {mostrarNombre && (
                <div className="text-[10px] font-bold theme-text-muted uppercase flex items-center gap-1 min-w-0">
                    <User className="w-3 h-3 shrink-0" />
                    <span className="truncate">{vendedor.name}</span>
                </div>
            )}
            {mostrarEtiquetas && badges.map((b) => (
                <span key={b.label} className={b.className} title={usaPrincipal ? 'Departamento principal' : 'Departamento asignado'}>
                    {b.label}
                </span>
            ))}
        </div>
    );
}
