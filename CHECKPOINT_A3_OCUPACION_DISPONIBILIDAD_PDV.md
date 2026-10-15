# GELIA — Checkpoint A3: ocupación, disponibilidad y productividad operativa PDV

Fecha: 14 de octubre de 2026.

## 1. Resumen ejecutivo

**A3 queda parcialmente pendiente: diagnóstico concluido, O-06/O-07 no certificados
como proporciones operativas.** Su reparación exige definir la base temporal y la
fuente de atención/disponibilidad. No se modificaron fórmulas ni servicios de producción.

Hallazgos confirmados:

- El denominador utiliza solo jornadas cerradas, con duración completa y atribución
  por apertura; el numerador suma intervalos recortados de jornadas abiertas y cerradas.
  Las pruebas reproducen **500 %** en ocupación y disponibilidad.
- La atención activa está acreditada por `TurnoPdvAtencion`. El inicio real inspeccionado
  no crea un intervalo `en_atencion`; puede coexistir con un intervalo `disponible`.
  Dos atenciones cerradas reproducen **0 % de ocupación y 100 % de disponibilidad**.
- El acumulado `disponible` no equivale a disponibilidad efectiva: puede abarcar
  atención, espera de un turno asignado y enfriamiento entre turnos.
- Los intervalos cerrados pueden solaparse sin que el reporte resuelva el conflicto.
  Un ejemplo sintético de doble conteo produce **175 %** de ocupación.
- La reactivación real de una jornada puede sobrescribir el último intervalo y borrar
  `cierre_at`. Una consulta histórica pasa de cuatro horas disponibles a cero después
  de reactivar. A2 recorta extremos conservados, pero no recupera valores sobrescritos.

Se añadieron pruebas y este informe. Resultado final: **30 casos ejecutados,
98 aserciones, 20 satisfactorios y 10 incompletos, sin errores ni fallos**. Los
incompletos documentan problemas reproducidos pendientes de autorización; no son
indicadores corregidos ni pruebas satisfactorias.

## 2. Archivos y servicios inspeccionados

Rutas relativas a la raíz del proyecto; inspección limitada a medición operativa y
dependencias necesarias:

| Área | Archivos |
| --- | --- |
| Modelos | `app/Models/PuntoVenta/{JornadaPdv,IntervaloOperativoPdv,TurnoPdv,TurnoPdvAtencion,MotivoPausaPdv}.php` |
| Reportes | `app/Services/PuntoVenta/Reportes/TurnosOperacion/{CalcularMetricasReporteTurnoOperacionPdvService,AplicarFiltrosReporteTurnoOperacionPdvQuery,NormalizarFiltrosReporteTurnoOperacionPdvService}.php` |
| Jornadas | `app/Services/PuntoVenta/Operacion/{AbrirJornadaPdvService,CerrarJornadaPdvService,CompletarCierreJornadaTrasAtencionPdvService,CancelarCierrePendienteJornadaPdvService,ResuelveConcurrenciaJornadaPdv}.php` |
| Atención | `app/Services/PuntoVenta/Turnos/{AsignarTurnoPdvService,IniciarAtencionTurnoPdvService,AplicarInicioAtencionTurnoPdvService,AplicarPlazosAtencionNuevaTurnoPdvService,CerrarAtencionTurnoPdvService,TransferirTurnoPdvService,AsignarReatencionTurnoPdvService,PlazosTurnosPdvConfig}.php` |
| Pausas y disponibilidad | `app/Services/PuntoVenta/Operacion/{IniciarPausaPdvService,FinalizarPausaPdvService,ConsultaMotivosPausaPdvService,ConsultaPersonaDisponiblePdvService,ResolverEstadoVendedorOperacionPdvService,OperacionPdvConfig}.php` |
| Eventos y contratos | `CompletarCierreJornadaTrasAtencionCerrada.php`, eventos de jornada/pausa/turno, `app/Support/PuntoVenta/Operacion/{TipoIntervaloOperativoPdv,EstadoJornadaPdv}.php`, definiciones e identificadores de métricas |
| Persistencia/pruebas | Migración existente de operación, factories de jornada/intervalo/atención, pruebas de métricas de turnos, referencias A2, `tests/TestCase.php`, `phpunit.xml` |

