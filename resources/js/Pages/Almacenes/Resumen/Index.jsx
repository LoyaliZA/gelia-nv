import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Warehouse, Boxes, DollarSign, Package, FileSpreadsheet } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { geliaCardClass } from '@/utils/geliaTheme';

export default function Index({ auth, contadores, ultimasImportaciones, sin_sucursales_operables }) {
    const tarjetas = [
        { label: 'Productos', valor: contadores.productos, href: route('gestion_interna.productos.index'), icon: Package },
        { label: 'Almacenes activos', valor: contadores.almacenes, href: route('almacenes.catalogo.index'), icon: Warehouse },
        { label: 'Asignaciones', valor: contadores.asignaciones, href: route('almacenes.cobertura.index'), icon: Boxes },
        { label: 'Costos por almacén', valor: contadores.costos, href: route('almacenes.costos.index'), icon: DollarSign },
    ];

    return (
        <AppLayout auth={auth}>
            <Head title="Almacenes" />
            <div className="max-w-5xl mx-auto p-4 md:p-8 space-y-6">
                <header className={geliaCardClass('p-6')}>
                    <h1 className="text-2xl font-black italic uppercase theme-text-main flex items-center gap-3">
                        <Warehouse className="w-7 h-7" style={{ color: 'var(--color-primario)' }} />
                        Almacenes
                    </h1>
                    <p className="text-[11px] font-bold theme-text-muted mt-2">Resumen del módulo: cobertura, cantidades de referencia, costos e importaciones.</p>
                    {sin_sucursales_operables && (
                        <p className="text-[11px] font-bold text-amber-600 mt-2">Sin sucursales operables asignadas: los listados y almacenes del módulo aparecerán vacíos hasta que te asignen acceso.</p>
                    )}
                </header>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {tarjetas.map((t) => (
                        <Link key={t.label} href={t.href} className={geliaCardClass('p-5 hover:opacity-90 block')}>
                            <t.icon className="w-6 h-6 mb-2" style={{ color: 'var(--color-primario)' }} />
                            <p className="text-[10px] font-black uppercase theme-text-muted">{t.label}</p>
                            <p className="text-2xl font-black theme-text-main">{t.valor}</p>
                        </Link>
                    ))}
                </div>
                <div className={geliaCardClass('p-6')}>
                    <div className="flex justify-between items-center mb-4">
                        <h2 className="text-sm font-black uppercase theme-text-main flex items-center gap-2">
                            <FileSpreadsheet className="w-4 h-4" /> Últimas importaciones
                        </h2>
                        <Link href={route('almacenes.importaciones.index')} className="text-[10px] font-black uppercase theme-text-muted hover:underline">Ver todas</Link>
                    </div>
                    {ultimasImportaciones.length === 0 ? (
                        <p className="text-[11px] font-bold theme-text-muted">Sin cargas recientes.</p>
                    ) : (
                        <ul className="space-y-2 text-[11px] font-bold theme-text-main">
                            {ultimasImportaciones.map((l) => (
                                <li key={l.id}>
                                    <Link href={route('almacenes.importaciones.show', l.id)} className="hover:underline">
                                        #{l.id} — {l.tipo} — {l.estado}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
