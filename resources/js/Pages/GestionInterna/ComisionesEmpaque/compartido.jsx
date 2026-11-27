import { router } from '@inertiajs/react';

export const VISITA_MODAL = { preserveState: true, preserveScroll: true };
export const ACCION_PRINCIPAL_COMISIONES = 'theme-btn-primary min-h-11 !px-3 !py-3 !text-sm !normal-case !tracking-normal !shadow-sm md:!px-6 focus-visible:!outline-2 focus-visible:outline-offset-2 motion-reduce:!transform-none motion-reduce:!transition-none !bg-[color-mix(in_srgb,var(--color-primario)_45%,black)] supports-[color:contrast-color(red)]:!bg-[var(--color-primario)] supports-[color:contrast-color(red)]:![color:contrast-color(var(--color-primario))]';
export const BOTON_FILTRO = 'inline-flex items-center gap-2 rounded-lg border theme-border theme-element px-3 py-2 text-sm font-semibold theme-text-main min-h-11 cursor-pointer focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2';
export function dinero(valor) {
    if (valor == null || valor === '') return '—';
    return Number(valor).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
}

const PROPS = [
    'tab', 'reporte', 'origen_simple', 'contexto_simple', 'puede', 'areas', 'fecha_inicio', 'fecha_fin', 'sincronizacion', 'filtros', 'sin_areas',
    'periodos_opciones', 'carga_revision', 'captura_nombre', 'resumen', 'situaciones', 'motivos_comerciales',
    'tipos_caja', 'paqueterias', 'acumulado', 'totales', 'preview', 'cortes', 'corte', 'productividad',
    'saldos', 'tarifas', 'repartos', 'alcances', 'equipo', 'periodos', 'flash',
];
const ETIQUETA_CACHE = 'comisiones-empaque';
const precargas = new Map();
const oyentes = new Set();
let turno = 0;
let epocaPrecarga = 0;
const PAUSA_PRECARGA = 30_000;
let reanudarPrecargaEn = 0;
const precargaEnPausa = () => Date.now() < reanudarPrecargaEn;
let cancelarLectura;
let lecturaBloqueada = false;
let confirmarLectura;
const publicar = (estado) => oyentes.forEach((oyente) => oyente(estado));
let reportarErrorLectura;
const errorSilencioso = () => { reportarErrorLectura?.mostrar(); return false; };
const errorDeRed = (error) => {
    if (!error?.url || error.url === reportarErrorLectura?.url) reportarErrorLectura?.mostrar();
    return false;
};

export function observarConsultas(oyente) {
    oyentes.add(oyente);
    return () => oyentes.delete(oyente);
}

export function confirmarAntesDeConsultar(confirmar) {
    confirmarLectura = confirmar;
    return () => { confirmarLectura = null; };
}

export function bloquearConsultas(bloqueo) {
    lecturaBloqueada = bloqueo;
}

function consulta(params, identidad, only = PROPS) {
    const actual = new URL(window.location.href).searchParams;
    const claves = ['area_id', 'periodo_id', 'desde', 'hasta', 'origen'];
    const contexto = Object.fromEntries(claves.filter((clave) => actual.has(clave)).map((clave) => [clave, actual.get(clave)]));
    const datos = { ...contexto, ...params };
    const query = Object.fromEntries(Object.entries(datos).filter(([, valor]) => valor !== undefined && valor !== null && valor !== '').sort(([a], [b]) => a.localeCompare(b)));
    const url = route('gestion_interna.comisiones_empaque.index');
    const options = {
        preserveState: true,
        preserveScroll: true,
        showProgress: false,
        only,
        headers: identidad ? { 'X-Gelia-Comisiones-Contexto': `${identidad}/${epocaPrecarga}` } : {},
        onHttpException: errorSilencioso,
        onNetworkError: errorDeRed,
    };

    return { url, query, options, clave: JSON.stringify([epocaPrecarga, identidad, query]), epoca: epocaPrecarga };
}