Se buscó específicamente en `app` la escritura de intervalos y referencias a
`TipoIntervaloOperativoPdv::EnAtencion`/`pdv_intervalos_operativos` para comprobar si
existía otro productor. No se localizaron escrituras de ese tipo en el código de
aplicación inspeccionado. No se infiere que jamás existan registros importados o legados.

## 3. Funcionamiento actual de jornadas, intervalos, atención y pausas

### Jornada

- La apertura crea una fila `JornadaPdv` y un intervalo `disponible` iniciado a la
  misma hora. Se registra usuario, sucursal, apertura, estado y versión.
- El cierre escribe `cierre_at` y cierra intervalos abiertos distintos de `en_atencion`.
  Si hay una atención abierta, la jornada queda `CerradaConAtencion`.
- Cuando termina la atención pendiente, el listener completa el cierre y cierra
  intervalos restantes; el servicio no cambia el `cierre_at` escrito originalmente.
  Por ello debe definirse si esa atención posterior integra o no el tiempo operativo.
- Reactivar una jornada cerrada del mismo día reutiliza la fila, deja `cierre_at=null`
  y puede reutilizar el último intervalo, sustituyendo tipo, inicio y final. Cancelar
  un cierre pendiente también puede reutilizar un intervalo. Esto limita el historial.

### Atención

- La asignación crea `TurnoPdvAtencion`: `inicio_at` representa asignación/llamado.
- `atencion_inicio_at` identifica el inicio efectivo registrado. Puede fijarse mediante
  inicio explícito o automáticamente si así lo configura el módulo.
- Una atención activa se reconoce por la fila con `fin_at=null`; el estado operativo
  del vendedor consulta esta condición independientemente del tipo de intervalo.
- Cerrar atención fija `fin_at` y motivo. Transferencia cierra el segmento anterior y
  crea otro; reatención crea otra secuencia. No deben contarse como turnos únicos sin
  deduplicar `turno_id` ni interpretar todas las secuencias como primera atención.
- Estos flujos inspeccionados no cambian el intervalo disponible a `en_atencion`.
  El inicio real se ejercitó en pruebas y confirmó esa ausencia de transición.
- Atención se relaciona con usuario y turno; la sucursal procede del turno. No tiene
  una relación directa `jornada_id`; asociarla a una jornada exige reglas temporales.

### Pausas y disponibilidad

- Tipos existentes: `disponible`, `en_pausa`, `en_atencion`.
- Iniciar pausa exige jornada abierta y disponibilidad; rechaza una atención abierta.
  Cierra el intervalo disponible y crea uno `en_pausa`.
- Finalizar pausa cierra su intervalo y crea uno disponible. Se conservan actores de
  inicio/final, motivo y detalle cuando se exigen. El flujo básico admite motivo nulo;
  no puede inferirse ni distribuirse entre categorías conocidas.
- El selector real de personas disponibles evalúa también atención abierta, pausa,
  aceptación de altas, jornada del día y `disponible_desde` (enfriamiento). O-04 no
  reproduce esa evaluación: suma exclusivamente intervalos etiquetados disponibles.
- Abiertos se extienden hasta el corte para acumulados, con el recorte A2. Una pausa
  olvidada puede parecer pausa de larga duración; es tiempo registrado, no evidencia
  independiente de presencia física o de que toda esa duración fuera intencional.

## 4. Fórmulas actuales y validación matemática

Definiciones para describir el código vigente:

- `W = [desde, hasta)` y corte inclusivo para eventos.
- Para cada intervalo, `s = max(0, min(fin o corte, corte, hasta) - max(inicio, desde))`.
- `Areg`, `Dreg`, `Preg`: suma de `s` para tipos atención, disponible y pausa.
- `Jcerradas`: suma de `cierre_at - apertura_at` de jornadas con duración positiva,
  cerradas al corte y cuya apertura satisface rango/fecha operativa/franja.

