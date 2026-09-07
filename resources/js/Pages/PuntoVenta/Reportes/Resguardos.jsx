import ReportePdvShell from './ReportePdvShell';
import { TIPO_REPORTE_RESGUARDOS } from './reportesPdvUtils';

export default function Resguardos(props) {
    return <ReportePdvShell {...props} tipo_reporte={props.tipo_reporte || TIPO_REPORTE_RESGUARDOS} />;
}
