import React, { useMemo, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, PackageCheck } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_SELECT } from '../../../utils/geliaTheme';
import { BTN_SECONDARY, badgeEstadoResguardo } from './Partials/resguardosStyles';
import useConfirmacionCustodia from './Partials/useConfirmacionCustodia';

export default function Custodia({
    auth,
    resguardo,
    almacenes = [],
    admite_confirmacion_custodia: admiteConfirmacion = false,
}) {
    const [almacenId, setAlmacenId] = useState(almacenes.length === 1 ? String(almacenes[0].id) : '');
    const [foliosSeleccionados, setFoliosSeleccionados] = useState([]);
    const titulo = resguardo?.snapshot_folio || `Resguardo #${resguardo?.id}`;
    const pendientes = resguardo?.bultos_pendientes_custodia || [];
    const { enviar, enviando, error, exito } = useConfirmacionCustodia({
        resguardoId: resguardo.id,
        versionInicial: resguardo.version,
    });

    const progreso = useMemo(() => {
        const esperada = resguardo?.cantidad_bultos_esperada || 0;
        const enCustodia = resguardo?.cantidad_bultos_en_custodia || 0;
        return `${enCustodia}/${esperada}`;
    }, [resguardo]);

    const toggleFolio = (folio) => {
        setFoliosSeleccionados((prev) => (
            prev.includes(folio) ? prev.filter((item) => item !== folio) : [...prev, folio]
        ));
    };

    const onSubmit = async (e) => {
        e.preventDefault();
        await enviar({
            almacenId: almacenId ? Number(almacenId) : null,
            folios: foliosSeleccionados,
        });
    };

    return (
        <AppLayout auth={auth}>
            <Head title={`Custodia ${titulo}`} />
            <GeliaPageShell className="space-y-5 max-w-3xl mx-auto">
                <div className="flex items-center gap-3">
                    <Link href={route('punto_venta.resguardos.index', { bandeja: 'por_recibir', paso: 'recepcionista' })} className={BTN_SECONDARY}>
                        <ArrowLeft className="w-4 h-4" />
                        Volver
                    </Link>
                    <div>
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Confirmar custodia</p>
                        <h1 className="text-xl font-black m-0">{titulo}</h1>
                    </div>
                    <span className={`ml-auto inline-flex px-2 py-1 rounded-lg text-[9px] font-black uppercase ${badgeEstadoResguardo(resguardo.estado)}`}>
                        {resguardo.estado_etiqueta || resguardo.estado}
                    </span>
                </div>

                <div className={`${geliaCardClass()} p-4`}>
                    <p className="text-sm theme-text-main m-0">
                        Custodia confirmada: <strong>{progreso}</strong> bultos.
                    </p>
                </div>

                {exito ? (
                    <div className={`${geliaCardClass()} p-6 text-center space-y-3`}>
                        <PackageCheck className="w-10 h-10 mx-auto text-emerald-500" />
                        <p className="font-black m-0">Custodia registrada correctamente.</p>
                        <Link href={route('punto_venta.resguardos.show', resguardo.id)} className={THEME_BTN_PRIMARY}>Ver detalle</Link>
                    </div>
                ) : (
                    <form onSubmit={onSubmit} className="space-y-4">
                        <div className={`${geliaCardClass()} p-5 space-y-4`}>
                            <label className="space-y-1.5 block">
                                <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Almacén de custodia</span>
                                <select
                                    value={almacenId}
                                    onChange={(e) => setAlmacenId(e.target.value)}
                                    className={THEME_SELECT}
                                    required
                                    disabled={enviando || !admiteConfirmacion}
                                >
                                    <option value="">Seleccionar ubicación…</option>
                                    {almacenes.map((almacen) => (
                                        <option key={almacen.id} value={almacen.id}>
                                            {almacen.codigo} — {almacen.nombre}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </div>

                        <div className={`${geliaCardClass()} p-5 space-y-3`}>
                            <h2 className="text-sm font-black uppercase tracking-widest m-0">Bultos recibidos por gerencia</h2>
                            {pendientes.length === 0 ? (
                                <p className="text-sm theme-text-muted m-0">No hay bultos pendientes de custodia.</p>
                            ) : pendientes.map((bulto) => (
                                <label key={bulto.id} className="flex items-center gap-3 p-3 rounded-xl theme-element border theme-border">
                                    <input
                                        type="checkbox"
                                        checked={foliosSeleccionados.includes(bulto.folio)}
                                        onChange={() => toggleFolio(bulto.folio)}
                                        disabled={enviando}
                                    />
                                    <span className="font-black">{bulto.folio}</span>
                                    <span className="text-[10px] uppercase theme-text-muted">{bulto.tipo}</span>
                                </label>
                            ))}
                        </div>

                        {error && <p className="text-sm text-red-500 font-semibold m-0">{error}</p>}

                        <button
                            type="submit"
                            className={`${THEME_BTN_PRIMARY} w-full min-h-[48px]`}
                            disabled={enviando || !admiteConfirmacion || foliosSeleccionados.length < 1 || !almacenId}
                        >
                            {enviando ? 'Registrando…' : 'Confirmar custodia'}
                        </button>
                    </form>
                )}
            </GeliaPageShell>
        </AppLayout>
    );
}
