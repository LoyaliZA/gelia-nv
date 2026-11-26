import React from 'react';
import { Droplet, Image as ImageIcon, Layers, Moon, PaintBucket, Palette, Sun, Trash2, Type, Upload } from 'lucide-react';
import { formatFontScaleLabel, FONT_SCALE_MIN, FONT_SCALE_MAX } from '../../utils/fontScale';
import {
    formatContentMaxLabel,
    formatContentPaddingLabel,
    CONTENT_MAX_REM_MIN,
    CONTENT_MAX_REM_MAX,
    CONTENT_PADDING_REM_MIN,
    CONTENT_PADDING_REM_MAX,
} from '../../utils/contentDensity';
import { FONDO_SISTEMA, etiquetaTipoFondo } from '../../utils/fondoPantalla';
import { ACENTOS, FUENTES_OPCIONES } from './ajustesTema';
import { AjustesBloque, AjustesFila, AjustesInterruptor, AjustesPaso, AjustesSegmentos } from './AjustesPrimitivas';

const PRESETS_RESPALDO = [
    { name: 'Gelia Signature', modo: 'dark', colorHex: '#ec4899', colorNombre: 'rosa', bg: 'blob', font: 'montserrat', escala: 1, glass: true, layout: 'professional_left', sound: true },
    { name: 'GELIA Oasis', modo: 'light', colorHex: '#10b981', colorNombre: 'verde', bg: 'stacked', font: 'poppins', escala: 1, glass: false, layout: 'professional_left', sound: true },
    { name: 'CyberTech', modo: 'dark', colorHex: '#3b82f6', colorNombre: 'azul', bg: 'polygon', font: 'mono', escala: 1, glass: false, layout: 'professional_left', sound: true },
];

const FONDOS_RESPALDO = ['blob', 'blobscene', 'circle', 'layered', 'peaks', 'polygon', 'square', 'stacked', 'steps', 'wave'];

