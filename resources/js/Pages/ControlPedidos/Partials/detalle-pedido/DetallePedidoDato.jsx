import React from 'react';

export function DetallePedidoDatos({ children }) {
    return <dl className="gelia-pedidos-datos">{children}</dl>;
}

export function DetallePedidoResumen({ hechos = [] }) {
    const visibles = hechos.filter((hecho) => hecho && hecho.value != null && hecho.value !== '');
    if (!visibles.length) return null;
    return (
        <dl className="gelia-pedidos-resumen">
            {visibles.map((hecho) => {
                const Icono = hecho.icon;
                return (
                    <div key={hecho.label} className="gelia-pedidos-hecho">
                        <span className="gelia-pedidos-hecho-barra" aria-hidden="true" />
                        <div className="min-w-0">
                            <dt>
                                {Icono ? <Icono className="gelia-pedidos-dato-icono" aria-hidden="true" /> : null}
                                {hecho.label}
                            </dt>
                            <dd>{hecho.value}</dd>
                        </div>
                    </div>
                );
            })}
        </dl>
    );
}

export default function DetallePedidoDato({ icon: Icono, label, value, obligatorio = false }) {
    const texto = value == null || value === '' ? null : String(value);
    const vacio = texto == null || texto === '—';
    if (vacio && !obligatorio) return null;
    return (
        <div className="gelia-pedidos-dato">
            <dt>
                {Icono ? <Icono className="gelia-pedidos-dato-icono" aria-hidden="true" /> : null}
                {label}
            </dt>
            <dd>{vacio ? '—' : texto}</dd>
        </div>
    );
}
