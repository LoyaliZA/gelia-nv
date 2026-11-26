import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';
import { usePage } from '@inertiajs/react';
import NotificationBrowserService from '@/Services/NotificationBrowserService';
import {
    readStoredAlertasPrefs,
    persistAlertasPrefsToStorage,
    mergeAlertasPrefs,
} from '../../utils/alertasPrefs';
import {
    clampFontScale,
    applyFontScaleToRoot,
    FONT_SCALE_STEP,
    FONT_SCALE_STORAGE_KEY,
} from '../../utils/fontScale';
import {
    clampContentMaxRem,
    clampContentPaddingRem,
    normalizeDensityMode,
    applyContentDensityToRoot,
    persistContentDensityToStorage,
    CONTENT_MAX_REM_STEP,
    CONTENT_PADDING_REM_STEP,
} from '../../utils/contentDensity';
import { compressImageToWebp, validateImageSource } from '../../utils/compressImage';
import { isProfessionalSidebarLayout } from '../../config/sidebarLayouts';
import { FONDO_SISTEMA, applyFondoPantalla, normalizeFondoBase } from '../../utils/fondoPantalla';
import {
    FUENTES,
    aplicarTemaAlDocumento,
    capturarInstantaneaTema,
    construirTemaVisual,
    huellaAjustes,
    leerTemaAlmacenado,
    resolverAcento,
    restaurarInstantaneaTema,
} from './ajustesTema';

const MAX_BG_PHOTO_BYTES = 5120 * 1024;

function emitir(nombre, detail) {
    window.dispatchEvent(detail === undefined ? new Event(nombre) : new CustomEvent(nombre, { detail }));
}

/**
 * Estado y acciones del modal de Ajustes.
 * Todo cambio se previsualiza al instante (localStorage + :root); solo «Guardar» lo lleva a la cuenta.
 * Al cerrar sin guardar, `descartar()` devuelve la interfaz al estado con el que se abrió.
 */
