import React from 'react';
import { createRoot } from 'react-dom/client';
import '../../../resources/css/app.css';
import ResumenImportacionClientes from '../../../resources/js/Pages/Admin/Partials/ResumenImportacionClientes';
const tema = new URLSearchParams(location.search).get('theme') || 'light';
document.documentElement.classList.toggle('dark', tema === 'dark');
document.body.className = 'theme-bg-base';
createRoot(document.getElementById('root')).render(
    <main className="max-w-6xl mx-auto p-4">
        <ResumenImportacionClientes resultado={{ estado: 'parcial', stats: { leidas: 12, creados: 2, actualizados: 4, sin_cambios: 3, omitidas: 1, errores: 2, activados: 2, clientes_marcados_inactivos: 1, cambios_lista: 2 }, errores_detalle: [{ numero_fila: 8, numero_cliente: '990001', campo: 'codigo_lista', mensaje: 'Código vacío con monto positivo: información contradictoria.' }, { numero_fila: 11, numero_cliente: '990002', campo: 'monto_venta_actual', mensaje: 'Monto inválido: use un decimal no negativo con hasta dos decimales.' }] }} />
    </main>
);
