import React from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass, THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';
import FormularioConfirmarRecepcionResguardo from './Partials/FormularioConfirmarRecepcionResguardo';
import useRecepcionFisica from './Partials/useRecepcionFisica';
import { mensajeEstadoNoRecepcion, resguardoAdmiteRecepcion } from './Partials/recepcionFisicaUtils';

export default function Recepcion({ auth, resguardo, catalogos = {}, flujo_completo = false, admite_recepcion = false, motivo_no_recepcion }) {
    const { enviar, enviando, progreso, error, exito, irADetalle, irABandeja, recargarFormulario } = useRecepcionFisica({ resguardoId: resguardo.id, versionInicial: resguardo.version });
    const disponible = resguardoAdmiteRecepcion(resguardo, admite_recepcion);
    const motivo = mensajeEstadoNoRecepcion({ motivo: motivo_no_recepcion, resguardo, catalogos });
    return <AppLayout auth={auth}>
        <Head title={`Recepción física ${resguardo.snapshot_folio || resguardo.id}`} />
        <GeliaPageShell className="max-w-[720px] space-y-5" data-recepcion-movil-root>
            <h1 className="theme-text-main text-xl font-bold">Recepción física</h1>
            {exito ? <div className={`${geliaCardClass()} p-5 space-y-3`}>
                <h2 className="theme-text-main font-bold">Llegada registrada</h2>
                <p className="theme-text-muted">Se registraron los bultos seleccionados. Los pendientes siguen en tránsito. La custodia se confirma por separado.</p>
                <button className={THEME_BTN_PRIMARY} onClick={irADetalle}>Ver detalle actualizado</button>
                <button className={THEME_BTN_PRIMARY} onClick={irABandeja}>Volver a bandeja</button>
            </div> : disponible ? <FormularioConfirmarRecepcionResguardo resguardo={resguardo} flujoCompleto={flujo_completo} enviando={enviando} progreso={progreso} error={error} onEnviar={enviar} />
                : <div className={`${geliaCardClass()} p-5 theme-text-muted`}><p>{motivo.titulo}</p><p>{motivo.detalle}</p></div>}
            {error?.includes('modificó este resguardo') && <button className={THEME_BTN_PRIMARY} onClick={recargarFormulario}>Actualizar datos y reintentar</button>}
        </GeliaPageShell>
    </AppLayout>;
}
