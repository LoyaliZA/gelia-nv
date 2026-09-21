import React, { useState } from 'react';
import { Bell, ChevronDown, ChevronUp } from 'lucide-react';
import { geliaCardClass, GELIA_ICON_BOX } from '@/utils/geliaTheme';
import PdvPreferenciasAlertasContenido from '@/Components/PuntoVenta/PdvPreferenciasAlertasContenido';

export default function PdvPreferenciasAlertas({
    prefs,
    tonosAlertas = [],
    estadoConexion,
    estadoTts,
    onActivarPush = null,
    activandoPush = false,
}) {
    const [abierto, setAbierto] = useState(false);

    if (!prefs) return null;

    return (
        <section className={geliaCardClass()} data-pdv-preferencias-alertas>
            <button
                type="button"
                className="w-full flex items-center justify-between gap-3 px-4 py-3 text-left"
                onClick={() => setAbierto((v) => !v)}
                aria-expanded={abierto}
            >
                <div className="flex items-center gap-3 min-w-0">
                    <span className={`${GELIA_ICON_BOX} !min-h-[36px] !min-w-[36px] !p-2`} aria-hidden>
                        <Bell className="w-4 h-4 theme-text-primario" />
                    </span>
                    <div className="min-w-0">
                        <p className="text-sm font-black uppercase tracking-widest m-0">Alertas del terminal</p>
                        <p className="text-xs theme-text-muted m-0 mt-0.5">
                            Sonido, voz y notificaciones push de punto de venta
                        </p>
                    </div>
                </div>
                <span className={`${GELIA_ICON_BOX} !min-h-[32px] !min-w-[32px] !p-1.5 shrink-0`} aria-hidden>
                    {abierto ? <ChevronUp className="w-3.5 h-3.5" /> : <ChevronDown className="w-3.5 h-3.5" />}
                </span>
            </button>

            {abierto && (
                <div className="px-4 pb-4 border-t theme-border">
                    <PdvPreferenciasAlertasContenido
                        prefs={prefs}
                        tonosAlertas={tonosAlertas}
                        estadoConexion={estadoConexion}
                        estadoTts={estadoTts}
                        onActivarPush={onActivarPush}
                        activandoPush={activandoPush}
                    />
                </div>
            )}
        </section>
    );
}
