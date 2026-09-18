import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, PackageCheck } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass, THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';
import { BTN_SECONDARY, badgeEstadoResguardo } from './Partials/resguardosStyles';
import useConfirmacionCustodia from './Partials/useConfirmacionCustodia';
import FormularioCustodiaBultos from './Partials/FormularioCustodiaBultos';

export default function Custodia({
    auth,
    resguardo,
    almacenes = [],
    catalogos = {},
    admite_confirmacion_custodia: admiteConfirmacion = false,
}) {
    const titulo = resguardo?.snapshot_folio || `Resguardo #${resguardo?.id}`;
    const { enviar, enviando, error, exito } = useConfirmacionCustodia({
        resguardoId: resguardo.id,
        versionInicial: resguardo.version,
    });

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
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Revisión en recepción</p>
                        <h1 className="text-xl font-black m-0">{titulo}</h1>
                    </div>
                    <span className={`ml-auto inline-flex px-2 py-1 rounded-lg text-[9px] font-black uppercase ${badgeEstadoResguardo(resguardo.estado)}`}>
                        {resguardo.estado_etiqueta || resguardo.estado}
                    </span>
                </div>

                {exito ? (
                    <div className={`${geliaCardClass()} p-6 text-center space-y-3`}>
                        <PackageCheck className="w-10 h-10 mx-auto text-emerald-500" />
                        <p className="font-black m-0">Custodia registrada correctamente.</p>
                        <Link href={route('punto_venta.resguardos.show', resguardo.id)} className={THEME_BTN_PRIMARY}>Ver detalle</Link>
                    </div>
                ) : !admiteConfirmacion ? (
                    <div className={`${geliaCardClass()} p-5`}>
                        <p className="text-sm theme-text-main m-0">
                            Este resguardo no admite confirmación de custodia en su estado actual.
                        </p>
                    </div>
                ) : almacenes.length === 0 ? (
                    <div className={`${geliaCardClass()} p-5`}>
                        <p className="text-sm theme-text-main m-0">
                            No hay almacenes activos en esta sucursal para registrar custodia.
                        </p>
                    </div>
                ) : (
                    <FormularioCustodiaBultos
                        resguardo={resguardo}
                        almacenes={almacenes}
                        catalogos={catalogos}
                        enviando={enviando}
                        error={error}
                        onEnviar={enviar}
                    />
                )}
            </GeliaPageShell>
        </AppLayout>
    );
}
