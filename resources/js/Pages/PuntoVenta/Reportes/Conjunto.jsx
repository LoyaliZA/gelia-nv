import ReportePdvShell from './ReportePdvShell';
import { TIPO_REPORTE_CONJUNTO } from './reportesPdvUtils';

export default function Conjunto(props) {
    return <ReportePdvShell {...props} tipo_reporte={props.tipo_reporte || TIPO_REPORTE_CONJUNTO} />;
}
