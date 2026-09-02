import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, AlertTriangle, Package, PackagePlus } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';
import FormularioRecepcionFisica from './Partials/FormularioRecepcionFisica';
import useRecepcionFisica from './Partials/useRecepcionFisica';
import { BTN_SECONDARY, badgeEstadoResguardo } from './Partials/resguardosStyles';
import {
    cantidadBultosPendiente,
    cantidadBultosRecibida,
    resguardoAdmiteRecepcion,
} from './Partials/recepcionFisicaUtils';

export default function Recepcion({
    auth,
    resguardo,
    almacenes = [],
    catalogos = {},
    puede_recibir: puedeRecibir = false,
}) {
    const titulo = resguardo?.snapshot_folio || `Resguardo #${resguardo?.id}`;
    const admiteRecepcion = resguardoAdmiteRecepcion(resguardo, puedeRecibir);
    const {
        enviar,
        enviando,
        progreso,
        error,
        exito,
        llegadaParcial,
        irADetalle,
        irABandeja,
        recargarFormulario,
        continuarComplemento,
    } = useRecepcionFisica({
        resguardoId: resguardo.id,
        versionInicial: resguardo.version,
    });

    return (
        <AppLayout auth={auth}>
            <Head title={`Recibir ${titulo} | Resguardos PDV`} />
            <GeliaPageShell className="max-w-[720px] space-y-6">
                <Link
                    href={route('punto_venta.resguardos.index', { bandeja: 'por_recibir' })}
                    className="inline-flex items-center gap-2 text-[10px] font-black uppercase theme-text-muted hover:theme-text-main"
                >
                    <ArrowLeft className="w-4 h-4" /> Volver a bandeja
                </Link>

                <div className={`${geliaCardClass()} p-5 space-y-3`}>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="flex items-center gap-2 min-w-0">
                            <Package className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                            <div className="min-w-0">
                                <h1 className="text-xl font-black italic uppercase theme-text-main m-0 truncate">
                                    {cantidadBultosRecibida(resguardo) > 0 ? 'Llegada complementaria' : 'Recepción física'}
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
                    <ResultadoExito onDetalle={irADetalle} onBandeja={irABandeja} />
                ) : llegadaParcial ? (
                    <ResultadoParcial
                        resguardo={llegadaParcial}
                        onContinuar={continuarComplemento}
                        onDetalle={irADetalle}
                    />
                ) : !admiteRecepcion ? (
                    <EstadoNoDisponible
                        resguardo={resguardo}
                        catalogos={catalogos}
                        onDetalle={irADetalle}
                    />
                ) : almacenes.length === 0 ? (
                    <div className={`${geliaCardClass()} p-5 flex items-start gap-3 border border-amber-500/30`}>
                        <AlertTriangle className="w-5 h-5 text-amber-500 shrink-0" />
                        <div className="space-y-2">
                            <p className="text-sm font-black theme-text-main m-0">
                                No hay almacenes activos en esta sucursal.
                            </p>
                            <p className="text-sm theme-text-muted m-0">
                                Solicita la configuración de ubicación antes de registrar la recepción.
                            </p>
                            <button type="button" onClick={irADetalle} className={BTN_SECONDARY}>
                                Ver detalle del resguardo
                            </button>
                        </div>
                    </div>
                ) : (
                    <FormularioRecepcionFisica
                        resguardo={resguardo}
                        almacenes={almacenes}
                        catalogos={catalogos}
                        enviando={enviando}
                        progreso={progreso}
                        error={error}
                        onEnviar={enviar}
                    />
                )}

                {error && error.includes('modificó este resguardo') && admiteRecepcion && !exito && !llegadaParcial && (
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

function ResultadoExito({ onDetalle, onBandeja }) {
    return (
        <div className={`${geliaCardClass()} p-8 text-center space-y-4`}>
            <CheckCircle2 className="w-12 h-12 mx-auto text-emerald-500" />
            <div className="space-y-2">
                <h2 className="text-lg font-black uppercase theme-text-main m-0">Recepción completa</h2>
                <p className="text-sm theme-text-muted m-0">
                    Todos los bultos quedaron en custodia. Puedes revisar el detalle actualizado o volver a la bandeja.
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

function ResultadoParcial({ resguardo, onContinuar, onDetalle }) {
    const recibida = cantidadBultosRecibida(resguardo);
    const pendiente = cantidadBultosPendiente(resguardo);
    const esperada = resguardo.cantidad_bultos_esperada;

    return (
        <div className={`${geliaCardClass()} p-8 text-center space-y-4 border border-amber-500/30`}>
            <PackagePlus className="w-12 h-12 mx-auto text-amber-500" />
            <div className="space-y-2">
                <h2 className="text-lg font-black uppercase theme-text-main m-0">Llegada registrada</h2>
                <p className="text-sm theme-text-muted m-0">
                    Se guardó esta llegada parcial. Hay {recibida} de {esperada} bulto(s) recibidos
                    {pendiente > 0 ? ` y ${pendiente} pendiente(s).` : '.'}
                </p>
            </div>
            <div className="flex flex-col sm:flex-row gap-2 justify-center">
                {pendiente > 0 && (
                    <button type="button" onClick={onContinuar} className={`${THEME_BTN_PRIMARY} min-h-[48px] px-6`}>
                        Registrar otra llegada
                    </button>
                )}
                <button type="button" onClick={onDetalle} className={`${BTN_SECONDARY} min-h-[48px] px-6`}>
                    Ver detalle actualizado
                </button>
            </div>
        </div>
    );
}

function EstadoNoDisponible({ resguardo, catalogos, onDetalle }) {
    const etiqueta = resguardo.estado_etiqueta || catalogos.estados?.[resguardo.estado] || resguardo.estado;
    const recepcionCompleta = resguardo.recepcion_completa === true;

    return (
        <div className={`${geliaCardClass()} p-5 space-y-3 border border-amber-500/30`}>
            <div className="flex items-start gap-3">
                <AlertTriangle className="w-5 h-5 text-amber-500 shrink-0" />
                <div className="space-y-2">
                    <p className="text-sm font-black theme-text-main m-0">
                        {recepcionCompleta
                            ? 'Este resguardo ya recibió todos los bultos esperados.'
                            : `No se puede recibir en estado «${etiqueta}».`}
                    </p>
                    {recepcionCompleta && (
                        <p className="text-sm theme-text-muted m-0">
                            Si otra terminal completó la recepción, consulta el detalle actualizado.
                        </p>
                    )}
                    <button type="button" onClick={onDetalle} className={BTN_SECONDARY}>
                        Ver detalle
                    </button>
                </div>
            </div>
        </div>
    );
}
