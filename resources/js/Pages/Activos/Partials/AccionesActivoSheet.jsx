import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { X, UserPlus, UserMinus, ArrowRightLeft, Wrench, Ban, Edit2 } from 'lucide-react';

const EXIT_MS = 260;

export default function AccionesActivoSheet({
    abierto,
    onCerrar,
    activo,
    can,
    onEditar,
    onAsignar,
    onDevolver,
    onTransferir,
    onMantenimiento,
    onBaja,
}) {
    const [render, setRender] = useState(false);
    const [exiting, setExiting] = useState(false);

    useEffect(() => {
        if (abierto) {
            setRender(true);
            setExiting(false);
            document.body.style.overflow = 'hidden';
            return undefined;
        }

        if (render) {
            setExiting(true);
            const timer = window.setTimeout(() => {
                setRender(false);
                setExiting(false);
                document.body.style.overflow = '';
            }, EXIT_MS);
            return () => window.clearTimeout(timer);
        }

        return undefined;
    }, [abierto, render]);

    useEffect(() => () => {
        document.body.style.overflow = '';
    }, []);

    if (!render || !activo) return null;

    const acciones = [
        can('activos.editar') && activo.estado !== 'baja' && {
            key: 'editar',
            label: 'Editar',
            icon: Edit2,
            onClick: () => { onCerrar(); onEditar(); },
        },
        can('activos.asignar') && activo.estado !== 'baja' && activo.estado !== 'mantenimiento' && {
            key: 'asignar',
            label: activo.responsable ? 'Reasignar' : 'Asignar',
            icon: UserPlus,
            onClick: () => { onCerrar(); onAsignar(); },
            primary: true,
        },
        can('activos.asignar') && activo.estado === 'asignado' && {
            key: 'devolver',
            label: 'Devolver',
            icon: UserMinus,
            onClick: () => { onCerrar(); onDevolver(); },
        },
        can('activos.transferir') && activo.estado !== 'baja' && {
            key: 'transferir',
            label: 'Transferir',
            icon: ArrowRightLeft,
            onClick: () => { onCerrar(); onTransferir(); },
        },
        can('activos.cambiar_estado') && activo.estado !== 'baja' && activo.estado !== 'mantenimiento' && {
            key: 'mantenimiento',
            label: 'Mantenimiento',
            icon: Wrench,
            onClick: () => { onCerrar(); onMantenimiento(); },
        },
        can('activos.cambiar_estado') && activo.estado !== 'baja' && {
            key: 'baja',
            label: 'Dar de baja',
            icon: Ban,
            onClick: () => { onCerrar(); onBaja(); },
            danger: true,
        },
    ].filter(Boolean);

    const overlayClass = `activos-sheet-overlay ${exiting ? 'activos-sheet-overlay--exit' : 'activos-sheet-overlay--enter'}`;
    const panelClass = `activos-sheet-panel ${exiting ? 'activos-sheet-panel--exit' : 'activos-sheet-panel--enter'}`;

    return createPortal(
        <div
            className={overlayClass}
            onClick={onCerrar}
            role="dialog"
            aria-modal="true"
            aria-label="Acciones del activo"
        >
            <div className={panelClass} onClick={(e) => e.stopPropagation()}>
                <div className="flex items-center justify-between mb-4 gap-3">
                    <div className="min-w-0">
                        <p className="text-[10px] font-mono font-bold theme-text-muted m-0 truncate">{activo.folio}</p>
                        <h3 className="text-sm font-black uppercase theme-text-main m-0 truncate">{activo.nombre}</h3>
                    </div>
                    <button
                        type="button"
                        onClick={onCerrar}
                        className="shrink-0 p-2.5 rounded-full theme-element border theme-border"
                        aria-label="Cerrar opciones"
                    >
                        <X className="w-4 h-4" />
                    </button>
                </div>
                <div className="grid grid-cols-1 gap-2">
                    {acciones.map((accion) => {
                        const Icon = accion.icon;
                        const btnClass = [
                            'activos-sheet-action',
                            accion.danger ? 'activos-sheet-action--danger' : '',
                            accion.primary && !accion.danger ? 'activos-sheet-action--primary' : '',
                        ].filter(Boolean).join(' ');

                        return (
                            <button
                                key={accion.key}
                                type="button"
                                onClick={accion.onClick}
                                className={btnClass}
                            >
                                <Icon className="w-4 h-4 shrink-0" />
                                {accion.label}
                            </button>
                        );
                    })}
                </div>
            </div>
        </div>,
        document.body
    );
}
