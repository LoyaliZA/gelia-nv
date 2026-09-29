import React from 'react';
import { THEME_INPUT, THEME_LABEL } from '@/utils/geliaTheme';
import { formatearDuracionSeg } from '@/utils/estadoPublicidadPdv';

const DIAS = [7, 30, 60, 90];

export default function FormularioPublicidad({
    valor,
    onChange,
    tipo = 'imagen',
    modo = 'edicion',
    duracionVideo = null,
    mostrarAplicarDuracion = false,
}) {
    const set = (campo, siguiente) => onChange({ ...valor, [campo]: siguiente });
    const esVideo = tipo === 'video';
    const muestraDuracionImagen = tipo !== 'video';

    return (
        <div className="grid gap-3 md:grid-cols-2">
            <label className="block">
                <span className={THEME_LABEL}>Alcance</span>
                <select className={THEME_INPUT} value={valor.alcance} onChange={(e) => set('alcance', e.target.value)}>
                    <option value="sucursal">Solo sucursal actual</option>
                    <option value="global">Todas las sucursales</option>
                </select>
            </label>
            {modo === 'alta' ? (
                <label className="block">
                    <span className={THEME_LABEL}>Estado inicial</span>
                    <select className={THEME_INPUT} value={valor.publicacion} onChange={(e) => set('publicacion', e.target.value)}>
                        <option value="ahora">Publicar ahora</option>
                        <option value="programar">Programar</option>
                        <option value="deshabilitada">Guardar deshabilitada</option>
                    </select>
                </label>
            ) : null}
            <label className="block md:col-span-2">
                <span className={THEME_LABEL}>Ajuste visual</span>
                <select className={THEME_INPUT} value={valor.ajuste} onChange={(e) => set('ajuste', e.target.value)}>
                    <option value="cover">Cubrir: llena la pantalla y puede recortar bordes</option>
                    <option value="contain">Contener: muestra el archivo completo y puede dejar márgenes</option>
                </select>
            </label>
            {esVideo || tipo === 'mixto' ? (
                <p className="text-sm theme-text-muted m-0 md:col-span-2">
                    Duración del video: {formatearDuracionSeg(duracionVideo) || 'se tomará del archivo cargado'}. No se puede cambiar desde la programación.
                </p>
            ) : null}
            {muestraDuracionImagen ? (
                <label className="block">
                    <span className={THEME_LABEL}>Duración de imágenes (segundos)</span>
                    <input
                        type="number"
                        min="3"
                        max="300"
                        className={THEME_INPUT}
                        value={valor.duracion_seg}
                        onChange={(e) => set('duracion_seg', e.target.value)}
                    />
                </label>
            ) : null}
            {mostrarAplicarDuracion ? (
                <label className="flex items-center gap-2 text-sm theme-text-main md:col-span-2">
                    <input
                        type="checkbox"
                        checked={Boolean(valor.aplicar_duracion_todas)}
                        onChange={(e) => set('aplicar_duracion_todas', e.target.checked)}
                    />
                    Aplicar esta duración a todas las imágenes
                </label>
            ) : null}
            {modo === 'alta' && valor.publicacion !== 'programar' ? null : (
                <label className="block">
                    <span className={THEME_LABEL}>Inicio</span>
                    <input
                        type="datetime-local"
                        className={THEME_INPUT}
                        value={valor.vigente_desde}
                        onChange={(e) => set('vigente_desde', e.target.value)}
                    />
                </label>
            )}
            <label className="block">
                <span className={THEME_LABEL}>Fin</span>
                <input
                    type="datetime-local"
                    className={THEME_INPUT}
                    value={valor.vigente_hasta}
                    onChange={(e) => set('vigente_hasta', e.target.value)}
                />
            </label>
            <div className="md:col-span-2 space-y-2 rounded-xl border theme-border p-3">
                <label className="flex items-center gap-2 text-sm theme-text-main">
                    <input
                        type="checkbox"
                        checked={Boolean(valor.eliminar_automaticamente)}
                        onChange={(e) => set('eliminar_automaticamente', e.target.checked)}
                    />
                    Eliminar automáticamente después de finalizar
                </label>
                {valor.eliminar_automaticamente ? (
                    <div className="flex flex-wrap gap-2">
                        {DIAS.map((dias) => (
                            <button
                                key={dias}
                                type="button"
                                className={`rounded-full px-3 py-1 text-xs font-bold border theme-border ${Number(valor.conservar_dias) === dias ? 'theme-btn-primary' : 'theme-element theme-text-main'}`}
                                onClick={() => set('conservar_dias', dias)}
                            >
                                {dias} días
                            </button>
                        ))}
                    </div>
                ) : (
                    <p className="text-xs theme-text-muted m-0">Si no se marca, la pieza vencida permanece hasta que alguien la elimine.</p>
                )}
                {valor.eliminar_automaticamente && !valor.vigente_hasta ? (
                    <p className="text-xs text-amber-800 m-0">Indica una fecha de fin para calcular la eliminación.</p>
                ) : null}
            </div>
        </div>
    );
}