| Aspecto | O-06 ocupación | O-07 disponibilidad |
| --- | --- | --- |
| Numerador | `Areg`: intervalos `en_atencion` | `Dreg`: intervalos `disponible` |
| Denominador | `Jcerradas` | `Jcerradas` |
| Fórmula/unidad | `100 × Areg / Jcerradas`, redondeo a 2 decimales; porcentaje | `100 × Dreg / Jcerradas`, redondeo a 2 decimales; porcentaje |
| Población del numerador | Intervalos relacionados con jornadas en alcance, abiertas o cerradas | Igual, filtrados por tipo disponible |
| Población del denominador | Jornadas cerradas al corte, atribuidas por apertura | Igual |
| Exclusiones temporales | Intervalos sin intersección positiva; filtros de fecha operativa/franja por inicio | Igual |
| Abiertos | Numerador hasta `min(corte,hasta)`; jornadas abiertas no aportan denominador | Igual |
| Incompletos | No se certifica cobertura, integridad ni exclusividad; cero intervalos se vuelve cero, no “sin medición” | Igual; no se descuentan atención, espera o enfriamiento contenidos en disponible |
| Denominador cero | `valor=null` | `valor=null` |
| Mayor de 100 % | Posible por población incompatible o superposición; no hay límite artificial | Igual |
| Señal `en_curso` | Jornadas abiertas o intervalos de atención abiertos | Jornadas abiertas o intervalos disponibles abiertos |

Filtrado:

- Usuario/alcance propio limita jornadas y filas de intervalo por `user_id`; sucursal
  delimita jornadas relacionadas. Se verificó el caso de filas coherentes y usuarios
  autorizados, sin certificar registros con usuario/sucursal discordantes.
- El periodo recorta numeradores; **no recorta el denominador**, que hereda las
  duraciones completas de O-02. Cambiar O-02 para arreglar razones rompería el contrato A2.
- Fecha operativa/franja se aplica al inicio del intervalo y a la apertura de la
  jornada, respectivamente: puede producir poblaciones distintas aun con iguales filtros.
- El servicio suma segundos entre usuarios y luego divide: no promedia porcentajes
  individuales. Una futura redefinición debe conservar agregación temporal compatible.

Conceptos que deben mantenerse separados:

| Concepto | Evidencia que puede acreditarlo |
| --- | --- |
| Atención registrada | `atencion_inicio_at` a `fin_at` o corte, con marcas válidas; inicio automático debe reconocerse como tal |
| Pausa registrada | Intervalos `en_pausa`; motivo identificado o explícitamente desconocido |
| Disponible registrado | Intervalos `disponible`; no acredita por sí solo estar libre para asignación |
| Tiempo operativo registrado | Tramos de jornada conservados y válidos; no implica presencia o trabajo efectivo |
| Ocupación | Proporción con atención y base operativa de igual usuario, sucursal y ventana; falta aprobar su definición |
| Productividad | Resultados operativos por tiempo acreditado; no existe aún un contrato aprobado para implementarla |

Disponibilidad y ocupación **no tienen por qué sumar 100 %**. Pausas, espera,
enfriamiento, huecos y superposiciones requieren tratamiento explícito.

## 5. Problemas confirmados y consecuencias

1. **Jornadas abiertas y cerradas mezcladas:** una hora cerrada y cuatro horas abiertas
   aportan cinco horas al numerador, pero una al denominador: 500 %.
2. **Duraciones completas frente a recortadas:** jornada 08–12, reporte 08–10:
   dos horas disponibles / cuatro de jornada = 50 %, aunque todo el tramo consultado
   figure disponible. Es incompatibilidad de bases, no un error de redondeo.
3. **Jornada iniciada antes del rango:** aporta intervalos, pero queda fuera del
   denominador. Aun con datos hay valor nulo por denominador cero.
4. **Atención sin intervalo correspondiente:** O-05 puede dar cero con atención efectiva
   registrada. No debe interpretarse ese cero como ausencia de atención.
5. **Disponible continuo durante atención:** O-07 puede dar 100 % mientras hay
   atenciones. El acumulado acredita una etiqueta de registro, no tiempo libre real.
6. **Solapamientos:** dos intervalos cerrados superpuestos de una persona se suman;
   tampoco se concilia atención con pausas. La restricción de unicidad protege un
   intervalo abierto por usuario/sucursal; no evita solapamientos entre cerrados.
   Los ejemplos superpuestos de pruebas son sintéticos, no evidencia de incidencia
   productiva. El flujo de pausa ordinario sí rechaza atención abierta.
