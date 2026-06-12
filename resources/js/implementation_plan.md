# Configuración Global del Periodo de Pago Actual

El usuario ha solicitado agregar un botón "Configurar Periodo de Pago actual" en el encabezado de la configuración de pago (Periodo de Pago), que permita establecer el rango de fechas a pagar. Estas fechas deben ser funcionales y afectar a todos los cálculos del sistema (Horas Extra, Préstamos, etc.) de forma global.

## User Review Required
> [!IMPORTANT]
> Se añadirá una nueva migración para guardar estas fechas en la base de datos. Una vez que apruebes el plan y yo genere el código, **tú deberás ejecutar** `artisan migrate` (usando tu comando de Sail) tal como dictan tus reglas globales.

## Proposed Changes

### Base de Datos y Modelo
#### [NEW] `database/migrations/[timestamp]_add_periodo_actual_to_rh_configuraciones.php`
- Se creará una migración para añadir las columnas `periodo_actual_inicio` (date) y `periodo_actual_fin` (date) a la tabla `rh_configuraciones`.

#### [MODIFY] `app/Models/RhConfiguracion.php`
- Añadir los nuevos campos a la propiedad `$fillable` y a `$casts` para que se manejen correctamente como fechas.
- En el método `obtener()`, establecer valores por defecto (ej. el inicio y fin del mes actual) si la configuración se crea por primera vez.

### Controladores y Lógica
#### [MODIFY] `routes/web.php`
- Añadir una nueva ruta POST `/rh/configuracion/periodo-actual` apuntando a un nuevo método en `ConfiguracionRhController`.

#### [MODIFY] `app/Http/Controllers/Rh/ConfiguracionRhController.php`
- Crear el método `updatePeriodoActual` que reciba `fecha_inicio`, `fecha_fin` y `dias_periodo_pago`, actualice el modelo `RhConfiguracion` y redirija de vuelta.

#### [MODIFY] `app/Http/Controllers/Rh/PeriodoPagoController.php`
- Modificar el método `index` para que, si el usuario no proporciona un filtro explícito en la URL, los valores por defecto de `$fechaInicio` y `$fechaFin` se tomen de `RhConfiguracion::obtener()->periodo_actual_inicio` y `periodo_actual_fin` en lugar de calcularlos basados en el día de hoy.

#### [MODIFY] `app/Http/Controllers/Rh/ConsolidadoHorasExtraController.php`
- Modificar el método `index` para que también utilice estas nuevas fechas de la configuración por defecto.

### Interfaz de Usuario
#### [MODIFY] `resources/js/Pages/Rh/PeriodoPago/Index.jsx`
- Añadir el botón "Configurar Periodo de Pago actual" en el encabezado (header oscuro), junto a los botones de *Generar cuotas* y *Sellar salidas*.
- Crear un pequeño componente/modal (o despliegue en línea) que se abra al hacer clic en dicho botón. Este modal permitirá elegir la `fecha_inicio` y `fecha_fin`. Al cambiar cualquiera de ellas, se calculará y mostrará los días del periodo.
- Al guardar en el modal, se enviará la petición POST a la nueva ruta y la página se actualizará reflejando globalmente el nuevo periodo.
- Remover el input suelto de "Días de Periodo" de la barra de filtros inferiores si ya se establece globalmente, o adaptarlo para que cambie la fecha local solo si se quiere simular sin guardar. (Lo dejaré como filtro local para simulación, pero el botón de arriba cambiará la configuración raíz de toda la empresa).

## Verification Plan
### Manual Verification
- Al cargar la página, las fechas mostradas por defecto serán las configuradas globalmente.
- Al abrir el modal y cambiar el periodo, toda la nómina estimada y el consolidado de horas extras se basarán en esas nuevas fechas por defecto, al igual que la generación de cuotas.
- Instruiré al usuario para ejecutar `./vendor/bin/sail artisan migrate` al finalizar.
