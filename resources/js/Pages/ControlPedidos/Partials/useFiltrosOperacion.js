import { useEffect, useRef, useState } from 'react';

export default function useFiltrosOperacion(filtros, cargar, inicial = {}) {
    const [valores, setValores] = useState(() => ({ tab: 'TODOS', q: '', desde: '', hasta: '', vendedora: '', orden: 'antiguos', ...inicial, ...filtros }));
    const actual = useRef(valores);
    const debounce = useRef(null);
    useEffect(() => () => clearTimeout(debounce.current), []);
    const params = (extra = {}) => ({ ...actual.current, tarea: undefined, ...extra });
    const cambiar = (cambios, diferir = false) => {
        clearTimeout(debounce.current);
        actual.current = { ...actual.current, ...cambios, page: 1 };
        setValores(actual.current);
        if (diferir) debounce.current = setTimeout(() => cargar(params()), 350);
        else cargar(params());
    };
    const limpiar = () => {
        clearTimeout(debounce.current);
        actual.current = { tab: 'TODOS', q: '', tipo: '', desde: '', hasta: '', vendedora: '', orden: 'antiguos', page: 1 };
        setValores(actual.current);
        cargar(params());
    };
    return { valores, params, cambiar, limpiar };
}
