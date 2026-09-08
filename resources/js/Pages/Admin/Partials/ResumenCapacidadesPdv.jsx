import React from 'react';
import { CheckCircle2, Circle, MapPin, Store } from 'lucide-react';
import { calcularCapacidadesPdv } from '../../../utils/capacidadesPdv';
import { geliaCardClass } from '../../../utils/geliaTheme';

function FilaCapacidad({ activo, label, nota = null }) {
    const Icono = activo ? CheckCircle2 : Circle;

    return (
        <li className="flex items-start gap-2.5">
            <Icono
                className={`w-4 h-4 shrink-0 mt-0.5 ${activo ? 'text-emerald-600 dark:text-emerald-400' : 'theme-text-muted'}`}
                aria-hidden
            />
            <div className="min-w-0">
                <span className={`text-[11px] font-bold leading-snug ${activo ? 'theme-text-main' : 'theme-text-muted'}`}>
                    {label}
                </span>
                {nota && (
                    <p className="text-[10px] theme-text-muted m-0 mt-0.5 leading-snug">{nota}</p>
                )}
            </div>
        </li>
    );
}

export default function ResumenCapacidadesPdv({
    permisos = [],
    sucursalesCatalogo = [],
    sucursalIds = [],
    sucursalPrincipalId = null,
}) {
    const capacidades = calcularCapacidadesPdv({
        permisos,
        sucursalesCatalogo,
        sucursalIds,
        sucursalPrincipalId,
    });

    return (
        <div className={`${geliaCardClass()} p-4 mb-3 border-[var(--color-primario)]/20`}>
            <header className="flex items-center gap-2 mb-3">
                <Store className="w-4 h-4 shrink-0" style={{ color: 'var(--color-primario)' }} aria-hidden />
                <h5 className="text-[10px] font-black uppercase tracking-widest theme-text-main m-0">
                    Resumen Punto de venta
                </h5>
            </header>

            <div className="space-y-3">
                <div>
                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0 mb-1 flex items-center gap-1">
                        <MapPin className="w-3 h-3" aria-hidden />
                        Sucursales asignadas
                    </p>
                    {capacidades.sucursalesAsignadas.length > 0 ? (
                        <p className="text-[11px] font-semibold theme-text-main m-0 leading-snug">
                            {capacidades.sucursalesAsignadas.join(', ')}
                        </p>
                    ) : (
                        <p className="text-[11px] theme-text-muted m-0 italic">Sin sucursales asignadas</p>
                    )}
                </div>

                <div>
                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0 mb-1">
                        Sucursal principal
                    </p>
                    <p className="text-[11px] font-semibold theme-text-main m-0">
                        {capacidades.sucursalPrincipal ?? '—'}
                    </p>
                </div>

                <ul className="space-y-2 m-0 p-0 list-none border-t theme-border pt-3">
                    <FilaCapacidad
                        activo={capacidades.puedeRegistrarTurnos}
                        label="Puede registrar turnos"
                    />
                    <FilaCapacidad
                        activo={capacidades.puedeAtenderTurnos}
                        label="Puede atender turnos y aparecer como vendedor"
                        nota={capacidades.notaAtenderSinSucursal
                            ? 'Requiere asignación a sucursal para aparecer en gestión de vendedores.'
                            : null}
                    />
                    <FilaCapacidad
                        activo={capacidades.apareceEnGestionVendedores}
                        label="Aparece en gestión de vendedores"
                    />
                    <FilaCapacidad
                        activo={capacidades.puedeGestionarVendedores}
                        label="Puede gestionar vendedores"
                    />
                    <FilaCapacidad
                        activo={capacidades.puedeConsultarReportes}
                        label="Puede consultar reportes"
                    />
                    <FilaCapacidad
                        activo={capacidades.puedeAdministrarExcepciones}
                        label="Puede administrar excepciones"
                    />
                </ul>
            </div>
        </div>
    );
}
