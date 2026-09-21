import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { usePage } from '@inertiajs/react';
import { Settings, X } from 'lucide-react';
import PdvPreferenciasAlertasContenido from '@/Components/PuntoVenta/PdvPreferenciasAlertasContenido';
import PdvTerminalAlertasSucursal from '@/Components/PuntoVenta/PdvTerminalAlertasSucursal';
import SelectorSucursalActivaPdv from '@/Components/PuntoVenta/SelectorSucursalActivaPdv';
import ConfiguracionPlazosTurnosPdv from '@/Pages/PuntoVenta/Operacion/Partials/ConfiguracionPlazosTurnosPdv';
import { puedeGestionarPlazosTurnosPdv } from '@/Pages/PuntoVenta/Operacion/Partials/operacionUtils';
import {
    THEME_BTN_ICON,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    deferModalAction,
} from '@/utils/geliaTheme';
import { reportarMensajeOperacion } from '@/utils/geliaToast';

export default function ModalConfiguracionAlertasPdv({
    abierto,
    onCerrar,
    prefs,
    terminal = null,
    tonosAlertas = [],
    estadoConexion,
    estadoTts,
    onActivarPush = null,
    activandoPush = false,
    mostrarTerminal = false,
    onProbarVoz = null,
    seccionInicial = null,
}) {
    const terminalRef = useRef(null);
    const plazosRef = useRef(null);
    const {
        auth,
        capacidades = {},
        sucursal_activa: sucursalActiva = null,
        sucursales_asignadas: sucursalesAsignadas = [],
    } = usePage().props;
    const puedeGestionarPlazos = puedeGestionarPlazosTurnosPdv({ capacidades, auth });

    useEffect(() => {
        if (!abierto) {
            return undefined;
        }

        if (seccionInicial !== 'terminal' && seccionInicial !== 'tiempos') return undefined;

        const id = window.requestAnimationFrame(() => {
            if (seccionInicial === 'terminal') {
                terminalRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            if (seccionInicial === 'tiempos') {
                plazosRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
        return () => window.cancelAnimationFrame(id);
    }, [abierto, seccionInicial]);

    if (!abierto) return null;

    const cerrar = (e) => {
        e?.stopPropagation?.();
        deferModalAction(onCerrar);
    };

    return createPortal(
        <div
            className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4`}
            style={{ zIndex: 'calc(var(--gelia-z-modal) + 15)' }}
            onClick={cerrar}
        >
            <div
                className={`${THEME_MODAL_SHELL} ${puedeGestionarPlazos ? 'max-w-2xl' : 'max-w-lg'} w-full max-h-[min(90vh,720px)] flex flex-col overflow-hidden modal-pop text-left`}
                onClick={(e) => e.stopPropagation()}
                role="dialog"
                aria-modal="true"
                aria-labelledby="pdv-config-alertas-titulo"
                data-pdv-modal-config-alertas
            >
                <div className="flex items-start justify-between gap-3 p-5 md:p-6 border-b theme-border shrink-0">
                    <div className="flex items-start gap-3 min-w-0">
                        <Settings className="w-5 h-5 shrink-0 mt-0.5 theme-text-primario" aria-hidden />
                        <div className="min-w-0">
                            <h2 id="pdv-config-alertas-titulo" className="text-base font-black uppercase theme-text-main m-0">
                                Configuración del terminal
                            </h2>
                            <p className="text-xs theme-text-muted m-0 mt-1">
                                {puedeGestionarPlazos
                                    ? 'Sucursal activa, alertas, terminal de sucursal y tiempos de atención'
                                    : 'Sucursal activa, sonido, voz, notificaciones push y terminal de sucursal'}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        className={`${THEME_BTN_ICON} theme-element border theme-border shrink-0 min-h-[44px] min-w-[44px] flex items-center justify-center`}
                        onClick={cerrar}
                        aria-label="Cerrar configuración de alertas"
                    >
                        <X className="w-5 h-5" aria-hidden />
                    </button>
                </div>

                <div className="gelia-modal-body p-5 md:p-6 overflow-y-auto custom-scrollbar flex-1 min-h-0 space-y-5">
                    <SelectorSucursalActivaPdv
                        sucursalActiva={sucursalActiva}
                        sucursalesAsignadas={sucursalesAsignadas}
                        variante="modal"
                    />

                    {mostrarTerminal && terminal && (
                        <div ref={terminalRef}>
                            <PdvTerminalAlertasSucursal
                                terminal={terminal}
                                tonosAlertas={tonosAlertas}
                                prefsUsuario={prefs?.prefsUsuario}
                                silencioTerminal={prefs?.silencioTerminal}
                                estadoTts={estadoTts}
                                onProbarVoz={onProbarVoz}
                                variante="modal"
                            />
                        </div>
                    )}

                    <PdvPreferenciasAlertasContenido
                        prefs={prefs}
                        tonosAlertas={tonosAlertas}
                        estadoConexion={estadoConexion}
                        estadoTts={estadoTts}
                        onActivarPush={onActivarPush}
                        activandoPush={activandoPush}
                    />

                    {puedeGestionarPlazos && (
                        <div ref={plazosRef} className="pt-2 border-t theme-border">
                            <ConfiguracionPlazosTurnosPdv
                                variante="modal"
                                cargarAlMontar
                                onError={reportarMensajeOperacion}
                            />
                        </div>
                    )}
                </div>
            </div>
        </div>,
        document.body,
    );
}
