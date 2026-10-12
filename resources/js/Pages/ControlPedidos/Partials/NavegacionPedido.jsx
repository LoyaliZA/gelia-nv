import React from 'react';

/** Saltos dentro del diálogo. Con onSeleccionar cambia de fase sin desmontar los campos. */
export default function NavegacionPedido({ secciones, label = 'Secciones del pedido', activa = null, onSeleccionar = null }) {
    const ir = (event, id) => {
        event.preventDefault();
        if (typeof onSeleccionar === 'function') {
            onSeleccionar(id);
            return;
        }
        const dialog = event.currentTarget.closest('[role="dialog"]');
        const destino = dialog?.querySelector(`[id="${id}"]`);
        if (!destino) return;
        destino.scrollIntoView({ block: 'start', behavior: 'instant' });
        destino.focus({ preventScroll: true });
    };
    return (
        <nav className="gelia-pedidos-navegacion" aria-label={label}>
            {secciones.filter(Boolean).map(({ id, label: texto, cantidad }) => (
                <a
                    key={id}
                    href={`#${id}`}
                    aria-current={activa === id ? 'page' : undefined}
                    onClick={(event) => ir(event, id)}
                >
                    {texto}
                    {cantidad != null && <span className="tabular-nums">{cantidad}</span>}
                </a>
            ))}
        </nav>
    );
}
