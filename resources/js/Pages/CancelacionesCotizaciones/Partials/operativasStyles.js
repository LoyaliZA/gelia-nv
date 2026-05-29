export const ACCENT = '#f97316';

export const ESTADO_BADGE = {
    1: 'bg-amber-500/15 text-amber-600 border-amber-500/30',
    2: 'bg-emerald-500/15 text-emerald-600 border-emerald-500/30',
    3: 'bg-emerald-500/15 text-emerald-700 border-emerald-500/30',
    4: 'bg-red-500/15 text-red-600 border-red-500/30',
};

export const BTN_PRIMARY =
    'inline-flex items-center gap-2 px-5 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest text-white shadow-md transition-transform hover:scale-[1.02] outline-none disabled:opacity-50';

export const BTN_SECONDARY =
    'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest theme-element border theme-border theme-text-main outline-none hover:border-orange-500 transition-colors';

export const TIPOS_OPERATIVO = [
    { id: '', label: 'Todos' },
    { id: 'REMISION', label: 'Remisión' },
    { id: 'PEDIDO', label: 'Pedido' },
    { id: 'COTIZACION', label: 'Cotización' },
];

export const tipoOperativoDeProceso = (proceso) => {
    const nombre = proceso?.nombre?.toUpperCase() || '';
    if (nombre.includes('REMISIÓN') || nombre.includes('REMISION')) return 'remision';
    if (nombre.includes('PEDIDO') && nombre.includes('CANCEL')) return 'pedido';
    if (nombre.includes('COTIZACIÓN') || nombre.includes('COTIZACION')) return 'cotizacion_pedido';
    return 'generico';
};