export default function AjustesApariencia({ ajustes }) {
    const { estado, presets, fondos, acciones, bgInputRef } = ajustes;
    const listaPresets = presets.length > 0 ? presets : PRESETS_RESPALDO;
    const listaFondos = fondos.length > 0
        ? fondos
        : FONDOS_RESPALDO.map((slug) => ({
            id: slug,
            slug,
            nombre: slug.charAt(0).toUpperCase() + slug.slice(1),
            tipo: 'vector',
            valor: slug,
            preview: `/assets/backgrounds/${slug}_movil.svg`,
        }));

    return (
        <div className="gelia-ajustes__panel">
            <AjustesBloque icon={Layers} title="Temas predefinidos_" hint="Color, modo, fuente y fondo de un solo clic">
                <div className="gelia-ajustes__presets">
                    {listaPresets.map((preset, idx) => (
                        <button
                            key={preset.id || preset.slug || idx}
                            type="button"
                            className="gelia-ajustes__preset"
                            onClick={() => acciones.aplicarPreset(preset)}
                            style={{ '--preset-color': preset.colorHex }}
                        >
                            <span className="gelia-ajustes__preset-dot" aria-hidden />
                            <span className="min-w-0">
                                <span className="gelia-ajustes__preset-name">{preset.name}</span>
                                <span className="gelia-ajustes__preset-mode">
                                    {preset.modo === 'dark' ? <Moon className="w-3 h-3" aria-hidden /> : <Sun className="w-3 h-3" aria-hidden />}
                                    {preset.modo === 'dark' ? 'Oscuro' : 'Claro'}
                                </span>
                            </span>
                        </button>
                    ))}
                </div>
            </AjustesBloque>

            <AjustesBloque icon={Palette} title="Interfaz y colores_" hint="Modo, énfasis, tipografía y transparencia">
                <div className="gelia-ajustes__lista">
                    <AjustesFila icon={estado.dark ? Moon : Sun} title="Modo de aplicación" subtitle="Esquema claro u oscuro">
                        <AjustesSegmentos
                            nombre="Modo de aplicación"
                            valor={estado.dark ? 'dark' : 'light'}
                            onChange={acciones.cambiarModo}
                            opciones={[['light', 'Claro', Sun], ['dark', 'Oscuro', Moon]]}
                        />
                    </AjustesFila>

                    <AjustesFila icon={PaintBucket} title="Color de énfasis" subtitle="Color primario del sistema">
                        <div className="gelia-ajustes__colores" role="radiogroup" aria-label="Color de énfasis">
                            {Object.keys(ACENTOS).map((nombre) => (
                                <button
                                    key={nombre}
                                    type="button"
                                    role="radio"
                                    aria-checked={estado.color === nombre}
                                    aria-label={nombre}
                                    title={nombre}
                                    className="gelia-ajustes__color"
                                    data-active={estado.color === nombre}
                                    onClick={() => acciones.cambiarColor(nombre)}
                                />
                            ))}
                            <label className="gelia-ajustes__color gelia-ajustes__color--custom" title="Elegir color personalizado">
                                <Palette className="w-4 h-4" aria-hidden />
                                <input
                                    type="color"
                                    aria-label="Color personalizado"
                                    onChange={(e) => acciones.cambiarColor(e.target.value)}
                                />
                            </label>
                        </div>
                    </AjustesFila>

                    <AjustesFila icon={Droplet} title="Transparencia de paneles" subtitle="Aplica translucidez a tarjetas y paneles" inline>
                        <AjustesInterruptor activo={estado.glass} onChange={acciones.cambiarCristal} label="Transparencia de paneles" />
                    </AjustesFila>

                    <AjustesFila icon={Type} title="Fuente del sistema" subtitle="Tipografía principal de la interfaz">
                        <select
                            value={estado.font}
                            onChange={(e) => acciones.cambiarFuente(e.target.value)}
                            className="gelia-ajustes__select"
                            aria-label="Fuente del sistema"
                        >
                            {FUENTES_OPCIONES.map(([valor, etiqueta]) => (
                                <option key={valor} value={valor}>{etiqueta}</option>
                            ))}
                        </select>
                    </AjustesFila>

                    <AjustesFila
                        icon={Type}
                        title="Tamaño de letra"
                        subtitle={`${formatFontScaleLabel(FONT_SCALE_MIN)} – ${formatFontScaleLabel(FONT_SCALE_MAX)}`}
                    >
                        <AjustesPaso
                            nombre="Tamaño de letra"
                            etiqueta={formatFontScaleLabel(estado.scale)}
                            onMenos={() => acciones.pasoEscala(-1)}
                            onMas={() => acciones.pasoEscala(1)}
                            deshabilitarMenos={estado.scale <= FONT_SCALE_MIN}
                            deshabilitarMas={estado.scale >= FONT_SCALE_MAX}
                        />
                    </AjustesFila>

                    <AjustesFila icon={Layers} title="Abarcamiento de datos" subtitle="Compacto centrado · Completo a pantalla · Personalizado">
                        <AjustesSegmentos
                            nombre="Abarcamiento de datos"
                            valor={estado.contentDensity}
                            onChange={acciones.cambiarDensidad}
                            opciones={[['compacto', 'Compacto'], ['completo', 'Completo'], ['personalizado', 'Personalizado']]}
                        />
                    </AjustesFila>

                    {estado.contentDensity === 'personalizado' && (
                        <>
                            <AjustesFila
                                icon={Layers}
                                title="Ancho máximo"
                                subtitle={`${formatContentMaxLabel(CONTENT_MAX_REM_MIN)} – ${formatContentMaxLabel(CONTENT_MAX_REM_MAX)}`}
                            >
                                <AjustesPaso
                                    nombre="Ancho máximo"
                                    etiqueta={formatContentMaxLabel(estado.contentMaxRem)}
                                    onMenos={() => acciones.pasoAncho(-1)}
                                    onMas={() => acciones.pasoAncho(1)}
                                    deshabilitarMenos={estado.contentMaxRem <= CONTENT_MAX_REM_MIN}
                                    deshabilitarMas={estado.contentMaxRem >= CONTENT_MAX_REM_MAX}
                                />
                            </AjustesFila>
                            <AjustesFila
                                icon={Layers}
                                title="Margen lateral"
                                subtitle={`${formatContentPaddingLabel(CONTENT_PADDING_REM_MIN)} – ${formatContentPaddingLabel(CONTENT_PADDING_REM_MAX)}`}
                            >
                                <AjustesPaso
                                    nombre="Margen lateral"
                                    etiqueta={formatContentPaddingLabel(estado.contentPaddingRem)}
                                    onMenos={() => acciones.pasoMargen(-1)}
                                    onMas={() => acciones.pasoMargen(1)}
                                    deshabilitarMenos={estado.contentPaddingRem <= CONTENT_PADDING_REM_MIN}
                                    deshabilitarMas={estado.contentPaddingRem >= CONTENT_PADDING_REM_MAX}
                                />
                            </AjustesFila>
                        </>
                    )}
                </div>
            </AjustesBloque>

            <AjustesBloque icon={ImageIcon} title="Fondo de pantalla_" hint={`Actual: ${etiquetaTipoFondo(estado.bg)}`}>
                <div className="gelia-ajustes__fondos" role="radiogroup" aria-label="Fondo de pantalla">
                    <button
                        type="button"
                        role="radio"
                        aria-checked={estado.bg === FONDO_SISTEMA}
                        className="gelia-ajustes__fondo gelia-ajustes__fondo--sistema"
                        data-active={estado.bg === FONDO_SISTEMA}
                        onClick={() => acciones.cambiarFondo(FONDO_SISTEMA)}
                        title="Fondo del sistema"
                    >
                        <Sun className="w-4 h-4" aria-hidden />
                        <span>Claro / oscuro</span>
                    </button>
                    {listaFondos.map((fondo) => {
                        const valor = fondo.valor;
                        const vista = fondo.preview || (fondo.tipo === 'vector' ? `/assets/backgrounds/${fondo.valor}_movil.svg` : fondo.valor);
                        return (
                            <button
                                key={fondo.id || fondo.slug}
                                type="button"
                                role="radio"
                                aria-checked={estado.bg === valor}
                                aria-label={fondo.nombre}
                                className="gelia-ajustes__fondo"
                                data-active={estado.bg === valor}
                                onClick={() => acciones.cambiarFondo(valor)}
                                title={fondo.nombre}
                            >
                                <img src={vista} alt="" loading="lazy" />
                            </button>
                        );
                    })}
                </div>

                <div className="gelia-ajustes__subida">
                    <input
                        ref={bgInputRef}
                        type="file"
                        className="hidden"
                        accept="image/jpeg,image/png,image/jpg,image/webp"
                        onChange={acciones.subirFondo}
                    />
                    <button type="button" className="gelia-ajustes__btn" onClick={() => bgInputRef.current?.click()} disabled={ajustes.comprimiendo}>
                        <Upload className="w-4 h-4" aria-hidden />
                        {ajustes.comprimiendo ? 'Optimizando imagen…' : 'Subir imagen propia'}
                    </button>
                    <button type="button" className="gelia-ajustes__btn gelia-ajustes__btn--peligro" onClick={acciones.quitarFondoPropio}>
                        <Trash2 className="w-4 h-4" aria-hidden />
                        Usar fondo del sistema
                    </button>
                    <p className="gelia-ajustes__nota">JPG, PNG o WebP · se optimiza antes de guardarse.</p>
                </div>
            </AjustesBloque>
        </div>
    );
}
