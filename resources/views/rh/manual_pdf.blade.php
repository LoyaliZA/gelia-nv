<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Manual del Módulo de Recursos Humanos</title>
    <style>
        body { font-family: Helvetica, sans-serif; font-size: 14px; line-height: 1.6; color: #333; margin: 30px; }
        h1 { color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px; font-size: 24px; text-transform: uppercase; }
        h2 { color: #3b82f6; font-size: 18px; margin-top: 30px; }
        p { margin-bottom: 15px; }
        ul { margin-bottom: 15px; padding-left: 20px; }
        li { margin-bottom: 5px; }
        .footer { text-align: center; margin-top: 50px; font-size: 12px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 20px; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>

    <h1>Manual del Módulo de Recursos Humanos (RH)</h1>
    <p>Este documento contiene una explicación detallada del funcionamiento y los componentes del módulo de Recursos Humanos, diseñado para centralizar la gestión de colaboradores, incidencias, deducciones, préstamos y nóminas.</p>

    <h2>1. Colaboradores</h2>
    <p>El núcleo del módulo son los colaboradores. Aquí se gestionan todos los perfiles activos e inactivos de la empresa.</p>
    <ul>
        <li><strong>Información General:</strong> Nombre, apellidos, curp, rfc y datos de contacto.</li>
        <li><strong>Información Laboral:</strong> Salario base mensual, salario diario, bono de puntualidad y productividad.</li>
        <li><strong>Organización:</strong> Asignación a departamentos, áreas y puestos de trabajo.</li>
    </ul>
    
    <h2>2. Horas Extra</h2>
    <p>Este submódulo permite llevar el control de los tiempos adicionales trabajados por el personal.</p>
    <ul>
        <li><strong>Registro de Turnos:</strong> Se registran las horas con su hora de inicio y fin.</li>
        <li><strong>Cálculo Automático:</strong> El sistema calcula el monto económico a pagar basándose en el salario diario y la configuración de pago de horas extra.</li>
        <li><strong>Estados de Pago:</strong> Las horas extra se mantienen como 'Pendientes' hasta que son procesadas y pagadas en la nómina.</li>
    </ul>

    <h2>3. Catálogo y Flujo de Configuración de Incidencias</h2>
    <p>Para estandarizar las sanciones, el sistema utiliza <strong>Reglas de Incidencia</strong>. El flujo completo para configurar y aplicar una incidencia es el siguiente:</p>
    <ol>
        <li><strong>Creación de la Regla:</strong> Un administrador accede a "Configuración" > "Reglas de Incidencia" y crea una nueva regla.</li>
        <li><strong>Definición del Comportamiento:</strong> Selecciona cómo actuará la regla. Puede ser un cobro fijo, un porcentaje del costo de un producto roto, o una deducción directa a la nómina (como retardos).</li>
        <li><strong>Asignación de Factores (Para Nómina):</strong> Si es una deducción de nómina, se establecen los "Factores de penalización". Por ejemplo, un factor de 7.5 en puntualidad significa que el trabajador perderá el equivalente a 7.5 días de su bono diario (Medio bono quincenal).</li>
        <li><strong>Restricciones de Visibilidad:</strong> Se puede configurar para que la regla aplique solo a ciertos departamentos o áreas.</li>
        <li><strong>Aplicación Operativa:</strong> Cuando el colaborador comete la falta, el área correspondiente registra la incidencia seleccionando esta regla. El sistema lee el salario y bonos actuales del trabajador para calcular matemáticamente cuánto se le debe descontar sin intervención manual.</li>
    </ol>

    <h2>4. Deducciones Operativas</h2>
    <p>Es el resultado económico de aplicar una incidencia. Permite penalizar o realizar cobros justificados.</p>
    <ul>
        <li><strong>Registro:</strong> Al crear una deducción, el sistema calcula de manera automática la penalización económica basándose en la regla seleccionada.</li>
        <li><strong>Bonos y Retardos:</strong> Si un retardo hace perder "Medio bono" o el "Bono completo", el sistema lo debita del <strong>Bono de Puntualidad</strong> diario multiplicado por el factor de la regla configurada.</li>
    </ul>

    <h2>5. Salidas Personales</h2>
    <p>Se gestionan los permisos para salir de la oficina en horario laboral por asuntos personales.</p>
    <ul>
        <li>Se contabiliza el tiempo exacto (Hora de salida y regreso).</li>
        <li>Se realiza el descuento económico correspondiente en la nómina (si la configuración lo determina).</li>
        <li>Existe un proceso de "Sellar Periodo" exclusivo para consolidar todas las salidas del mes/quincena y aplicarlas en un solo movimiento.</li>
    </ul>

    <h2>6. Préstamos y Pagos Fijos</h2>
    <p>Permite registrar préstamos económicos a los colaboradores, los cuales se cobrarán en plazos (cuotas) fijos durante cada nómina.</p>
    <ul>
        <li>El sistema deduce automáticamente la cuota estipulada en el periodo de pago activo hasta saldar la deuda.</li>
    </ul>

    <h2>7. Consolidado de Deducciones y Cierre de Periodo</h2>
    <p>La pantalla de Consolidado funciona como una <strong>Pre-Nómina Gerencial</strong>.</p>
    <ul>
        <li><strong>Visualización:</strong> Muestra un resumen general de lo que se debe descontar a cada trabajador: Préstamos, Incidencias Operativas, Faltas de Salario, Retardos (Bonos) y Salidas Personales.</li>
        <li><strong>Sellar Periodo:</strong> Es el proceso crítico donde se "cierra" la quincena. El sistema marca las deducciones como aplicadas.</li>
        <li><strong>Arrastre Automático:</strong> Si las penalizaciones de los bonos superan el monto máximo posible del bono para la quincena actual, el sistema topa el descuento a la cantidad máxima, y genera un <strong>nuevo registro de deducción</strong> automático para el siguiente periodo (arrastre).</li>
    </ul>

    <div class="page-break"></div>

    <h2>8. Periodo de Pago</h2>
    <p>Esta vista proporciona el desglose final y neto que el colaborador deberá cobrar, tomando en cuenta percepciones (Salario base, bonos, horas extra) menos las deducciones aplicadas.</p>
    <p>El periodo de pago se calcula de manera dinámica basándose en la selección de fechas y define el comportamiento matemático de los topes de nómina para los arrastres.</p>

    <div class="page-break"></div>

    <h2>Diccionario de Términos (Glosario)</h2>
    <p>Para comprender completamente el módulo, es crucial conocer la terminología utilizada por el sistema:</p>
    <ul>
        <li><strong>Incidencia:</strong> Evento, reporte o falta cometida por un colaborador. Es el "acto" que se registra en el sistema.</li>
        <li><strong>Deducción:</strong> Es la traducción "monetaria" de una incidencia. Es el cargo que se reflejará como descuento en la nómina.</li>
        <li><strong>Catálogo de Reglas:</strong> Es la base de datos de "tipos de sanciones". Estandariza los castigos para evitar que los cobros dependan del criterio de una sola persona.</li>
        <li><strong>Factor de Penalización:</strong> Multiplicador utilizado en las reglas. Define cuántos "días" de un concepto (salario o bono) perderá el empleado al cometer una incidencia. (Ej. Factor 15 = 15 días perdidos).</li>
        <li><strong>Arrastre (Rollover):</strong> Proceso automático que ocurre cuando la deuda de un empleado supera el monto de su bono actual. La deuda sobrante "se arrastra" como una deducción nueva para su próxima quincena.</li>
        <li><strong>Consolidado (Pre-nómina):</strong> Pantalla gerencial que suma todo lo que se le va a descontar a los empleados en el rango de fechas seleccionado, previo a enviarse a pago.</li>
        <li><strong>Sellar Periodo:</strong> Acción definitiva que bloquea las deducciones mostradas, las marca como "cobradas" en la quincena actual, y ejecuta los arrastres necesarios para el próximo ciclo.</li>
    </ul>

    <div class="footer">
        Generado por el Sistema ERP &bull; <?php echo date('d/m/Y H:i:s'); ?>
    </div>

</body>
</html>
