import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, AlertTriangle, Package, Truck } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';
import FormularioEntregaResguardo from './Partials/FormularioEntregaResguardo';
import useEntregaResguardo from './Partials/useEntregaResguardo';
import { BTN_SECONDARY, badgeEstadoResguardo } from './Partials/resguardosStyles';

export default function Entrega({
    auth,
    resguardo,
    catalogos = {},
    puede_entregar: puedeEntregar = false,
    motivo_no_entregable: motivoNoEntregable = null,
}) {
    const titulo = resguardo?.snapshot_folio || `Resguardo #${resguardo?.id}`;
    const {
        enviar,
        enviando,
        progreso,
        error,
        exito,
        resguardoResultado,
        irADetalle,
        irABandeja,
        recargarFormulario,
    } = useEntregaResguardo({
        resguardoId: resguardo.id,
        versionInicial: resguardo.version,
        metodoValidacion: catalogos.metodo_validacion || 'firma',
    });

    const cancelar = () => {
        router.visit(route('punto_venta.resguardos.show', resguardo.id));
    };

    return (
        <AppLayout auth={auth}>
            <Head title={`Entregar ${titulo} | Resguardos PDV`} />
            <GeliaPageShell className="max-w-[720px] space-y-6">
                <Link
                    href={route('punto_venta.resguardos.show', resguardo.id)}
                    className="inline-flex items-center gap-2 text-[10px] font-black uppercase theme-text-muted hover:theme-text-main"
                >
                    <ArrowLeft className="w-4 h-4" /> Volver al detalle
                </Link>

                <div className={`${geliaCardClass()} p-5 space-y-3`}>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="flex items-center gap-2 min-w-0">
                            <Truck className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                            <div className="min-w-0">
                                <h1 className="text-xl font-black italic uppercase theme-text-main m-0 truncate">
                                    Entrega física
                                </h1>
                                <p className="text-sm font-bold theme-text-muted m-0 truncate">{titulo}</p>
                            </div>
                        </div>
                        <span className={`inline-flex px-3 py-1.5 rounded-xl text-[10px] font-black uppercase ${badgeEstadoResguardo(resguardo.estado)}`}>
                            {resguardo.estado_etiqueta || catalogos.estados?.[resguardo.estado]}
                        </span>
                    </div>
                </div>

                {exito ? (
                    <ResultadoExito
                        onDetalle={irADetalle}
                        onBandeja={irABandeja}
                        parcial={resguardoResultado?.estado === 'en_custodia' || resguardoResultado?.estado === 'pendiente_recepcion'}
                    />
                ) : !puedeEntregar ? (
                    <EstadoNoDisponible
                        resguardo={resguardo}
                        catalogos={catalogos}
                        motivo={motivoNoEntregable}
                        onDetalle={irADetalle}
                    />
                ) : (
                    <FormularioEntregaResguardo
                        resguardo={resguardo}
                        catalogos={catalogos}
                        enviando={enviando}
                        progreso={progreso}
                        error={error}
                        onEnviar={enviar}
                        onCancelar={cancelar}
                    />
                )}

                {error && error.includes('modificó este resguardo') && puedeEntregar && !exito && (
                    <button
                        type="button"
                        onClick={recargarFormulario}
                        className={`${BTN_SECONDARY} w-full min-h-[44px]`}
                    >
                        Actualizar datos y reintentar
                    </button>
                )}
            </GeliaPageShell>
        </AppLayout>
    );
}

function ResultadoExito({ onDetalle, onBandeja, parcial = false }) {
    return (
        <div className={`${geliaCardClass()} p-8 text-center space-y-4`}>
            <CheckCircle2 className="w-12 h-12 mx-auto text-emerald-500" />
            <div className="space-y-2">
                <h2 className="text-lg font-black uppercase theme-text-main m-0">
                    {parcial ? 'Entrega parcial registrada' : 'Entrega registrada'}
                </h2>
                <p className="text-sm theme-text-muted m-0">
                    {parcial
                        ? 'Los bultos restantes permanecen en custodia. El pedido no pasa a Entregado hasta completar el resto.'
                        : 'El resguardo quedó en estado Entregado. Puedes revisar el detalle actualizado o volver a la bandeja.'}
                </p>
            </div>
            <div className="flex flex-col sm:flex-row gap-2 justify-center">
                <button type="button" onClick={onDetalle} className={`${THEME_BTN_PRIMARY} min-h-[48px] px-6`}>
                    Ver detalle actualizado
                </button>
                <button type="button" onClick={onBandeja} className={`${BTN_SECONDARY} min-h-[48px] px-6`}>
                    Ir a en custodia
                </button>
            </div>
        </div>
    );
}

function EstadoNoDisponible({ resguardo, catalogos, motivo, onDetalle }) {
    const etiqueta = resguardo.estado_etiqueta || catalogos.estados?.[resguardo.estado] || resguardo.estado;
    const yaEntregado = resguardo.estado === 'entregado';

    return (
        <div className={`${geliaCardClass()} p-5 space-y-3 border border-amber-500/30`}>
            <div className="flex items-start gap-3">
                <AlertTriangle className="w-5 h-5 text-amber-500 shrink-0" />
                <div className="space-y-2">
                    <p className="text-sm font-black theme-text-main m-0">
                        {yaEntregado
                            ? 'Este resguardo ya fue entregado.'
                            : motivo || `No se puede entregar en estado «${etiqueta}».`}
                    </p>
                    {(yaEntregado || motivo?.includes('otra terminal') || motivo?.includes('ya fue entregado')) && (
                        <p className="text-sm theme-text-muted m-0">
                            Si otra terminal completó la entrega, consulta el detalle actualizado.
                        </p>
                    )}
                    <button type="button" onClick={onDetalle} className={BTN_SECONDARY}>
                        <Package className="w-4 h-4 inline mr-2" />
                        Ver detalle
                    </button>
                </div>
            </div>
        </div>
    );
}
