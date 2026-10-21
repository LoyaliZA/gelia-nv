import { useEffect, useRef, useState } from 'react';
import { THEME_INPUT, THEME_LABEL } from '@/utils/geliaTheme';

export default function BuscarUsuario({ value, nombre = '', onChange, etiqueta = 'Cuenta', disabled = false }) {
    const [q, setQ] = useState(nombre);
    const [opciones, setOpciones] = useState([]);
    const [estado, setEstado] = useState('');
    const peticion = useRef(null);
    const secuencia = useRef(0);
    const limpiandoSeleccion = useRef(false);

    useEffect(() => {
        if (!value && !limpiandoSeleccion.current) { setQ(''); setEstado(''); setOpciones([]); }
        else if (value && nombre) setQ(nombre);
        limpiandoSeleccion.current = false;
    }, [value, nombre]);
    useEffect(() => () => peticion.current?.abort(), []);

    async function buscar(texto) {
        const turno = ++secuencia.current;
        peticion.current?.abort();
        setQ(texto);
        limpiandoSeleccion.current = !!value;
        onChange('', '');
        setOpciones([]);
        setEstado('');
        if (texto.trim().length < 2) return;
        const control = new AbortController();
        peticion.current = control;
        setEstado('Buscando…');
        try {
            const respuesta = await fetch(`${route('gestion_interna.comisiones_empaque.usuarios')}?q=${encodeURIComponent(texto)}`, {
                signal: control.signal,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!respuesta.ok) throw new Error('busqueda');
            const opciones = await respuesta.json();
            if (turno !== secuencia.current) return;
            setOpciones(opciones);
            setEstado(opciones.length ? 'Elija una cuenta de los resultados.' : 'No se encontraron cuentas con ese nombre.');
        } catch (error) {
            if (error.name !== 'AbortError' && turno === secuencia.current) setEstado('No se pudo buscar. Escriba de nuevo para reintentar.');
        }
    }

    return <div>
        <label><span className={THEME_LABEL}>{etiqueta}</span>
            <input className={THEME_INPUT} value={q} disabled={disabled} onChange={(e) => buscar(e.target.value)} placeholder="Escriba al menos 2 letras…" autoComplete="off" />
        </label>
        <p className="text-xs theme-text-muted m-0 mt-1" role="status">{value ? `Cuenta seleccionada: ${q}` : estado || 'Busque y seleccione una cuenta existente.'}</p>
        {opciones.length > 0 && <ul className="mt-1 border theme-border rounded-lg overflow-hidden list-none p-0">
            {opciones.map((opcion) => <li key={opcion.id}>
                <button type="button" className="w-full text-left px-3 py-2 text-sm theme-text-main hover:bg-black/5 focus-visible:outline focus-visible:outline-2" onClick={() => {
                    ++secuencia.current;
                    peticion.current?.abort();
                    onChange(opcion.id, opcion.name);
                    setQ(opcion.name);
                    setOpciones([]);
                }}>{opcion.name}</button>
            </li>)}
        </ul>}
    </div>;
}