export default function useAjustes() {
    const { auth, tonos_alertas: tonosAlertas = [], catalogo_temas: catalogoTemas = [], catalogo_fondos: catalogoFondos = [] } = usePage().props;
    const temaGuardado = auth?.tema_visual || {};
    const usuario = auth?.user || {};

    const origen = useRef(null);
    if (origen.current === null) {
        origen.current = {
            tema: leerTemaAlmacenado(temaGuardado),
            alertas: readStoredAlertasPrefs(temaGuardado),
            instantanea: capturarInstantaneaTema(),
        };
    }
    const inicial = origen.current.tema;

    const bgInputRef = useRef(null);

    const [color, setColor] = useState(inicial.color);
    const [dark, setDark] = useState(inicial.dark);
    const [bg, setBg] = useState(inicial.bg);
    const [font, setFont] = useState(inicial.font);
    const [scale, setScale] = useState(inicial.scale);
    const [glass, setGlass] = useState(inicial.glass);
    const [layout, setLayout] = useState(inicial.layout);
    const [mobileLayout, setMobileLayout] = useState(inicial.mobileLayout);
    const [sidebarMode, setSidebarMode] = useState(inicial.sidebarMode);
    const [fixedPosition, setFixedPosition] = useState(inicial.fixedPosition);
    const [contentDensity, setContentDensity] = useState(inicial.contentDensity);
    const [contentMaxRem, setContentMaxRem] = useState(inicial.contentMaxRem);
    const [contentPaddingRem, setContentPaddingRem] = useState(inicial.contentPaddingRem);
    const [alertas, setAlertas] = useState(origen.current.alertas);
    const [comprimiendo, setComprimiendo] = useState(false);
    const [aviso, setAviso] = useState(null); // { tipo: 'error' | 'exito', texto }

    // Imagen de fondo pendiente de subir (o «volver al fondo del sistema»); se envía al guardar.
    const [fondoPendiente, setFondoPendiente] = useState({ archivo: null, quitar: false });
    const [estadoGuardado, setEstadoGuardado] = useState('idle'); // idle | guardando | guardado
    const temporizadorRef = useRef(null);
    useEffect(() => () => clearTimeout(temporizadorRef.current), []);

    const estado = useMemo(() => ({
        color, dark, bg, font, scale, glass, layout, mobileLayout, sidebarMode, fixedPosition,
        contentDensity, contentMaxRem, contentPaddingRem, alertas,
    }), [color, dark, bg, font, scale, glass, layout, mobileLayout, sidebarMode, fixedPosition, contentDensity, contentMaxRem, contentPaddingRem, alertas]);

    const huellaBase = useRef(null);
    if (huellaBase.current === null) huellaBase.current = huellaAjustes(estado);
    const hayArchivoPendiente = Boolean(fondoPendiente.archivo) || fondoPendiente.quitar;
    const sinGuardar = huellaAjustes(estado) !== huellaBase.current || hayArchivoPendiente;
    const sinGuardarRef = useRef(false);
    sinGuardarRef.current = sinGuardar;

    // --- Alertas: se aplican al instante (voz, tono, toasts) y se guardan con el resto ---
    const actualizarAlertas = useCallback((cambio) => {
        setAlertas((prev) => {
            const siguiente = typeof cambio === 'function' ? cambio(prev) : cambio;
            persistAlertasPrefsToStorage(siguiente);
            NotificationBrowserService.setPreferences(siguiente);
            return siguiente;
        });
    }, []);

    useEffect(() => {
        NotificationBrowserService.setTonosCatalog(tonosAlertas);
        NotificationBrowserService.setPreferences(alertas);
    }, [tonosAlertas, alertas]);

    const alternarCanal = (canal) => actualizarAlertas((prev) => {
        const activo = prev.canales?.[canal] !== false;
        const canales = { ...prev.canales, [canal]: !activo };
        const siguiente = { ...prev, canales };
        if (canal === 'voz' && canales.voz === false) siguiente.mensajeria_voz = 'desactivado';
        return siguiente;
    });

    const alternarTipo = (tipo) => actualizarAlertas((prev) => ({
        ...prev,
        tipos: { ...prev.tipos, [tipo]: prev.tipos?.[tipo] === false },
    }));

    // --- Apariencia: vista previa inmediata ---
    const cambiarModo = (modo) => {
        const oscuro = modo === 'dark';
        setDark(oscuro);
        localStorage.setItem('theme', oscuro ? 'dark' : 'light');
        emitir('theme-changed');
    };

    const cambiarColor = (valor) => {
        setColor(valor);
        document.documentElement.style.setProperty('--color-primario', resolverAcento(valor));
        localStorage.setItem('theme_color', valor);
        emitir('theme-changed');
    };

    const cambiarFuente = (valor) => {
        setFont(valor);
        document.documentElement.style.setProperty('--font-principal', FUENTES[valor] || FUENTES.inter);
        localStorage.setItem('theme_font', valor);
        emitir('theme-changed');
    };

    const cambiarCristal = (activo) => {
        setGlass(activo);
        document.documentElement.classList.toggle('glass-active', activo);
        localStorage.setItem('theme_glass', String(activo));
        emitir('theme-changed');
    };

    const pasoEscala = (direccion) => {
        const siguiente = clampFontScale(scale + direccion * FONT_SCALE_STEP);
        setScale(siguiente);
        applyFontScaleToRoot(siguiente);
        localStorage.setItem(FONT_SCALE_STORAGE_KEY, String(siguiente));
        emitir('theme-changed');
    };

    const previsualizarDensidad = (modo, maxRem, paddingRem) => {
        persistContentDensityToStorage({ modo, contenidoMaxRem: maxRem, contenidoPaddingRem: paddingRem });
        applyContentDensityToRoot({
            densidad_contenido: modo,
            contenido_max_rem: maxRem,
            contenido_padding_rem: paddingRem,
        });
        emitir('theme-changed');
    };

    const cambiarDensidad = (modo) => {
        const normalizado = normalizeDensityMode(modo);
        setContentDensity(normalizado);
        previsualizarDensidad(normalizado, contentMaxRem, contentPaddingRem);
    };

    const pasoAncho = (direccion) => {
        const siguiente = clampContentMaxRem(contentMaxRem + direccion * CONTENT_MAX_REM_STEP);
        setContentMaxRem(siguiente);
        previsualizarDensidad(contentDensity, siguiente, contentPaddingRem);
    };

    const pasoMargen = (direccion) => {
        const siguiente = clampContentPaddingRem(contentPaddingRem + direccion * CONTENT_PADDING_REM_STEP);
        setContentPaddingRem(siguiente);
        previsualizarDensidad(contentDensity, contentMaxRem, siguiente);
    };

    const cambiarFondo = (valorCrudo) => {
        const valor = normalizeFondoBase(valorCrudo);
        setBg(valor);
        setFondoPendiente((prev) => ({ ...prev, quitar: false }));
        applyFondoPantalla(document.documentElement, valor);
        localStorage.setItem('bg_base', valor);
        emitir('theme-changed');
    };

    const subirFondo = async (event) => {
        const archivo = event.target.files?.[0];
        if (!archivo) return;
        const limpiarInput = () => { if (bgInputRef.current) bgInputRef.current.value = ''; };

        const error = validateImageSource(archivo, 'Fondo de pantalla');
        if (error) {
            setAviso({ tipo: 'error', texto: error });
            limpiarInput();
            return;
        }

        setComprimiendo(true);
        try {
            const comprimido = await compressImageToWebp(archivo, { maxDimension: 1920, quality: 0.82, maxBytes: MAX_BG_PHOTO_BYTES });
            setFondoPendiente({ archivo: comprimido, quitar: false });
            const vistaPrevia = URL.createObjectURL(comprimido);
            setBg(vistaPrevia);
            applyFondoPantalla(document.documentElement, vistaPrevia);
            localStorage.setItem('bg_base', vistaPrevia);
            emitir('theme-changed');
            setAviso(null);
        } catch (err) {
            setAviso({
                tipo: 'error',
                texto: err?.message === 'COMPRESS_TOO_LARGE'
                    ? 'El fondo sigue siendo muy grande después de optimizarlo. Prueba con una imagen de menor resolución.'
                    : 'No se pudo procesar la imagen de fondo. Verifica que sea una imagen válida.',
            });
            setFondoPendiente((prev) => ({ ...prev, archivo: null }));
        } finally {
            setComprimiendo(false);
            limpiarInput();
        }
    };

    const quitarFondoPropio = () => {
        setFondoPendiente({ archivo: null, quitar: true });
        setBg(FONDO_SISTEMA);
        applyFondoPantalla(document.documentElement, FONDO_SISTEMA);
        localStorage.setItem('bg_base', FONDO_SISTEMA);
        emitir('theme-changed');
        if (bgInputRef.current) bgInputRef.current.value = '';
    };

    // --- Navegación ---
    const cambiarLayout = (valor) => {
        setLayout(valor);
        localStorage.setItem('theme_layout', valor);
        emitir('theme-layout-preview', { layout: valor });
        if (isProfessionalSidebarLayout(valor)) {
            setMobileLayout('mobile_topbar');
            localStorage.setItem('theme_layout_mobile', 'mobile_topbar');
            emitir('theme-mobile-layout-preview', { layout: 'mobile_topbar' });
        }
        emitir('theme-changed');
    };

    const cambiarLayoutMovil = (valor) => {
        setMobileLayout(valor);
        localStorage.setItem('theme_layout_mobile', valor);
        emitir('theme-mobile-layout-preview', { layout: valor });
        emitir('theme-changed');
    };

    const cambiarModoSidebar = (modo) => {
        setSidebarMode(modo);
        localStorage.setItem('theme_sidebar_mode', modo);
        emitir('theme-sidebar-mode-preview', { mode: modo });
        emitir('theme-changed');
    };

    const cambiarPosicionFija = (posicion) => {
        setFixedPosition(posicion);
        localStorage.setItem('theme_fixed_position', posicion);
        emitir('theme-fixed-position-preview', { position: posicion });
        emitir('theme-changed');
    };

    const aplicarPreset = (preset) => {
        const escala = preset.escala !== undefined ? clampFontScale(preset.escala) : scale;
        const oscuro = preset.modo === 'dark';
        const acento = preset.colorNombre || preset.colorHex;
        const cristal = preset.glass !== undefined ? preset.glass : glass;
        const distribucion = preset.layout !== undefined ? preset.layout : layout;
        const fuente = preset.font !== undefined ? preset.font : font;
        const fondo = normalizeFondoBase(preset.bg);

        setDark(oscuro);
        setColor(acento);
        setBg(fondo);
        setFont(fuente);
        setScale(escala);
        setGlass(cristal);
        setLayout(distribucion);
        if (preset.sound !== undefined) {
            actualizarAlertas((prev) => ({ ...prev, canales: { ...prev.canales, sonido: preset.sound } }));
        }

        aplicarTemaAlDocumento({
            color: acento, font: fuente, bg: fondo, scale: escala, glass: cristal,
            contentDensity, contentMaxRem, contentPaddingRem,
        });
        localStorage.setItem('theme', oscuro ? 'dark' : 'light');
        localStorage.setItem('theme_color', acento);
        localStorage.setItem('bg_base', fondo);
        localStorage.setItem('theme_font', fuente);
        localStorage.setItem(FONT_SCALE_STORAGE_KEY, String(escala));
        localStorage.setItem('theme_glass', String(cristal));
        localStorage.setItem('theme_layout', distribucion);
        emitir('theme-layout-preview', { layout: distribucion });
        emitir('theme-changed');
    };

    // --- Guardar / descartar ---
    const descartar = useCallback(() => {
        const { tema, alertas: alertasOrigen, instantanea } = origen.current;
        restaurarInstantaneaTema(instantanea);
        aplicarTemaAlDocumento(tema);
        persistAlertasPrefsToStorage(alertasOrigen);
        NotificationBrowserService.setPreferences(alertasOrigen);
        emitir('theme-layout-preview', { layout: tema.layout });
        emitir('theme-mobile-layout-preview', { layout: tema.mobileLayout });
        emitir('theme-sidebar-mode-preview', { mode: tema.sidebarMode });
        emitir('theme-fixed-position-preview', { position: tema.fixedPosition });
        emitir('theme-changed');
    }, []);

    // Cierre inesperado con cambios sin guardar: la vista previa nunca debe quedarse puesta.
    useEffect(() => () => {
        if (sinGuardarRef.current) descartar();
    }, [descartar]);

    /** Guarda en segundo plano. Resuelve `true` si quedó guardado. */
    const guardar = async () => {
        if (estadoGuardado === 'guardando') return false;
        clearTimeout(temporizadorRef.current);
        setAviso(null);
        setEstadoGuardado('guardando');

        const subioFondo = Boolean(fondoPendiente.archivo);
        const datos = new FormData();
        // El endpoint de perfil exige nombre y apellido; se reenvían tal cual sin tocarlos.
        datos.append('name', usuario.name ?? '');
        datos.append('apellido_paterno', usuario.apellido_paterno ?? '');
        datos.append('tema_visual', JSON.stringify(construirTemaVisual(estado, { archivoFondo: subioFondo })));
        if (subioFondo) datos.append('archivo_fondo', fondoPendiente.archivo);
        if (fondoPendiente.quitar) datos.append('remove_fondo', '1');

        try {
            const { data } = await axios.post(route('profile.update'), datos, { headers: { Accept: 'application/json' } });

            const alertasSincronizadas = data?.alertas_prefs ? mergeAlertasPrefs(data.alertas_prefs) : estado.alertas;
            const fondoFinal = subioFondo && data?.fondo_base ? normalizeFondoBase(data.fondo_base) : estado.bg;
            const guardado = { ...estado, alertas: alertasSincronizadas, bg: fondoFinal };

            if (subioFondo && data?.fondo_base) {
                localStorage.setItem('bg_base', fondoFinal);
                applyFondoPantalla(document.documentElement, fondoFinal);
            }
            persistAlertasPrefsToStorage(alertasSincronizadas);
            NotificationBrowserService.setPreferences(alertasSincronizadas);

            huellaBase.current = huellaAjustes(guardado);
            origen.current = { ...origen.current, tema: guardado, alertas: alertasSincronizadas, instantanea: capturarInstantaneaTema() };
            setFondoPendiente({ archivo: null, quitar: false });
            setBg(fondoFinal);
            setAlertas(alertasSincronizadas);
            emitir('theme-changed');

            setEstadoGuardado('guardado');
            temporizadorRef.current = setTimeout(() => setEstadoGuardado('idle'), 2200);
            return true;
        } catch (error) {
            const errores = error?.response?.data?.errors || {};
            const faltaPerfil = errores.name || errores.apellido_paterno;
            setAviso({
                tipo: 'error',
                texto: faltaPerfil
                    ? 'Completa tu nombre y apellido en «Mi perfil» para poder guardar.'
                    : (errores.archivo_fondo?.[0] || 'No se pudieron guardar los ajustes. Intenta de nuevo.'),
            });
            setEstadoGuardado('idle');
            return false;
        }
    };

    return {
        usuario,
        presets: catalogoTemas,
        fondos: catalogoFondos,
        tonos: tonosAlertas,
        estado,
        sinGuardar,
        guardando: estadoGuardado === 'guardando',
        estadoGuardado,
        comprimiendo,
        aviso,
        descartarAviso: () => setAviso(null),
        bgInputRef,
        acciones: {
            cambiarModo, cambiarColor, cambiarFuente, cambiarCristal, pasoEscala,
            cambiarDensidad, pasoAncho, pasoMargen, cambiarFondo, subirFondo, quitarFondoPropio,
            cambiarLayout, cambiarLayoutMovil, cambiarModoSidebar, cambiarPosicionFija,
            aplicarPreset, actualizarAlertas, alternarCanal, alternarTipo,
        },
        guardar,
        descartar,
    };
}
