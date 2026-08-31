import React from 'react';
import { Landmark } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';

export default function VouchersValidadosPlaceholder() {
    return (
        <div className={geliaCardClass('p-10 md:p-14 text-center')}>
            <div className="inline-flex p-4 rounded-2xl theme-element border theme-border mb-4">
                <Landmark className="w-8 h-8" style={{ color: 'var(--color-primario)' }} aria-hidden />
            </div>
            <p className="text-sm font-semibold theme-text-main m-0">Vouchers validados — próximamente</p>
            <p className="text-xs theme-text-muted mt-2 m-0 max-w-lg mx-auto leading-relaxed">
                Este reporte mostrará cada exhibición de pago validada para localizar ingresos en los bancos:
                montos por banco, fechas de movimiento, reporte y validación, y acceso al voucher.
                No presenta conciliación bancaria automática.
            </p>
            <p className="text-xs theme-text-muted mt-3 m-0">
                Mientras tanto, use <strong className="theme-text-main">Pagos por pedido</strong> para revisar cobertura y evidencias.
            </p>
        </div>
    );
}
