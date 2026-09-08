import React from 'react';
import { Loader2, Radio, RadioTower, ShieldAlert, WifiOff } from 'lucide-react';
import { geliaCardClass } from '@/utils/geliaTheme';
import { reproducirTonoPdv } from '@/utils/pdvAlertasPrefs';
import { mensajeTtsTerminalPdv } from '@/utils/pdvAlertasCatalog';

const ETIQUETAS_ESTADO = {
    no_autorizada: {
        titulo: 'No autorizada',
        detalle: 'Este usuario no puede designar la terminal de alertas de sucursal.',
    },
    disponible: {
        titulo: 'Disponible para activar',
        detalle: 'Pulsa el botón para usar este equipo como terminal de alertas generales.',
    },
    terminal_activa: {
        titulo: 'Terminal activa',
        detalle: 'Este equipo anuncia las novedades generales de la sucursal.',
    },
    otra_terminal_activa: {
        titulo: 'Otra terminal activa',
        detalle: 'Ya hay un equipo designado. Espera a que se libere o venza.',
    },
    conexion_perdida: {
        titulo: 'Conexión perdida',
        detalle: 'No se pudo renovar la designación. Revisa la red o vuelve a activar.',
    },
};

export default function PdvTerminalAlertasSucursal({
    terminal,
    tonosAlertas = [],
    prefsUsuario,
    silencioTerminal = false,
    estadoTts,
    onProbarVoz = null,
}) {
    if (!terminal) return null;

    const {
        estado,
        terminalActiva,
        cargando,
        error,
        activar,
        liberar,
    } = terminal;

    const info = ETIQUETAS_ESTADO[estado] || ETIQUETAS_ESTADO.disponible;
    const puedeActivar = estado === 'disponible' || estado === 'conexion_perdida';
    const puedeLiberar = terminalActiva;

    const probarAudio = () => {
        if (!prefsUsuario?.canales?.sonido || silencioTerminal) return;
        reproducirTonoPdv(prefsUsuario.tono_id, tonosAlertas);
    };

    const probarVoz = () => {
        if (typeof onProbarVoz === 'function') {
            onProbarVoz();
            return;
        }
        const mensaje = mensajeTtsTerminalPdv({
            tipo: 'turno.alta',
            datos: { folio: 'V-0001' },
        });
        if (mensaje && typeof window !== 'undefined' && window.speechSynthesis) {
            const utterance = new SpeechSynthesisUtterance(mensaje);
            utterance.lang = 'es-MX';
            window.speechSynthesis.speak(utterance);
        }
    };

    const icono = ({
        no_autorizada: ShieldAlert,
        disponible: Radio,
        terminal_activa: RadioTower,
        otra_terminal_activa: Radio,
        conexion_perdida: WifiOff,
    })[estado] || Radio;

    const Icono = icono;

    return (
        <section className={geliaCardClass()} data-pdv-terminal-alertas>
            <div className="p-4 space-y-3">
                <div className="flex items-start gap-3">
                    <Icono className="w-5 h-5 shrink-0 mt-0.5" aria-hidden />
                    <div className="min-w-0">
                        <p className="text-sm font-black uppercase tracking-widest m-0">{info.titulo}</p>
                        <p className="text-xs opacity-70 m-0 mt-1 leading-snug">{info.detalle}</p>
                    </div>
                </div>

                {terminalActiva && (
                    <p
                        className="text-xs font-semibold m-0 rounded-xl border px-3 py-2"
                        style={{
                            borderColor: 'color-mix(in srgb, var(--color-primario) 30%, transparent)',
                            backgroundColor: 'color-mix(in srgb, var(--color-primario) 8%, transparent)',
                        }}
                        role="status"
                        data-pdv-terminal-activa
                    >
                        Terminal de alertas activa en este equipo.
                    </p>
                )}

                <div className="flex flex-wrap gap-2">
                    {puedeActivar && (
                        <button
                            type="button"
                            className="px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest theme-element border theme-border min-h-[44px]"
                            disabled={cargando || estado === 'no_autorizada'}
                            onClick={() => activar()}
                        >
                            {cargando ? 'Activando…' : 'Usar este equipo como terminal de alertas'}
                        </button>
                    )}
                    {puedeLiberar && (
                        <button
                            type="button"
                            className="px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest opacity-80 min-h-[44px]"
                            disabled={cargando}
                            onClick={() => liberar('manual')}
                        >
                            Dejar de ser terminal
                        </button>
                    )}
                    {terminalActiva && (
                        <>
                            <button
                                type="button"
                                className="px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest theme-element border theme-border min-h-[44px]"
                                disabled={!prefsUsuario?.canales?.sonido || silencioTerminal}
                                onClick={probarAudio}
                            >
                                Probar timbre
                            </button>
                            <button
                                type="button"
                                className="px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest theme-element border theme-border min-h-[44px]"
                                disabled={!prefsUsuario?.canales?.voz || silencioTerminal || estadoTts === 'bloqueado'}
                                onClick={probarVoz}
                            >
                                Probar voz
                            </button>
                        </>
                    )}
                </div>

                {estadoTts === 'bloqueado' && terminalActiva && (
                    <p className="text-xs opacity-80 m-0" role="status">
                        Audio bloqueado por el navegador. Interactúa con la página; las alertas visuales continúan.
                    </p>
                )}

                {cargando && (
                    <p className="text-xs flex items-center gap-2 m-0" role="status">
                        <Loader2 className="w-3.5 h-3.5 animate-spin" aria-hidden />
                        Actualizando terminal…
                    </p>
                )}

                {error && (
                    <p className="text-xs text-red-500 m-0" role="alert">{error}</p>
                )}
            </div>
        </section>
    );
}
