# Posibles Implementaciones/cambios en el sistema de CONTROL DE ACTIVOS 


# Firts Propuesta de mejora: Registro de accesorios incluidos en un activo
Objetivo: Permitir que los accesorios incluidos con un equipo puedan registrarse dentro de su misma ficha, sin necesidad de crear un activo independiente para cada uno.

Funcionamiento propuesto:

1. Accesorios incluidos: agregar un apartado opcional en el formulario de registro y edición de activos para capturar accesorios como cargadores, mouse, mochilas, cables, etc.
2. Ficha del activo: mostrar los accesorios registrados dentro del detalle del equipo principal, sin generarles folios individuales.
3. Asignación: al asignar el equipo a un colaborador, los accesorios incluidos formarán parte de la misma entrega.
4. Responsiva: incluir el listado de accesorios en el documento que firma el colaborador, dejando constancia de todo lo recibido.
5. Accesorios independientes: conservar la función actual para registrar accesorios por separado cuando requieran control individual, asignación o seguimiento propio.

Consideraciones: La implementación deberá respetar los procesos existentes de asignación, devolución, transferencia e historial. Antes de desarrollarla, será necesario analizar cómo se almacenará esta información y cómo se integrará a las responsivas sin afectar otros módulos ni los registros existentes.

- Estado: Propuesta de mejora pendiente de análisis e implementación.

# 2. Mejora en la selección de departamento y responsable
Objetivo: Evitar asignaciones incorrectas durante el registro de activos.

Funcionamiento propuesto:
1. Seleccionar primero el departamento al que pertenecerá el activo.
2. Habilitar posteriormente el campo «Asignar a (opcional)», mostrando únicamente colaboradores vinculados al departamento seleccionado.
3. Si se cambia el departamento, limpiar automáticamente el responsable seleccionado para evitar inconsistencias.
4. Aplicar la misma validación cuando se utilice el registro continuo.
5. Mantener la posibilidad de registrar activos sin responsable, dejándolos en estado Disponible.

Consideraciones: Conservar las validaciones existentes del servidor y limitar los ajustes al formulario de registro, sin afectar otros procesos.

- Estado: Pendiente de implementación.

# 3. Prevención de registros duplicados por reintentos
Objetivo: Evitar que GELIA genere activos duplicados cuando un usuario repite una solicitud de registro debido a un error de comunicación o falta de respuesta.

Funcionamiento propuesto:
1. Identificar cada intento de registro para distinguirlo de una nueva captura.
2. Si el activo se guardó correctamente, pero la respuesta no llegó al usuario, permitir recuperar el resultado original sin generar otro registro.
3. Si el registro falló y se revirtieron los cambios, permitir un nuevo intento de guardado.
4. Garantizar que el registro continuo genere una identificación independiente para cada activo nuevo.
5. Conservar los folios, las asignaciones opcionales y el comportamiento actual del formulario.

Consideraciones: Analizar previamente el mecanismo de identificación de solicitudes y su almacenamiento, evitando modificaciones no autorizadas a la base de datos o a otros módulos.

- Estado: Pendiente de análisis técnico e implementación.

# 4. Mejora en el historial de firmas y responsivas
Objetivo: Garantizar que cada asignación de un activo conserve su propia evidencia de recibido, manteniendo un historial completo de responsables y firmas.

Funcionamiento propuesto:
1. Cada vez que un colaborador reciba un activo, deberá generarse una nueva asignación con su respectiva responsiva para firma.
2. Si el activo se devuelve, transfiere o reasigna, deberá conservarse el historial de las asignaciones y firmas anteriores.
3. El nuevo responsable deberá firmar su propia responsiva, sin reemplazar las firmas anteriores.
4. El activo conservará su folio original durante todo su ciclo de vida, independientemente de los cambios de responsable o departamento.
5. Las firmas y responsivas anteriores deberán permanecer disponibles para consulta dentro del historial del activo.
6. Una firma confirmada no deberá poder modificarse ni sustituirse mediante el proceso normal.

- Estado: Pendiente de revisión e implementación.
