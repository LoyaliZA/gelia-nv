<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .container { background-color: #ffffff; padding: 20px; border-radius: 8px; max-width: 600px; margin: 0 auto; border-top: 5px solid #10B981; }
        h2 { color: #10B981; }
        p { color: #555555; line-height: 1.5; }
        .stats { background-color: #f8fafc; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #e2e8f0; }
        .footer { margin-top: 20px; font-size: 12px; color: #999999; text-align: center; }
        
        /* Estilos para la tabla de reporte */
        .table-custom { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        .table-custom th, .table-custom td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .table-custom th { background-color: #f2f2f2; color: #333; }
    </style>
</head>
<body>
    <div class="container">
        <h2>✅ Sincronización Completada</h2>
        <p>El proceso de actualización masiva de precios en WooCommerce ha finalizado correctamente en el servidor de <strong>Gelia Hub</strong>.</p>
        
        <div class="stats">
            <p><strong>ID de Proceso:</strong> #{{ $log->id }}</p>
            <p><strong>Productos Procesados:</strong> {{ $log->procesados }} de {{ $log->total_productos }}</p>
            <p><strong>Fecha:</strong> {{ $log->updated_at->format('d/m/Y H:i:s') }}</p>
        </div>

        <!-- Extracción y renderizado de productos actualizados -->
        @php
            $detalles = \App\Models\WoocommerceSyncDetail::where('sync_log_id', $log->id)
                            ->where('estado', 'exito')
                            ->where('mensaje', 'Enviado en lote a Woo')
                            ->get();
        @endphp

        @if($detalles->count() > 0)
        <h3 style="color: #333; font-size: 16px; margin-top: 20px;">Detalle de Precios Actualizados ({{ $detalles->count() }})</h3>
        <table class="table-custom">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Normal Nuevo</th>
                    <th>Rebaja Nueva</th>
                </tr>
            </thead>
            <tbody>
                @foreach($detalles as $item)
                <tr>
                    <td><strong>{{ $item->sku }}</strong></td>
                    <td>${{ number_format($item->precio_nuevo_normal, 2) }}</td>
                    <td><span style="color: #10B981;">${{ number_format($item->precio_nuevo_rebajado, 2) }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <p style="margin-top: 20px;">Adjunto a este correo encontrarás el reporte de auditoría completo en formato CSV detallando todos los cambios realizados.</p>
        <br>
        <p>Saludos,<br><strong>GELIA</strong></p>
    </div>
    <div class="footer">Este es un mensaje automático. Por favor no respondas a este correo.</div>
</body>
</html>