7. **Reactivación destructiva del historial de la fila:** el servicio vigente puede
   cambiar un reporte pasado sin que exista un evento futuro sumado indebidamente.
   Se necesitaría conservar/reconstruir los extremos previos; no se arregla con `min(corte)`.
8. **Franjas/días con inicios diferentes:** una jornada abierta a las 08 y un intervalo
   de las 10 pasan filtros distintos para una franja 10–12. Los cruces de medianoche
   son temporalmente recortables, pero la distribución por día requiere otro contrato.

## 6. Confiabilidad de indicadores actuales

| Indicador | Dictamen |
| --- | --- |
| O-03 tiempo de pausa | Verificable como suma de filas conservadas y recortadas. Condicionado como tiempo transcurrido exclusivo: requiere cobertura, ausencia de duplicación y extremos no sobrescritos |
| O-04 tiempo disponible | Verificable como suma de intervalos etiquetados disponibles. **No confiable como disponibilidad efectiva para atender** |
| O-05 atención por intervalos | Verificable como suma literal de las filas existentes. **No confiable como total de atención real**, por fuente sin productor operativo localizado y posibles solapamientos |
| O-06 ocupación | No certificado: numerador incompleto y base temporal/población incompatibles |
| O-07 disponibilidad | No certificado: fuente no acredita libertad para asignación y base incompatible |
| O-02 duración de jornada, como dependencia | Promedio completo de casos cerrados atribuidos por apertura, conforme A2. No es el tiempo operativo acumulado que necesita una razón recortada |
| T-07 duración de atención, como fuente alternativa | Duración por episodio con inicio/fin válidos; promedio completo de cerrados y abiertos separados. No equivale al acumulado operativo de la ventana ni a turnos únicos |

No se certifican históricos de intervalos sobrescritos, aunque la aritmética del
recorte sea correcta. No se modificaron funciones de resguardos ni su estado pendiente A2.

## 7. Viabilidad de nuevas métricas de productividad — solo análisis

Clasificación: **V** calculable y verificable sobre registros válidos conservados;
**C** calculable con condiciones/limitaciones; **N** no calculable confiablemente con
la definición o trazabilidad actual. No se implementó ninguna de estas propuestas.

| Propuesta | Viabilidad | Fuentes y reglas necesarias |
| --- | --- | --- |
| Total de atención por usuario | C | `TurnoPdvAtencion`, usuario y sucursal del turno; recortar acumulados, distinguir abiertos, decidir unión de solapamientos y relación con jornadas |
| Promedio y mediana de atención | V para episodios cerrados válidos | Diferencias inicio efectivo–fin completas, mismo criterio de atribución y muestra explícita. Interpretación limitada si inicio automático o marcas ausentes |
| Turnos efectivamente atendidos | C | `turno_id` distinto con atención efectiva; acordar si basta inicio, duración positiva o cierre y cómo excluir no presentación/contar transferencias y reatenciones |
| Duración mínima y máxima | V para episodios cerrados válidos | Misma población que promedio; excluir duraciones negativas y distinguir datos ausentes de cero |
| Acumulado de pausas | C | Intervalos `en_pausa`, recorte A2; advertir solapamientos y reactivaciones que puedan sobrescribir una pausa previa |
| Frecuencia de pausas | C | Conteo de intervalos conservados; acordar pausas iniciadas en periodo frente a pausas que lo atraviesan, no contar reintentos como nuevas pausas |
| Motivos de mayor consumo | C | `motivo_pausa_id` y relación `MotivoPausaPdv`; conservar “sin motivo”, controlar renombres del catálogo e integridad temporal |
| Disponible efectivo por jornada | N hoy | `disponible` incluye atención/espera/enfriamiento; se necesita definición y evidencia para excluirlos, con historia suficiente |
| Tiempo efectivo de actividad | C para atención registrada; N como actividad laboral total | Unir atención efectiva válida permite proponer actividad registrada; no medir tareas fuera del sistema ni inventar disponibilidad/actividad en huecos |
| Turnos por hora operativa | N hasta aprobar base/resultado | Numerador de turnos únicos y denominador operativo compatible; no dividir por O-02 o por horas de calendario sin definirlo |
| Horarios de mayor actividad | C | Altas o inicios efectivos por hora local; indicar qué evento mide demanda o atención, y no confundir asignación con inicio real |
| Comparaciones persona/sucursal/periodo | C | Cohortes equivalentes, zona, alcance, cobertura y reglas constantes; las razones actuales O-06/O-07 no habilitan comparaciones confiables |

