import { useState } from 'react';
import axios from 'axios';
import { THEME_BTN_PRIMARY, geliaCardClass } from '@/utils/geliaTheme';

function mensajeError(error) {
    const datos = error?.response?.data;
    const primero = datos?.errors ? Object.values(datos.errors).flat()[0] : null;

    return primero || datos?.message || 'No se pudo procesar la carga.';
}

export default function CargaMultipleImagenes() {
    const [lote, setLote] = useState(null);
    const [resultados, setResultados] = useState([]);
    const [ocupado, setOcupado] = useState(false);
    const [aviso, setAviso] = useState('');
    const [marcados, setMarcados] = useState({});

    const analizar = async (evento) => {
        const archivos = Array.from(evento.target.files || []);
        evento.target.value = '';
        if (archivos.length === 0) return;
        const datos = new FormData();
        archivos.forEach((archivo) => datos.append('archivos[]', archivo));
        setOcupado(true);
        setAviso('');
        setResultados([]);
        try {
            const respuesta = await axios.post(route('gestion_interna.productos.imagenes.lotes.analizar'), datos);
            setLote(respuesta.data.lote);
            setMarcados({});
        } catch (error) {
            setAviso(mensajeError(error));
        } finally {
            setOcupado(false);
        }
    };

    const alternar = (id, campo, valor) => {
        setMarcados((prev) => ({
            ...prev,
            [id]: { ...(prev[id] || {}), [campo]: valor },
        }));
    };

    const confirmar = async () => {
        if (!lote) return;
        const archivos = (lote.archivos || []).flatMap((archivo) => {
            const marca = marcados[archivo.id] || {};
            if (marca.descartar) {
                return [{ id: archivo.id, accion: 'descartar' }];
            }
            if (!marca.confirmar) return [];
            return [{
                id: archivo.id,
                accion: 'confirmar',
                producto_id: marca.producto_id || archivo.producto_id,
                confirmar_repetido: archivo.clasificacion === 'repetido' ? true : undefined,
            }];
        });
        if (archivos.length === 0) {
            setAviso('Seleccione los archivos que quiere asociar o descartar.');
            return;
        }
        setOcupado(true);
        setAviso('');
        try {
            const respuesta = await axios.post(route('gestion_interna.productos.imagenes.lotes.confirmar', lote.uuid), { archivos });
            setResultados(respuesta.data.archivos || []);
            const fresco = await axios.get(route('gestion_interna.productos.imagenes.lotes.mostrar', lote.uuid));
            setLote(fresco.data.lote);
        } catch (error) {
            setAviso(mensajeError(error));
        } finally {
            setOcupado(false);
        }
    };

    const archivos = lote?.archivos || [];

    return (
        <section className={geliaCardClass('p-4 space-y-3')}>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 className="text-sm font-black uppercase theme-text-main">Carga múltiple de imágenes</h2>
                    <p className="text-[11px] font-bold theme-text-muted">El nombre debe incluir el folio. Nada se asocia hasta confirmar la vista previa.</p>
                </div>
                <label className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact cursor-pointer`}>
                    Elegir archivos
                    <input type="file" accept="image/jpeg,image/png,image/webp" multiple className="sr-only" onChange={analizar} disabled={ocupado} />
                </label>
            </div>
            {ocupado && <p className="text-[11px] font-bold theme-text-muted">Procesando archivos…</p>}
            {aviso && <p className="text-[11px] font-bold theme-text-aviso" role="alert">{aviso}</p>}
            {archivos.length > 0 && (
                <div className="overflow-x-auto">
                    <table className="w-full text-left min-w-[720px]">
                        <thead>
                            <tr className="border-b theme-border text-[10px] font-black uppercase theme-text-muted">
                                <th className="px-2 py-2">Archivo</th>
                                <th className="px-2 py-2">Propuesta</th>
                                <th className="px-2 py-2">Asociar</th>
                                <th className="px-2 py-2">Descartar</th>
                            </tr>
                        </thead>
                        <tbody>
                            {archivos.map((archivo) => (
                                <tr key={archivo.id} className="border-b theme-border text-[11px] font-bold">
                                    <td className="px-2 py-2">{archivo.nombre_original}</td>
                                    <td className="px-2 py-2">
                                        <span className="block">{archivo.motivo || (archivo.estado === 'listo' ? `Folio ${archivo.folio_detectado}` : archivo.estado)}</span>
                                        {archivo.apoyo_sku && <span className="block theme-text-muted">{archivo.apoyo_sku}</span>}
                                        {archivo.clasificacion === 'ambiguo' && (
                                            <select className="theme-input mt-1 px-2 py-1 text-[11px]" value={marcados[archivo.id]?.producto_id || ''} onChange={(evento) => alternar(archivo.id, 'producto_id', Number(evento.target.value))}>
                                                <option value="">Elegir folio</option>
                                                {(archivo.candidatos || []).map((candidato) => (
                                                    <option key={candidato.producto_id} value={candidato.producto_id}>{candidato.folio} · {candidato.sku}</option>
                                                ))}
                                            </select>
                                        )}
                                    </td>
                                    <td className="px-2 py-2">
                                        <input type="checkbox" checked={Boolean(marcados[archivo.id]?.confirmar)} onChange={(evento) => alternar(archivo.id, 'confirmar', evento.target.checked)} disabled={archivo.clasificacion === 'invalido' || archivo.clasificacion === 'sin_folio' || archivo.estado === 'completado'} aria-label={`Asociar ${archivo.nombre_original}`} />
                                    </td>
                                    <td className="px-2 py-2">
                                        <input type="checkbox" checked={Boolean(marcados[archivo.id]?.descartar)} onChange={(evento) => alternar(archivo.id, 'descartar', evento.target.checked)} disabled={archivo.estado === 'completado'} aria-label={`Descartar ${archivo.nombre_original}`} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <button type="button" className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact mt-3`} onClick={confirmar} disabled={ocupado}>Confirmar selección</button>
                </div>
            )}
            {resultados.length > 0 && (
                <ul className="space-y-1">
                    {resultados.map((resultado) => (
                        <li key={resultado.id} className="text-[11px] font-bold theme-text-main">
                            {resultado.nombre_original}: {resultado.estado}{resultado.motivo ? ` — ${resultado.motivo}` : ''}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
