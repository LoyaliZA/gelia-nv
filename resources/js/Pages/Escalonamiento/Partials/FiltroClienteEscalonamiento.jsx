import { useEffect, useState } from 'react';
import axios from 'axios';
import { Search, X } from 'lucide-react';
import { THEME_INPUT, THEME_LABEL } from '../../../utils/geliaTheme';

/**
 * @param {{ valorId?: string|number, etiquetaInicial?: string, onSeleccionar: (cliente: { id: number, etiqueta: string }|null) => void, label?: string }} props
 */
export default function FiltroClienteEscalonamiento({
    valorId = '',
    etiquetaInicial = '',
    onSeleccionar,
    label = 'Cliente',
}) {
    const [busqueda, setBusqueda] = useState('');
    const [etiqueta, setEtiqueta] = useState(etiquetaInicial || '');
    const [sugerencias, setSugerencias] = useState([]);
    const [cargando, setCargando] = useState(false);

    useEffect(() => {
        setEtiqueta(etiquetaInicial || '');
    }, [etiquetaInicial, valorId]);

    useEffect(() => {
        const q = busqueda.trim();
        if (q.length < 2) {
            setSugerencias([]);
            setCargando(false);
            return undefined;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setCargando(true);
            axios
                .get(route('escalonamiento.clientes.buscar'), { params: { q }, signal: controller.signal })
                .then((res) => setSugerencias(res.data?.clientes || []))
                .catch((error) => {
                    if (error?.code !== 'ERR_CANCELED') setSugerencias([]);
                })
                .finally(() => {
                    if (!controller.signal.aborted) setCargando(false);
                });
        }, 280);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [busqueda]);

    const limpiar = () => {
        setBusqueda('');
        setEtiqueta('');
        setSugerencias([]);
        onSeleccionar(null);
    };

    const elegir = (cliente) => {
        setEtiqueta(cliente.etiqueta);
        setBusqueda('');
        setSugerencias([]);
        onSeleccionar(cliente);
    };

    return (
        <div className="space-y-2 relative">
            <span className={THEME_LABEL}>{label}</span>
            {valorId && etiqueta ? (
                <div className="flex min-h-11 items-center gap-3 theme-element border theme-border rounded-xl px-3.5 py-2.5 text-sm">
                    <span className="flex-1 theme-text-main font-semibold truncate">{etiqueta}</span>
                    <button type="button" className="min-w-9 min-h-9 inline-flex items-center justify-center rounded-lg hover:bg-black/5 dark:hover:bg-white/5" onClick={limpiar} aria-label="Quitar cliente">
                        <X className="w-4 h-4 theme-text-muted" />
                    </button>
                </div>
            ) : (
                <>
                    <div className="theme-field-with-icon">
                        <Search className="theme-field-icon" aria-hidden />
                        <input
                            type="search"
                            className={`${THEME_INPUT} w-full`}
                            placeholder="Número o nombre (mín. 2 caracteres)"
                            value={busqueda}
                            onChange={(e) => setBusqueda(e.target.value)}
                            autoComplete="off"
                        />
                    </div>
                    {cargando && <p className="text-xs theme-text-muted m-0">Buscando…</p>}
                    {sugerencias.length > 0 && (
                        <ul className="absolute z-20 left-0 right-0 mt-2 theme-surface border theme-border rounded-xl shadow-lg max-h-64 overflow-y-auto m-0 p-1.5 list-none">
                            {sugerencias.map((c) => (
                                <li key={c.id}>
                                    <button
                                        type="button"
                                        className="w-full min-h-11 text-left px-3.5 py-2.5 rounded-lg text-sm theme-text-main hover:bg-black/5 dark:hover:bg-white/5"
                                        onClick={() => elegir(c)}
                                    >
                                        {c.etiqueta}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </>
            )}
        </div>
    );
}
