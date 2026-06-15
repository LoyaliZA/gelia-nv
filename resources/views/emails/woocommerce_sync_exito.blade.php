<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .container { background-color: #ffffff; padding: 20px; border-radius: 8px; max-width: 700px; margin: 0 auto; border-top: 5px solid #10B981; }
        h2 { color: #10B981; }
        p { color: #555555; line-height: 1.5; }
        .stats { background-color: #f8fafc; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #e2e8f0; }
        .footer { margin-top: 20px; font-size: 12px; color: #999999; text-align: center; }
        .table-custom { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 12px; }
        .table-custom th, .table-custom td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .table-custom th { background-color: #f2f2f2; color: #333; }
        .anterior { color: #94a3b8; text-decoration: line-through; }
        .nuevo { color: #059669; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Sincronización Completada</h2>
        <p>El proceso de actualización masiva de precios en WooCommerce ha finalizado correctamente en <strong>GELIANV</strong>.</p>

        <div class="stats">
            <p><strong>ID de Proceso:</strong> #{{ $log->id }}</p>
            <p><strong>Productos Procesados:</strong> {{ $log->procesados }} de {{ $log->total_productos }}</p>
            <p><strong>Fecha:</strong> {{ $log->updated_at->format('d/m/Y H:i:s') }}</p>
        </div>

        @php
            $detalles = \App\Models\Woocommerce\WoocommerceSyncDetail::where('sync_log_id', $log->id)
                ->where('estado', 'exito')
                ->where('mensaje', 'Enviado en lote a Woo')
                ->get();
        @endphp

        @if($detalles->count() > 0)
        <h3 style="color: #333; font-size: 16px; margin-top: 20px;">Detalle de Cambios de Precio ({{ $detalles->count() }})</h3>
        <table class="table-custom">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Normal Anterior</th>
                    <th>Normal Nuevo</th>
                    <th>Rebaja Anterior</th>
                    <th>Rebaja Nuevo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($detalles as $item)
                <tr>
                    <td><strong>{{ $item->sku }}</strong></td>
                    <td class="anterior">${{ number_format($item->precio_anterior_normal ?? 0, 2) }}</td>
                    <td class="nuevo">${{ number_format($item->precio_nuevo_normal ?? 0, 2) }}</td>
                    <td class="anterior">${{ number_format($item->precio_anterior_rebajado ?? 0, 2) }}</td>
                    <td class="nuevo">${{ number_format($item->precio_nuevo_rebajado ?? 0, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <p style="margin-top: 20px;">Adjunto encontrarás el reporte de auditoría completo en CSV con todos los cambios realizados.</p>
        <br>
        <p>Saludos,<br><strong>GELIANV</strong></p>
    </div>
    <div class="footer">Este es un mensaje automático. Por favor no respondas a este correo.</div>
</body>
</html>
