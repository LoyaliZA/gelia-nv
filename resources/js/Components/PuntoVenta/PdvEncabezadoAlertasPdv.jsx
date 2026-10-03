import React, { useCallback, useState } from 'react';
import { Settings } from 'lucide-react';
import { THEME_BTN_ICON } from '@/utils/geliaTheme';
import { usePdvAlertContext } from '@/Components/PuntoVenta/PdvAlertProvider';
import PdvBarraEstadoSistema from '@/Components/PuntoVenta/PdvBarraEstadoSistema';
import ModalConfiguracionAlertasPdv from '@/Components/PuntoVenta/ModalConfiguracionAlertasPdv';

export default function PdvEncabezadoAlertasPdv({ className = '' }) {
    const ctx = usePdvAlertContext();
    const [modalAbierto, setModalAbierto] = useState(false);
    const [seccionInicial, setSeccionInicial] = useState(null);

    const abrirConfiguracion = useCallback((seccion = null) => {
        setSeccionInicial(seccion);
        setModalAbierto(true);
    }, []);

    const cerrarConfiguracion = useCallback(() => {
        setModalAbierto(false);
        setSeccionInicial(null);
    }, []);

    if (!ctx) return null;

    const {
        prefs,
        terminal,
        terminalGeneral,
        tonosAlertas,
        estadoConexion,
        estadoTts,
        activarPush,
        activandoPush,
        capacidades,
        probarVozTerminal,
    } = ctx;

    const mostrarTerminal = Boolean(capacidades?.alertas_sucursal);
    const mostrarTerminalGeneral = Boolean(capacidades?.alertas_sucursal || capacidades?.atender);

    return (
        <>
            <div
                className={`flex items-center gap-2 ${className}`}
                data-pdv-encabezado-alertas
            >
                <PdvBarraEstadoSistema onAbrirConfiguracion={abrirConfiguracion} />
                <button
                    type="button"
                    className={`${THEME_BTN_ICON} gelia-estado-vivo gelia-estado-vivo--neutro theme-element border theme-border`}
                    onClick={() => abrirConfiguracion()}
                    aria-label="Configurar terminal"
                    title="Configuración del terminal"
                    data-pdv-config-alertas-trigger
                >
                    <Settings className="w-4 h-4 theme-text-primario" aria-hidden />
                </button>
            </div>

            <ModalConfiguracionAlertasPdv
                abierto={modalAbierto}
                onCerrar={cerrarConfiguracion}
                prefs={prefs}
                terminal={terminal}
                terminalGeneral={terminalGeneral}
                mostrarTerminalGeneral={mostrarTerminalGeneral}
                tonosAlertas={tonosAlertas}
                estadoConexion={estadoConexion}
                estadoTts={estadoTts}
                onActivarPush={activarPush}
                activandoPush={activandoPush}
                mostrarTerminal={mostrarTerminal}
                onProbarVoz={probarVozTerminal}
                seccionInicial={seccionInicial}
            />
        </>
    );
}
