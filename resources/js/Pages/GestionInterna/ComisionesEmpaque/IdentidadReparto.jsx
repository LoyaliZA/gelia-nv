const paleta = ['#4389f8', '#14b8a6', '#a78bfa', '#f59e0b', '#ec4899', '#22c55e',
    '#f87171', '#38bdf8', '#e879f9', '#f97316', '#a3e635', '#818cf8'];

export function colorReparto(linea, indice) {
    return /^#[0-9a-f]{6}$/i.test(linea.color || '') ? linea.color : paleta[indice % paleta.length];
}

export function colorDisponible(lineas) {
    const asignados = new Set(lineas.map((linea, indice) => colorReparto(linea, indice).toLowerCase()));
    return paleta.find((color) => !asignados.has(color)) ?? colorReparto({}, lineas.length);
}

export function MarcaColaborador({ color }) {
    return <span aria-hidden="true" className="inline-block size-2.5 shrink-0 rounded-full ring-1 ring-inset ring-white/25" style={{ backgroundColor: color }} />;
}

export function BarraReparto({ lineas }) {
    if (!lineas.length) return null;
    return <div className="flex h-2.5 w-full overflow-hidden rounded-full theme-element" aria-hidden="true">
        {lineas.map((linea, indice) => <span key={linea.participante_id ?? linea.fila ?? indice} className="h-full shrink-0"
            style={{ width: `${Math.max(0, Math.min(Number(linea.porcentaje) || 0, 100))}%`, backgroundColor: colorReparto(linea, indice) }} />)}
    </div>;
}
