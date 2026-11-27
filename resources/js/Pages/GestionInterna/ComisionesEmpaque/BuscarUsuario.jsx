import { ChevronDown, Search } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { THEME_INPUT, THEME_LABEL } from '@/utils/geliaTheme';

export default function BuscarUsuario({ value, nombre = '', onChange, etiqueta = 'Cuenta', disabled = false, areaEquipo }) {
    const id = useId();
    const campo = useRef(null);
    const devolverFoco = useRef(false);
    const lista = useRef(null);
    const [abierto, setAbierto] = useState(false);
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

    function cerrar() {
        ++secuencia.current;
        peticion.current?.abort();
        setOpciones([]);
        setAbierto(false);
    }

    async function buscar(texto, editando = true) {
        const turno = ++secuencia.current;
        peticion.current?.abort();
        if (editando) {
            setQ(texto);
            limpiandoSeleccion.current = !!value;
            onChange('', '');
        }
        setOpciones([]);
        setEstado('');
        if (!areaEquipo && texto.trim().length < 2) return;
        setAbierto(true);
        const consulta = new URLSearchParams({ q: texto });
        if (areaEquipo) consulta.set('equipo_area_id', areaEquipo);
        const control = new AbortController();
        peticion.current = control;
        setEstado('Buscando…');
        try {
            const respuesta = await fetch(`${route('gestion_interna.comisiones_empaque.usuarios')}?${consulta}`, {
                signal: control.signal,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!respuesta.ok) throw new Error('busqueda');
            const opciones = await respuesta.json();
            if (turno !== secuencia.current) return;
            setOpciones(opciones);
            setEstado(opciones.length ? (areaEquipo ? '' : 'Elija una cuenta de los resultados.')
                : (areaEquipo ? 'No hay coincidencias entre los colaboradores con acceso a esta área.' : 'No se encontraron cuentas con ese nombre.'));
        } catch (error) {
            if (error.name !== 'AbortError' && turno === secuencia.current) setEstado('No se pudo buscar. Escriba de nuevo para reintentar.');
        }
    }

    const abrir = () => {
        if (areaEquipo && !abierto && !devolverFoco.current) buscar(value ? '' : q, false);
    };
    return <div className="relative min-w-0" onBlur={(e) => {
        if (areaEquipo && !e.currentTarget.contains(e.relatedTarget)) cerrar();
    }} onKeyDown={(e) => {
        if (e.key === 'Escape' && abierto) {
            e.preventDefault();
            campo.current?.focus();
            cerrar();
        }
    }}>
        <label htmlFor={id} className={THEME_LABEL}>{etiqueta}</label>
        <div className="relative">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 theme-text-muted" aria-hidden="true" />
            <input ref={campo} id={id} className={`${THEME_INPUT} !pl-9 ${areaEquipo ? '!pr-11' : ''}`} type="search"
                value={q} disabled={disabled} onFocus={abrir} onClick={abrir}
                onChange={(e) => buscar(e.target.value)} onKeyDown={(e) => {
                    if (e.key === 'ArrowDown' && areaEquipo) {
                        e.preventDefault();
                        lista.current?.querySelector('button')?.focus();
                        abrir();
                    }
                }}
                aria-controls={opciones.length ? `${id}-opciones` : undefined}
                placeholder={areaEquipo ? 'Buscar por nombre o usuario' : 'Escriba al menos 2 letras…'} autoComplete="off" />
            {areaEquipo && <button type="button" className="absolute inset-y-0 right-0 grid w-11 place-items-center rounded-lg theme-text-muted focus-visible:outline-2"
                aria-label={abierto ? 'Cerrar lista de colaboradores' : 'Mostrar colaboradores'} aria-expanded={abierto} disabled={disabled}
                onClick={abierto ? cerrar : abrir}><ChevronDown className="size-4" aria-hidden="true" /></button>}
        </div>
        {(!areaEquipo || (abierto && estado)) && <p className="text-xs theme-text-muted m-0 mt-1" role="status">
            {areaEquipo ? estado : (value ? `Cuenta seleccionada: ${q}` : estado || 'Busque y seleccione una cuenta existente.')}
        </p>}
        {opciones.length > 0 && <ul ref={lista} id={`${id}-opciones`} aria-label="Colaboradores disponibles"
            className={`mt-1 border theme-border rounded-lg list-none p-1 ${areaEquipo ? 'absolute z-20 w-full max-h-60 overflow-auto theme-element shadow-md' : ''}`}>
            {opciones.map((opcion) => <li key={opcion.id}>
                <button type="button" className="w-full min-h-11 rounded-md text-left px-3 py-2 text-sm theme-text-main hover:bg-current/5 focus-visible:outline focus-visible:outline-2" onClick={() => {
                    cerrar();
                    if (opcion.facultades) onChange(opcion.id, opcion.name, opcion);
                    else onChange(opcion.id, opcion.name);
                    setQ(opcion.name);
                    if (areaEquipo) {
                        devolverFoco.current = true;
                        campo.current?.focus();
                        devolverFoco.current = false;
                    }
                }}>
                    <span className="block font-medium">{opcion.name}</span>
                    {areaEquipo && (opcion.username || opcion.area) && <span className="block text-xs theme-text-muted">{[opcion.username, opcion.area].filter(Boolean).join(' · ')}</span>}
                </button>
            </li>)}
        </ul>}
    </div>;
}