“Verificable” no certifica presencia física, calidad de servicio ni resultados
comerciales; esas fuentes no forman parte de este checkpoint.

## 8. Pruebas automatizadas y resultados

Nueva clase: `tests/Unit/PuntoVenta/Reportes/OcupacionDisponibilidadReportePdvTest.php`.
Datos sintéticos; algunos escenarios crean intervalos explícitos únicamente para
examinar la fórmula. No se crean ni completan intervalos de datos reales.

| Caso solicitado | Evidencia/resultados |
| --- | --- |
| Jornada abierta al corte | Cuatro horas disponibles registradas, razón nula y señal de abierto; denominador cerrado igual a cero |
| Jornada cerrada dentro del periodo | Cuatro horas de base, una de atención y dos disponibles: 25 % y 50 %; sin exigir suma 100 % |
| Jornada que atraviesa límite | Numerador recortado frente a jornada completa: 50 %; jornada iniciada antes del periodo aporta numerador sin denominador. Diagnósticos incompletos |
| Atención activa sin `en_atencion` | Apertura e inicio real por servicios; vendedor atendiendo, una atención abierta y cero intervalos de atención. Diagnóstico incompleto |
| Atenciones consecutivas | Dos episodios de una hora; O-06=0 %, O-07=100 %. Diagnóstico incompleto |
| Atención/pausa superpuestas | Una hora simultánea entre ambas fuentes; no se presenta su suma como tiempo exclusivo. Diagnóstico incompleto |
| Pausa abierta al corte | Dos horas al corte; al escribir final posterior, mantiene acumulado y proporciones históricas del caso |
| Sin intersección | Intervalo anterior al periodo e intervalo futuro no aportan tiempo |
| Medianoche | Pausa 23–01 aporta una hora al día siguiente; promedio de jornada completa conserva cuatro horas |
| Datos insuficientes | Sin registros, ratios nulos; no inventa tiempo de atención |
| Denominador cero | Jornada sin duración positiva no causa división ni porcentaje inventado |
| Porcentajes superiores a 100 % | Dos casos de 500 % por mezcla de jornadas y uno de 175 % por solapamientos; incompletos |
| Filtros usuario/sucursal | 50 %/100 % por persona, 75 % agregado de la sucursal; otra sucursal excluida y alcance propio verificado sobre datos compatibles |
| Repetición histórica | Cierre futuro conserva valores A2; reactivación real sobrescribe intervalo y cambia cuatro horas a cero. Este último caso queda incompleto |
| Motivo de pausa | Servicios de inicio/final conservan motivo y actores; una hora de pausa y tres disponibles al corte |
| Franja | Jornada 08 y disponible 10 producen poblaciones distintas en 10–12; diagnóstico incompleto |

Ejecuciones:

1. Primera ejecución acotada: **30 casos, 97 aserciones, 1 error de preparación y
   10 incompletos**. El caso de alcance propio no había concedido al usuario sintético
   acceso a su sucursal; el producto rechazó correctamente la consulta. Se corrigió
   exclusivamente la preparación de la nueva clase, concediendo ese acceso.
2. Ejecución final con el mismo comando: **30 casos, 98 aserciones, 0 fallos,
   0 errores, 10 incompletos y 20 satisfactorios**. Duración 23.121 segundos,
   memoria 99 MB. Incluye 19 casos A3 y 11 de métricas de turnos existentes.

Los diez diagnósticos pendientes ejecutan aserciones que reproducen los valores
problemáticos y después llaman `markTestIncomplete` con su limitación. No establecen
esos valores como comportamiento operativo correcto ni se contabilizan como aprobados.
Como no hubo corrección autorizada de producción, no existe una etapa de “error
funcional corregido”: los problemas permanecen reproducidos antes y después.

### Entorno y comando utilizado

