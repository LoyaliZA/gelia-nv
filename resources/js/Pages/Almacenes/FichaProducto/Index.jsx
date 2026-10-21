import { Head, Link, router } from '@inertiajs/react';
import { Package } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaPaginacion from '@/Components/GeliaPaginacion';
import GaleriaProducto from '@/Components/Productos/GaleriaProducto';
import { geliaCardClass } from '@/utils/geliaTheme';

function textoExistencia(fila) {
    if (fila.estado_existencia === 'sin_registro') return 'Sin registro de inventario';
    if (fila.estado_existencia === 'nula') return 'Existencia sin informar';
    if (fila.estado_existencia === 'cero') return '0';

    return fila.existencia;
}

export default function Index({
    maestro,
    presencia,
    incluye_costos: incluyeCostos,
    incluye_ventas: incluyeVentas,
    galeria,
    puede_gestionar_imagenes: puedeGestionar,
    retorno,
}) {
    const filas = presencia?.data || [];

    const irPresencia = (pagina) => {
        router.get(route('almacenes.productos.ficha', {
            producto: maestro.id,
            ...retorno?.params,
            desde: retorno?.desde,
            presencia_page: pagina,
            galeria_page: galeria?.current_page,
        }), {}, { preserveScroll: true, preserveState: true });
    };

    return (
        <AppLayout>
            <Head title={maestro?.nombre || 'Ficha de producto'} />
            <div className="max-w-[1400px] mx-auto p-4 md:p-8 space-y-6">
                <header className={geliaCardClass('p-6 space-y-3')}>
                    <Link href={route(retorno.ruta, retorno.params)} className="text-[11px] font-black uppercase theme-text-primario">
                        {retorno.rotulo}
                    </Link>
                    <h1 className="text-2xl font-black uppercase theme-text-main flex items-center gap-3">
                        <Package className="w-7 h-7 theme-text-primario" />
                        {maestro.nombre}
                    </h1>
                    <p className="text-[11px] font-bold theme-text-muted">
                        Folio {maestro.folio} · SKU {maestro.sku}
                        {maestro.marca ? ` · ${maestro.marca}` : ''}
                        {maestro.categoria ? ` · ${maestro.categoria}` : ''}
                        {maestro.activo ? '' : ' · Inactivo'}
                    </p>
                    {maestro.descripcion_corta && <p className="text-sm theme-text-main">{maestro.descripcion_corta}</p>}
                </header>

                <section className={geliaCardClass('p-4 space-y-3')}>
                    <h2 className="text-sm font-black uppercase theme-text-main">Presencia por almacén</h2>
                    {filas.length === 0 ? (
                        <p className="text-[11px] font-bold theme-text-muted">No hay asignación, inventario ni costo en los almacenes operables.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left min-w-[760px]">
                                <thead>
                                    <tr className="border-b theme-border text-[10px] font-black uppercase theme-text-muted">
                                        <th className="px-3 py-2">Almacén</th>
                                        <th className="px-3 py-2">Asignación</th>
                                        <th className="px-3 py-2">Inventario</th>
                                        {incluyeCostos && <th className="px-3 py-2">Costo</th>}
                                        {incluyeCostos && <th className="px-3 py-2">Precio</th>}
                                        {incluyeVentas && <th className="px-3 py-2">Ventas</th>}
                                    </tr>
                                </thead>
                                <tbody>
                                    {filas.map((fila) => (
                                        <tr key={fila.almacen_id} className="border-b theme-border text-[11px] font-bold">
                                            <td className="px-3 py-2">
                                                <span className="block theme-text-main">{fila.codigo}</span>
                                                <span className="theme-text-muted">{fila.nombre}</span>
                                            </td>
                                            <td className="px-3 py-2">{fila.asignado ? 'Asignado' : 'Sin asignar'}</td>
                                            <td className="px-3 py-2">{textoExistencia(fila)}</td>
                                            {incluyeCostos && <td className="px-3 py-2">{fila.tiene_costo ? fila.costo : 'Sin costo'}</td>}
                                            {incluyeCostos && <td className="px-3 py-2">{fila.tiene_costo && fila.precio_venta != null ? fila.precio_venta : '—'}</td>}
                                            {incluyeVentas && (
                                                <td className="px-3 py-2">
                                                    {(fila.ventas || []).length === 0 ? '—' : fila.ventas.map((venta) => (
                                                        <span key={venta.periodo} className="block">{venta.periodo}: {venta.monto_venta}</span>
                                                    ))}
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <GeliaPaginacion paginator={presencia} onIrAPagina={irPresencia} embedded />
                </section>

                <section className={geliaCardClass('p-4')}>
                    <GaleriaProducto
                        productoId={maestro.id}
                        galeria={galeria}
                        puedeGestionar={puedeGestionar}
                        retorno={retorno}
                    />
                </section>
            </div>
        </AppLayout>
    );
}
