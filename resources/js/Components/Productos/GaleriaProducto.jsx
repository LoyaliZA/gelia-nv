import { useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { THEME_BTN_PRIMARY } from '@/utils/geliaTheme';

function mensajeError(error, reserva) {
    const datos = error?.response?.data;
    const primero = datos?.errors ? Object.values(datos.errors).flat()[0] : null;

    return primero || datos?.message || reserva;
}

export default function GaleriaProducto({ productoId, galeria, puedeGestionar, retorno }) {
    const imagenes = galeria?.data || [];
    const [ocupado, setOcupado] = useState(false);
    const [aviso, setAviso] = useState('');
    const [confirmarId, setConfirmarId] = useState(null);

    const recargar = (extra = {}) => {
        router.get(route('almacenes.productos.ficha', {
            producto: productoId,
            ...retorno?.params,
            desde: retorno?.desde,
            galeria_page: galeria?.current_page,
            ...extra,
        }), {}, { preserveScroll: true, preserveState: true });
    };

    const actuar = async (url, opciones = {}) => {
        setOcupado(true);
        setAviso('');
        try {
            await axios(url, { method: opciones.method || 'post', data: opciones.data });
            setConfirmarId(null);
            recargar();
        } catch (error) {
            setAviso(mensajeError(error, 'No se pudo actualizar la galería.'));
        } finally {
            setOcupado(false);
        }
    };

    const subir = (evento) => {
        const archivo = evento.target.files?.[0];
        evento.target.value = '';
        if (!archivo) return;
        const datos = new FormData();
        datos.append('archivo', archivo);
        actuar(route('gestion_interna.productos.imagenes.store', productoId), { data: datos });
    };

    const reemplazar = (imagenId, evento) => {
        const archivo = evento.target.files?.[0];
        evento.target.value = '';
        if (!archivo) return;
        const datos = new FormData();
        datos.append('archivo', archivo);
        actuar(route('gestion_interna.productos.imagenes.reemplazar', { producto: productoId, imagen: imagenId }), { data: datos });
    };

    const mover = (imagenId, direccion) => {
        actuar(route('gestion_interna.productos.imagenes.mover', { producto: productoId, imagen: imagenId }), {
            data: { direccion },
        });
    };

    return (
        <section className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-sm font-black uppercase theme-text-main">Imágenes del producto</h2>
                {puedeGestionar && (
                    <label className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact cursor-pointer`}>
                        Cargar imagen
                        <input type="file" accept="image/jpeg,image/png,image/webp" className="sr-only" onChange={subir} disabled={ocupado} />
                    </label>
                )}
            </div>
            {aviso && <p className="text-[11px] font-bold theme-text-aviso" role="alert">{aviso}</p>}
            {ocupado && <p className="text-[11px] font-bold theme-text-muted">Actualizando galería…</p>}
            {imagenes.length === 0 ? (
                <p className="text-[11px] font-bold theme-text-muted">Este producto no tiene imágenes en la galería.</p>
            ) : (
                <ul className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    {imagenes.map((imagen, indice) => (
                        <li key={imagen.id} className="theme-element border theme-border rounded-xl p-2 space-y-2">
                            <a href={imagen.original_url} className="block">
                                <img src={imagen.miniatura_url} alt="" className="w-full h-28 object-cover rounded-lg" />
                            </a>
                            <p className="text-[10px] font-black uppercase theme-text-muted">
                                {imagen.es_principal ? 'Principal' : `Posición ${imagen.posicion}`}
                                {imagen.estado === 'miniatura_error' ? ' · Miniatura pendiente' : ''}
                            </p>
                            {puedeGestionar && (
                                <div className="flex flex-wrap gap-1">
                                    <button type="button" className="px-2 py-1 text-[10px] font-black uppercase theme-element border theme-border rounded-lg" onClick={() => mover(imagen.id, 'arriba')} disabled={ocupado || (galeria.current_page === 1 && indice === 0)}>Subir</button>
                                    <button type="button" className="px-2 py-1 text-[10px] font-black uppercase theme-element border theme-border rounded-lg" onClick={() => mover(imagen.id, 'abajo')} disabled={ocupado || (galeria.current_page === galeria.last_page && indice === imagenes.length - 1)}>Bajar</button>
                                    {!imagen.es_principal && (
                                        <button type="button" className="px-2 py-1 text-[10px] font-black uppercase theme-element border theme-border rounded-lg" disabled={ocupado} onClick={() => actuar(route('gestion_interna.productos.imagenes.principal', { producto: productoId, imagen: imagen.id }))}>Principal</button>
                                    )}
                                    <label className="px-2 py-1 text-[10px] font-black uppercase theme-element border theme-border rounded-lg cursor-pointer">
                                        Reemplazar
                                        <input type="file" accept="image/jpeg,image/png,image/webp" className="sr-only" disabled={ocupado} onChange={(evento) => reemplazar(imagen.id, evento)} />
                                    </label>
                                    {imagen.estado === 'miniatura_error' && (
                                        <button type="button" className="px-2 py-1 text-[10px] font-black uppercase theme-element border theme-border rounded-lg" disabled={ocupado} onClick={() => actuar(route('gestion_interna.productos.imagenes.miniatura', { producto: productoId, imagen: imagen.id }))}>Reintentar miniatura</button>
                                    )}
                                    {confirmarId === imagen.id ? (
                                        <button type="button" className="px-2 py-1 text-[10px] font-black uppercase theme-element border theme-border rounded-lg" disabled={ocupado} onClick={() => actuar(route('gestion_interna.productos.imagenes.destruir', { producto: productoId, imagen: imagen.id }), { method: 'delete' })}>Confirmar quitar</button>
                                    ) : (
                                        <button type="button" className="px-2 py-1 text-[10px] font-black uppercase theme-element border theme-border rounded-lg" disabled={ocupado} onClick={() => setConfirmarId(imagen.id)}>Quitar</button>
                                    )}
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {(galeria?.last_page || 1) > 1 && (
                <div className="flex gap-2">
                    <button type="button" className="px-3 py-2 text-[10px] font-black uppercase theme-element border theme-border rounded-xl" disabled={galeria.current_page <= 1} onClick={() => recargar({ galeria_page: galeria.current_page - 1 })}>Anterior</button>
                    <button type="button" className="px-3 py-2 text-[10px] font-black uppercase theme-element border theme-border rounded-xl" disabled={galeria.current_page >= galeria.last_page} onClick={() => recargar({ galeria_page: galeria.current_page + 1 })}>Siguiente</button>
                </div>
            )}
        </section>
    );
}
