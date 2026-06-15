<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .container { background-color: #ffffff; padding: 20px; border-radius: 8px; max-width: 600px; margin: 0 auto; border-top: 5px solid #EF4444; }
        h2 { color: #EF4444; }
        p { color: #555555; line-height: 1.5; }
        .alert { background-color: #fef2f2; color: #991b1b; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #f87171; font-weight: bold; }
        .footer { margin-top: 20px; font-size: 12px; color: #999999; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Alerta: Sincronización Fallida</h2>
        <p>El proceso de actualización masiva de precios en WooCommerce no finalizó correctamente.</p>

        <div class="alert">
            Estado Final del Proceso #{{ $log->id }}: {{ strtoupper($log->estado) }}
            @if($log->mensaje_error)
                <br><span style="font-weight: normal;">{{ $log->mensaje_error }}</span>
            @endif
        </div>

        <p>Ingresa al panel de GELIANV para revisar los logs de auditoría o reiniciar el proceso.</p>
        <br>
        <p>Saludos,<br><strong>GELIANV</strong></p>
    </div>
    <div class="footer">Este es un mensaje automático de alerta crítica.</div>
</body>
</html>
