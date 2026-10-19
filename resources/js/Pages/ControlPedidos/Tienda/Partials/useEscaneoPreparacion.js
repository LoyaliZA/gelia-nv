import { useEffect, useRef, useState } from 'react';
import axios from 'axios';

// Las lecturas se resuelven en orden; una consulta lenta no descarta la siguiente.
export default function useEscaneoPreparacion({ almacenId, onProducto }) {
    const cola = useRef(Promise.resolve());
    const sesion = useRef(null);
    const agregar = useRef(onProducto);
    agregar.current = onProducto;
    const [pendientes, setPendientes] = useState(0);
    const [ultimo, setUltimo] = useState('');
    const [fallos, setFallos] = useState([]);

    useEffect(() => {
        const controller = new AbortController();
        sesion.current = controller;
        cola.current = Promise.resolve();
        setPendientes(0);
        setFallos([]);
        setUltimo('');
        return () => controller.abort();
    }, [almacenId]);

    const encolar = (valor) => {
        const codigo = String(valor || '').trim();
        const controller = sesion.current;
        if (!codigo || !almacenId || !controller || controller.signal.aborted) return;
        setPendientes((n) => n + 1);
        cola.current = cola.current.then(async () => {
            if (controller.signal.aborted) return;
            try {
                const { data } = await axios.get(route('gestion_interna.productos.buscar'), {
                    params: { q: codigo, almacen_id: almacenId, per_page: 25, incluir_sin_inventario: 1 }, signal: controller.signal,
                });
                if (controller.signal.aborted) return;
                const encontrados = data.data || [];
                const normalizar = (v) => String(v || '').trim().toLowerCase().replace(/^\][a-z]\d/, '').replace(/^0+(?=\d)/, '');
                const buscado = normalizar(codigo);
                const producto = encontrados.find((p) => [p.sku, p.codigo_barras, p.folio].some((v) => normalizar(v) === buscado))
                    || (encontrados.length === 1 ? encontrados[0] : null);
                if (!producto) throw new Error(encontrados.length ? 'Hay varios resultados. Seleccione el producto en el buscador.' : 'No se encontró en este almacén. Busque por nombre o reporte el problema.');
                agregar.current(producto);
                setUltimo(`${producto.descripcion || producto.sku}: +1 pieza`);
            } catch (e) {
                if (!controller.signal.aborted) setFallos((prev) => [...prev, { codigo, mensaje: e.response?.data?.message || e.message || 'No se pudo consultar el catálogo. Intente de nuevo.' }]);
            } finally {
                if (!controller.signal.aborted) setPendientes((n) => n - 1);
            }
        });
    };

    return { encolar, pendientes, ultimo, fallos, quitarFallo: (i) => setFallos((prev) => prev.filter((_, j) => j !== i)) };
}