PHP 8.4.25 / PHPUnit 12.5.25, imagen local de Sail sin instalación adicional.
Contenedor temporal sin red, repositorio en solo lectura, almacenamiento temporal,
SQLite `:memory:` y entorno `testing`; sin Compose, conexiones o volúmenes productivos.
Las tablas de pruebas solo se crean en memoria. No se añaden migraciones ni se
escribe caché de resultados. Eventos de jornada/pausa y colas se simulan en la clase
para evitar efectos secundarios; los servicios de persistencia ejercitados sí se ejecutan.

```bash
docker run --rm --network none --entrypoint /bin/sh \
  --mount type=bind,source=/home/ti-admin/gelianv,target=/var/www/html,readonly \
  --tmpfs /var/www/html/storage:mode=1777 --tmpfs /tmp:mode=1777 \
  -w /var/www/html \
  -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: \
  -e APP_CONFIG_CACHE=/tmp/gelia-a3-config.php \
  -e APP_EVENTS_CACHE=/tmp/gelia-a3-events.php \
  -e APP_ROUTES_CACHE=/tmp/gelia-a3-routes.php \
  sail-8.4/app:latest -c 'mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs
exec php vendor/bin/phpunit --do-not-cache-result --display-incomplete tests/Unit/PuntoVenta/Reportes/OcupacionDisponibilidadReportePdvTest.php tests/Unit/PuntoVenta/Reportes/CalcularMetricasReporteTurnoOperacionPdvTest.php'
```

No se ejecutaron suites globales ni pruebas de resguardos. PHPUnit devuelve código
0 con incompletos en la configuración actual; no significa validación integral.
Agregar `--fail-on-incomplete` permitiría exigir resolución de estos pendientes para
aprobar automáticamente el checkpoint; no se cambió la configuración del proyecto.

Inspecciones y verificación: búsquedas focalizadas con `rg`, lectura de fuentes,
`git status --short`, `git diff --check` y comparación de hashes del diff previo y
archivos de A1/A2. No se consultaron registros productivos ni su distribución.

## 9. Correcciones implementadas

**Ninguna corrección de producción.** No se identificó una reparación local que
certifique O-06/O-07 sin cambiar la definición de sus fuentes/población o la
instrumentación/historia. Tampoco se limitó artificialmente el porcentaje a 100 %
ni se convirtió la ausencia de intervalos en una medición nueva de atención.

Se corrigió solo la preparación del caso nuevo de autorización de pruebas descrito
en §8. Las correcciones temporales A2 y el contrato de promedios completos permanecen.

## 10. Archivos modificados y justificación

Solo se agregaron:

| Archivo | Finalidad |
| --- | --- |
| `tests/Unit/PuntoVenta/Reportes/OcupacionDisponibilidadReportePdvTest.php` | Evidencia matemática, controles A2, flujos de inicio/pausa/reactivación y diagnósticos pendientes |
| `CHECKPOINT_A3_OCUPACION_DISPONIBILIDAD_PDV.md` | Informe y alternativas para aprobación |

Los cambios existentes de A1/A2 ya estaban presentes al iniciar A3 y no se tocaron.
No hubo commits, push, cambios de esquemas, funcionalidades de resguardos ni UI.

## 11. Riesgos técnicos y datos faltantes

- Falta un productor operativo de `en_atencion` identificado; no se conoce cobertura
  de registros legados. No puede cuantificarse el impacto sin otra validación autorizada.
- Extremos de jornadas/intervalos pueden sobrescribirse al reactivar/cancelar cierre;
  no hay un registro de segmentos de jornada inmutable consumido por estos reportes.
- Unicidad de abiertos no garantiza cronología válida, pertenencia temporal a la
  jornada, compatibilidad usuario/sucursal ni ausencia de solapamientos cerrados.
- Faltan reglas para pausa/atención simultáneas, espera de asignación, enfriamiento,
  atención posterior a cierre y huecos sin evidencia. No se resuelven por resta automática.
- Motivos pueden faltar; el nombre del catálogo no es una instantánea histórica.
- Una marca automática de inicio representa actividad registrada, sin probar que el
  cliente ya esté siendo atendido físicamente. No se infiere presencia ni venta.
- Intervalos y jornadas no se redistribuyen entre franjas/días con una misma base.
- SQLite aislado acredita lógica de los casos, no rendimiento ni integridad de MySQL
  productivo. Las pruebas de servicios no constituyen una prueba integral de todas
  las pantallas, listeners, transferencia y reatención.