export function invalidarPrecargas() {
    epocaPrecarga++;
    router.flushByCacheTags?.(ETIQUETA_CACHE);
}

export function cancelarConsulta() {
    turno++;
    cancelarLectura?.();
    cancelarLectura = null;
    reportarErrorLectura = null;
    publicar(null);
}

export function precargarComisiones(params, identidad) {
    if (!identidad || lecturaBloqueada || precargaEnPausa() || !router.prefetch
        || !['periodos', 'productividad'].includes(params.tab)) return;
    const { url, query, options, clave, epoca } = consulta(params, identidad);
    const visita = { ...options, data: query };
    if (precargas.size >= 2 || precargas.has(clave) || router.getCached?.(url, visita)) return;

    let resolver;
    const espera = new Promise((resolve) => { resolver = resolve; });
    const terminar = (disponible) => {
        window.clearTimeout(limite);
        if (precargas.get(clave) === espera) precargas.delete(clave);
        resolver(disponible);
    };
    const limite = window.setTimeout(() => {
        reanudarPrecargaEn = Date.now() + PAUSA_PRECARGA;
        terminar(false);
    }, 5000);
    precargas.set(clave, espera);
    router.prefetch(url, {
        ...visita,
        onPrefetched: (respuesta) => {
            const vigente = precargas.get(clave) === espera && epoca === epocaPrecarga && respuesta.status < 400;
            if (!vigente) router.flush?.(url, visita);
            terminar(vigente);
        },
    }, { cacheFor: '30s', cacheTags: ETIQUETA_CACHE });
}

export function ir(params, { identidad, only, replace = false, onSuccess } = {}) {
    if (lecturaBloqueada || confirmarLectura?.() === false) return;
    const { url, query, options, clave, epoca } = consulta(params, identidad, only);
    const intento = ++turno;
    cancelarLectura?.();
    cancelarLectura = null;

    const reintentar = () => ir(params, { identidad, only, replace, onSuccess });
    const fallo = () => {
        if (turno === intento) publicar({ error: true, reintentar });
    };
    const leer = (puedeUsarPrecarga = true) => {
        if (turno !== intento || lecturaBloqueada) return;
        let aceptada = false;
        const opcionDeCache = puedeUsarPrecarga && epoca === epocaPrecarga && !precargaEnPausa();
        reportarErrorLectura = { mostrar: fallo, url: null };
        const completar = () => {
            if (turno !== intento) return;
            cancelarLectura = null;
            reportarErrorLectura = null;
            publicar(null);
        };
        router.get(url, query, {
            ...options, replace, headers: opcionDeCache ? options.headers : {},
            onBefore: (visita) => {
                aceptada = turno === intento;
                if (aceptada) reportarErrorLectura.url = visita.url?.href;
                return aceptada;
            },
            onCancelToken: (token) => { cancelarLectura = token.cancel; },
            onSuccess: (page) => {
                completar();
                if (turno === intento) onSuccess?.(page);
            },
            onError: fallo,
            onFinish: (visit) => {
                if (visit.cancelled || visit.interrupted) completar();
                else if (turno === intento) {
                    cancelarLectura = null;
                    reportarErrorLectura = null;
                }
            },
        });
        if (!aceptada) completar();
    };

    publicar({ tab: query.tab, pendiente: true, reintentar });
    const pendiente = precargas.get(clave);
    if (pendiente) pendiente.then(leer);
    else leer();
}

export function momentoLegible(valor) {
    if (!valor) return '';
    const [fecha, hora = ''] = String(valor).split(' ');
    const [anio, mes, dia] = fecha.split('-');
    const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    const nombre = meses[Number(mes) - 1];
    if (!nombre || !dia || !anio) return String(valor);
    return `${Number(dia)} ${nombre} ${anio}${hora ? ` · ${hora}` : ''}`;
}

export function ErroresFormulario({ form }) {
    return Object.entries(form.errors).map(([campo, error]) => <p key={campo} className="theme-text-peligro text-sm m-0" role="alert">{error}</p>);
}

