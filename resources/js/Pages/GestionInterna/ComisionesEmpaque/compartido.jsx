import { router } from '@inertiajs/react';

export const VISITA_MODAL = { preserveState: true, preserveScroll: true };
export const BOTON_FILTRO = 'inline-flex items-center gap-2 rounded-lg border theme-border theme-element px-3 py-2 text-sm font-semibold theme-text-main min-h-11 cursor-pointer focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2';
export function dinero(valor) {
    if (valor == null || valor === '') return '—';
    return Number(valor).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
}

export function ir(params) {
    const actual = new URL(window.location.href).searchParams;
    const contexto = Object.fromEntries(['area_id', 'periodo_id', 'desde', 'hasta'].filter((clave) => actual.has(clave)).map((clave) => [clave, actual.get(clave)]));
    const query = { ...contexto, ...params };
    router.get(route('gestion_interna.comisiones_empaque.index'), query, { preserveState: false, preserveScroll: true });
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

