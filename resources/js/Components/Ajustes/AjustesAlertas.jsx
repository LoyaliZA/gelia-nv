import React, { useState } from 'react';
import { Bell, BellRing, ChevronDown, MessageCircle, Mic, Monitor, Volume2 } from 'lucide-react';
import NotificationBrowserService from '@/Services/NotificationBrowserService';
import {
    ALERTAS_TIPOS,
    MENSAJERIA_VOZ_MODOS,
    construirMensajeVozMensajeria,
    resolveTonoPath,
} from '../../utils/alertasPrefs';
import { AjustesBloque, AjustesFila, AjustesInterruptor, AjustesSegmentos } from './AjustesPrimitivas';

export default function AjustesAlertas({ ajustes }) {
    const { estado, tonos, usuario, acciones } = ajustes;
    const { alertas } = estado;
    const [tiposAbiertos, setTiposAbiertos] = useState(false);
    const vozActiva = alertas.canales?.voz !== false;
    const catalogoTonos = tonos.length > 0 ? tonos : [{ id: 'default', nombre: 'Campana clásica' }];

    const probarVozMensajeria = () => {
        const demo = construirMensajeVozMensajeria(
            { user: { name: 'María' }, contenido: 'Hola, ¿cómo estás?', tipo: 'texto' },
            alertas,
            usuario?.name
        );
        if (demo) NotificationBrowserService.speakText(demo, true, { immediate: true });
    };

    return (
        <div className="gelia-ajustes__panel">
            <AjustesBloque icon={BellRing} title="Alertas_" hint="Canales de notificación, tonos y tipos de aviso">
                <div className="gelia-ajustes__lista">
                    <AjustesFila icon={Volume2} title="Timbres de alerta" subtitle="Reproducir sonido al recibir alertas" inline>
                        <AjustesInterruptor activo={alertas.canales?.sonido} onChange={() => acciones.alternarCanal('sonido')} label="Timbres de alerta" />
                    </AjustesFila>

                    <AjustesFila icon={Mic} title="Texto a voz" subtitle="Control maestro de la lectura en voz alta (operativas y mensajería)" inline>
                        <AjustesInterruptor activo={alertas.canales?.voz} onChange={() => acciones.alternarCanal('voz')} label="Texto a voz" />
                    </AjustesFila>

                    <AjustesFila icon={MessageCircle} title="Voz en mensajería interna" subtitle="Modo de anuncio en chat; requiere «Texto a voz»">
                        <div className="gelia-ajustes__control-doble">
                            <div className={vozActiva ? '' : 'gelia-ajustes__inactivo'}>
                                <AjustesSegmentos
                                    nombre="Voz en mensajería interna"
                                    valor={alertas.mensajeria_voz}
                                    onChange={(modo) => vozActiva && acciones.actualizarAlertas((prev) => ({ ...prev, mensajeria_voz: modo }))}
                                    opciones={Object.entries(MENSAJERIA_VOZ_MODOS)}
                                />
                            </div>
                            <button
                                type="button"
                                className="gelia-ajustes__btn"
                                onClick={probarVozMensajeria}
                                disabled={alertas.mensajeria_voz === 'desactivado' || !vozActiva}
                            >
                                Probar voz
                            </button>
                        </div>
                    </AjustesFila>

                    <AjustesFila icon={Monitor} title="Notificaciones de escritorio" subtitle="Alertas nativas del sistema operativo" inline>
                        <AjustesInterruptor activo={alertas.canales?.escritorio} onChange={() => acciones.alternarCanal('escritorio')} label="Notificaciones de escritorio" />
                    </AjustesFila>

                    <AjustesFila icon={Bell} title="Notificaciones GELIA" subtitle="Avisos flotantes dentro de la aplicación" inline>
                        <AjustesInterruptor activo={alertas.canales?.app} onChange={() => acciones.alternarCanal('app')} label="Notificaciones GELIA" />
                    </AjustesFila>

                    <AjustesFila icon={Volume2} title="Tono de notificación" subtitle="Sonido al recibir una alerta">
                        <div className="gelia-ajustes__control-doble">
                            <select
                                value={alertas.tono_id}
                                onChange={(e) => acciones.actualizarAlertas((prev) => ({ ...prev, tono_id: e.target.value }))}
                                className="gelia-ajustes__select"
                                aria-label="Tono de notificación"
                            >
                                {catalogoTonos.map((tono) => (
                                    <option key={tono.id} value={tono.id}>{tono.nombre}</option>
                                ))}
                            </select>
                            <button
                                type="button"
                                className="gelia-ajustes__btn"
                                onClick={() => NotificationBrowserService.previewTone(resolveTonoPath(tonos, alertas.tono_id))}
                            >
                                Probar tono
                            </button>
                        </div>
                    </AjustesFila>
                </div>
            </AjustesBloque>

            <section className="gelia-ajustes__acordeon">
                <button
                    type="button"
                    className="gelia-ajustes__acordeon-btn"
                    aria-expanded={tiposAbiertos}
                    aria-controls="ajustes-tipos-alerta"
                    onClick={() => setTiposAbiertos((v) => !v)}
                >
                    <span className="min-w-0">
                        <span className="gelia-ajustes__fila-title">Tipos de alerta a recibir</span>
                        <span className="gelia-ajustes__fila-sub">
                            Desactivar un tipo silencia sonido, voz, escritorio y avisos. El historial del Centro de Alertas se conserva.
                        </span>
                    </span>
                    <ChevronDown className={`gelia-ajustes__chevron ${tiposAbiertos ? 'gelia-ajustes__chevron--abierto' : ''}`} aria-hidden />
                </button>
                {tiposAbiertos && (
                    <div id="ajustes-tipos-alerta" className="gelia-ajustes__tipos">
                        {Object.entries(ALERTAS_TIPOS).map(([tipo, etiqueta]) => (
                            <div key={tipo} className="gelia-ajustes__tipo">
                                <span>{etiqueta}</span>
                                <AjustesInterruptor activo={alertas.tipos?.[tipo] !== false} onChange={() => acciones.alternarTipo(tipo)} label={etiqueta} />
                            </div>
                        ))}
                    </div>
                )}
            </section>
        </div>
    );
}
