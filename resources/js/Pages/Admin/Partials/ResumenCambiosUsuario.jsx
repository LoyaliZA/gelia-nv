import React, { useState } from 'react';

function cuenta(cantidad, uno, varios) {
    return cantidad === 1 ? `1 ${uno}` : `${cantidad} ${varios}`;
}

export default function ResumenCambiosUsuario({
    resumen,
    titulo = 'Cambios pendientes',
}) {
    const [abierto, setAbierto] = useState(false);
    if (!resumen || resumen.total < 1) return null;

    const { contadores } = resumen;
    const piezas = [
        contadores.datos > 0 ? cuenta(contadores.datos, 'dato', 'datos') : null,
        contadores.roles > 0 ? cuenta(contadores.roles, 'rol o plantilla', 'roles o plantillas') : null,
        contadores.permisosAlta > 0 ? cuenta(contadores.permisosAlta, 'permiso añadido', 'permisos añadidos') : null,
        contadores.permisosBaja > 0 ? cuenta(contadores.permisosBaja, 'permiso retirado', 'permisos retirados') : null,
        contadores.organizacion > 0 ? cuenta(contadores.organizacion, 'cambio de organización', 'cambios de organización') : null,
    ].filter(Boolean);

    return (
        <section className="usuarios-cambios" aria-label={titulo}>
            <div className="flex items-start justify-between gap-3 min-w-0">
                <div className="min-w-0">
                    <h3 className="text-sm font-semibold theme-text-main m-0">{titulo}</h3>
                    <ul className="usuarios-cambios-contadores">
                        {piezas.map((pieza) => <li key={pieza}>{pieza}</li>)}
                    </ul>
                </div>
                <button
                    type="button"
                    className="theme-btn-secondary shrink-0"
                    aria-expanded={abierto}
                    onClick={() => setAbierto((prev) => !prev)}
                >
                    {abierto ? 'Ocultar detalle' : 'Ver detalle'}
                </button>
            </div>
            {abierto && (
                <div className="usuarios-cambios-detalle">
                    {resumen.grupos.map((grupo) => (
                        <section key={grupo.id}>
                            <h4 className="text-sm font-semibold theme-text-main m-0">{grupo.label}</h4>
                            <ul>
                                {grupo.cambios.map((cambio) => (
                                    <li
                                        key={cambio.id}
                                        className={cambio.tipo === 'alta' ? 'usuarios-cambio-alta' : cambio.tipo === 'baja' ? 'usuarios-cambio-baja' : undefined}
                                        title={cambio.clave || undefined}
                                    >
                                        <span className="theme-text-main">{cambio.etiqueta}</span>
                                        {cambio.tipo === 'valor' && (
                                            <span className="theme-text-muted">Antes: {cambio.antes}. Ahora: {cambio.despues}.</span>
                                        )}
                                        {cambio.tipo === 'alta' && <span>Añadido</span>}
                                        {cambio.tipo === 'baja' && <span>Retirado</span>}
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}
                </div>
            )}
        </section>
    );
}
