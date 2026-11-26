# Panel de control

- Propósito: monitorear cotizado, actividad comercial, colas y cartera vencida; los accesos a módulos quedan debajo.
- Ubicación: `GET /dashboard` (`routes/web/core.php`); `app/Http/Controllers/DashboardController.php`.
- Servicios: `app/Services/Dashboard/{ResolverPeriodoDashboard,ObtenerMetricasOperativasDashboardService}.php`; cartera en `app/Services/Cobranza/ResumenCarteraVencidaService.php`.
- Frontend: `resources/js/Pages/Dashboards/Index.jsx`, `resources/js/Components/Dashboard/`, `resources/css/gelia/features/dashboard.css`.
- Gráficas: Chart.js 4.5.1 vendido (`public/vendor/chart.js`, `cargarChartJs`).
- Pruebas: `tests/Unit/Dashboard/`; `dashboardLayoutUtils.test.js`.

## Estado actual

- [verificado] Periodo `hoy|7d|mes|rango` en query; default mes en curso. Credibox no sigue el filtro (cartera viva).
- [verificado] Cotizado y creadas/cerradas se agregan de `solicitudes_tags` con el aislamiento de `ListarSolicitudesService::aislarConsulta`. Desglose por vendedor si hay más de uno; si no, por proceso. No hay sucursal.
- [verificado] Permiso de cotizado = widget solicitudes (`solicitudes.ver_listado` / `gestionar` / `configuracion.ver_auditoria`).
- [verificado] Credibox: saldo, rangos 1–30…+120 y top 5 vencidos. Sin evolución histórica (no hay snapshots).
- [verificado] Tabs Operativo / Comercial / Accesos, Autoajuste, Organizar, Configurar y `dashboard_layout` por usuario se conservan. El bloque analítico queda fuera de la cuadrícula; Accesos lo oculta.
- [verificado] Identidad: Credibox `violet`, RH `indigo`. El rojo queda para alertas (`gelia-estado-vivo--error`).

## Reglas que no se rompen

- No simular métricas ausentes. Colas Tienda/CEDIS/PDV no se duplican aquí.
- Layout guardado a medida no se pisa; Restablecer y Autoajuste usan altos 5 (widgets) y 10 (módulos).
- El CTA del widget va sobre superficie sólida; el cuerpo hace scroll si la celda no alcanza. El rango de fechas usa `theme-surface-solid`.

Última verificación: 2026-11-26, sin commit, árbol de trabajo.