## 12. Alternativas para mejorar la medición — no implementadas

| Alternativa | Ventajas | Riesgos e impacto |
| --- | --- | --- |
| A. Tratamiento conservador de datos no certificables | Mostrar ausencia de proporción y razón de insuficiencia; mantener acumulados como tiempos registrados | Cambia contrato de respuesta/presentación; no recupera datos ni genera una proporción válida. Requiere aprobar cuándo invalidar |
| B. Derivar ocupación con marcas existentes | Usar atención efectiva y una base de jornada recortada compatible; evita inventar intervalos persistidos | Cambia fuente y población de O-06. Necesita reglas de unión por usuario, solapamientos, asociación temporal y exclusión explícita de historia insuficiente. O-07 requiere además decidir qué acredita disponibilidad |
| C. Instrumentar transiciones e historial para el futuro | Registrar segmentos y transiciones sin sobrescribir extremos; permitir disponibilidad verificable | Cambia flujos operativos y posiblemente persistencia; necesita diseño/pruebas y autorización separada. No corrige automáticamente el pasado |

Recomendación: acordar B como contrato de medición y usar el criterio conservador
de A cuando falte evidencia; evaluar C por separado para trazabilidad futura.

Propuesta concreta para discutir, **no fórmula aprobada**:

- Tiempo operativo: unión por persona de tramos de jornada acreditados dentro de la
  ventana/corte, incluyendo tramos abiertos hasta el corte solo cuando la historia sea suficiente.
- Atención: unión de tramos con inicio efectivo registrado y final/corte, limitada a
  la misma población y ventana. Conservar por separado episodios completos para promedios.
- Ocupación propuesta: atención acreditada dentro de esa base / tiempo operativo
  acreditado. Acordar previamente si la base es bruta o excluye pausas.
- Disponibilidad: no usar automáticamente `1 - ocupación`; definir qué períodos
  estaban libres de atención asignada/efectiva, pausa y enfriamiento. Si no se conocen,
  declarar información insuficiente y conservar solo el dato literal registrado.
- En una superposición entre fuentes, proponer detectar el conflicto y no certificar
  la proporción hasta decidir si se excluye, prioriza o corrige. No sumar tiempos
  duplicados ni redistribuir silenciosamente el conflicto.

La unión de tiempos sería por persona antes de agregar; las horas de dos personas
pueden sumarse como horas-persona, sin interpretarse como duración de reloj de la sucursal.

## 13. Decisiones que requieren autorización

La restricción del encargo A3, §5, es explícita: “No debes inventar intervalos,
completar información inexistente ni modificar fórmulas de negocio sin autorización”.
Por ello se detiene esa implementación y se solicita aprobación para:

1. Elegir fuente/base de O-06: intervalos literales o atención efectiva, jornada bruta
   o sin pausas, población compatible de abiertos/cerrados y tratamiento de cierres pendientes.
2. Definir O-07: disponibilidad registrada o disponibilidad efectiva; definir espera,
   enfriamiento y cuándo el resultado debe ser nulo por falta de evidencia.
3. Aprobar tratamiento de solapamientos: unión de duplicados de una fuente y manejo
   explícito de conflictos pausa/atención, sin asumir complementariedad.
4. Definir franjas/días y umbral de calidad para habilitar comparaciones por persona/sucursal.
5. Autorizar, en un trabajo separado, diseño de historial/transiciones que evite perder
   extremos al reactivar; no rellenar retrospectivamente datos no acreditados.
6. Antes de productividad, acordar qué cuenta como turno efectivamente atendido y qué
   hora operativa lo divide. La tabla de viabilidad no autoriza nuevas métricas.

## 14. Estado final

**Diagnóstico y evidencia A3 concluidos; validación operativa global parcialmente
pendiente. O-06/O-07 requieren decisiones de definición, fuentes y trazabilidad.**

Se conservan los resultados verificados de recorte temporal A2 bajo extremos
conservados. O-03 es utilizable como tiempo de pausa registrado con las condiciones
señaladas; O-04/O-05 no deben presentarse como disponibilidad/atención reales certificadas.

Se detiene aquí el trabajo, pendiente de aprobación de las decisiones de §13.
No se continúa con otros checkpoints, nuevas métricas, cambios operativos o dashboard.
