<?php

namespace App\Support\Manuales\Content;

/** Una sola fuente para la guía en pantalla y su versión impresa. */
class RegistrarPedidosVendedorManualContent
{
    public static function payload(): array
    {
        return [
            'version' => '1.0',
            'actualizado' => '12/10/2026',
            'indice' => [
                ['id' => 'overview', 'label' => 'Para empezar'],
                ['id' => 'preparacion', 'label' => 'Qué tener a la mano'],
                ['id' => 'pasos', 'label' => 'Paso a paso'],
                ['id' => 'casos', 'label' => 'Casos de uso'],
                ['id' => 'respuestas', 'label' => 'Respuestas de otras áreas'],
                ['id' => 'estatus', 'label' => 'Cómo va mi pedido'],
                ['id' => 'problemas', 'label' => 'Si algo se detiene'],
                ['id' => 'checklist', 'label' => 'Revisión y recomendaciones'],
                ['id' => 'glosario', 'label' => 'Palabras útiles'],
            ],
            'intro' => 'Esta guía te acompaña desde que recibes la solicitud del cliente hasta que la envías a revisión y das seguimiento. Entra a Logística → Gestión de pedidos → Registrar pedidos. Las opciones que aparecen dependen de tus permisos, del tipo de pedido y de los pasos que ya completaste.',
            'alcance' => 'Está dirigida a vendedores. Explica lo que debes registrar y qué respuesta esperar de las otras áreas. Sus procedimientos completos se documentarán en sus propios manuales.',
            'regla' => 'Guardar borrador conserva tu trabajo. Solicitar revisión pide a otra área que revise la mercancía. Enviar pedido lo entrega a auditoría. El pedido se considera enviado al cliente cuando se registra su salida física; enviar a auditoría todavía no significa que salió.',
            'visuales_nota' => 'Los ejemplos visuales son ilustraciones de lectura, sin botones activos. Los nombres, folios y montos son ficticios. En tu pantalla pueden aparecer menos campos o distintas opciones según el pedido.',
            'preparacion' => [
                'Nombre o número del cliente. Confirma que elegiste a la persona correcta.',
                'Tipo de pedido: Tienda o Envío, almacén de salida y sucursal destino cuando se solicite.',
                'Folio y PDF o foto legible de la cotización o del pedido, con las piezas y cantidades solicitadas.',
                'Paquetería, si es un envío; datos de quien recibe y dirección cuando se abra la cotización.',
                'Después de confirmar mercancía: total acordado, condiciones de envío y comprobantes de los pagos realizados.',
            ],
            'pasos' => self::pasos(),
            'casos' => self::casos(),
            'respuestas' => self::respuestas(),
            'estatus' => [
                ['nombre' => 'Borrador', 'significado' => 'Aún estás capturando. Guardar no lo manda a auditoría.', 'accion' => 'Abre Editar y continúa en el mismo registro.'],
                ['nombre' => 'Consulta o pesaje pendiente', 'significado' => 'La solicitud está esperando revisión de mercancía o pesaje.', 'accion' => 'Espera respuesta; revisa el detalle o usa Actualizar.'],
                ['nombre' => 'Consulta o pesaje respondido', 'significado' => 'Ya hay una respuesta para revisar con el cliente.', 'accion' => 'Revisa piezas, evidencia e incidencias; confirma y cierra la consulta cuando esté lista.'],
                ['nombre' => 'Pendiente de auditoría', 'significado' => 'El auxiliar revisa datos, pagos y documentos.', 'accion' => 'Da seguimiento. Puede haber pago en revisión, pago validado o remisión pendiente.'],
                ['nombre' => 'Rechazado o devuelto para corrección', 'significado' => 'Hay un motivo o datos que debes corregir.', 'accion' => 'Lee el motivo, corrige y reenvía el mismo pedido.'],
                ['nombre' => 'Pendiente de empaque', 'significado' => 'Auditoría aprobó; falta preparar la salida. Un resguardo debe liberarse primero.', 'accion' => 'Espera a CEDIS o al área de preparación correspondiente.'],
                ['nombre' => 'Pendiente de guía', 'significado' => 'Falta el documento o número de rastreo para continuar.', 'accion' => 'Espera al encargado de guías, o carga la guía del cliente si te corresponde.'],
                ['nombre' => 'Pendiente de recolección o envío', 'significado' => 'Se espera la salida o recolección de la mercancía.', 'accion' => 'Aún no confirmes al cliente que el paquete salió.'],
                ['nombre' => 'Enviado', 'significado' => 'El área responsable registró la salida o recolección.', 'accion' => 'Consulta la guía cuando aplique y comparte el seguimiento. No significa que ya fue entregado al cliente.'],
                ['nombre' => 'Sin existencias / con observaciones / con retraso', 'significado' => 'Hay una situación adicional que debes revisar; puede acompañar otro estado.', 'accion' => 'Abre el detalle y lee el motivo. Un retraso no garantiza una nueva fecha de entrega.'],
            ],
            'problemas' => [
                ['situacion' => 'No veo Nuevo pedido, Editar o una opción del manual.', 'hacer' => 'Puede faltar permiso o el pedido ya no permite esa acción. Comparte con soporte el folio, la acción y el mensaje de pantalla. Solo el vendedor responsable puede hacer las correcciones que le corresponden.'],
                ['situacion' => 'No aparecen Cotización o Pago.', 'hacer' => 'Revisa si falta solicitar la revisión, recibir la respuesta o cerrar la consulta con el cliente. Si hay cambios pendientes, espera la nueva respuesta. En Recoge en tienda estas secciones no se usan.'],
                ['situacion' => 'No puedo cerrar la consulta.', 'hacer' => 'Revisa incidencias y solicitudes de actualización. La respuesta debe estar vigente. Si las piezas vienen de Tienda a CEDIS, espera su recepción antes de continuar.'],
                ['situacion' => 'No puedo enviar el pedido.', 'hacer' => 'Lee la lista de datos faltantes. Revisa folio, archivo del pedido, cliente, almacén, consulta cerrada, dirección y costos cuando apliquen. El resumen de pagos debe cubrir lo requerido y no tener comprobantes rechazados sin sustituir.'],
                ['situacion' => 'El comprobante fue rechazado.', 'hacer' => 'Lee el motivo y usa Sustituir comprobante si tienes permiso. Adjunta el comprobante correcto y verifica la cobertura. Agregar otro pago sin sustituir el rechazado puede dejar el pedido detenido.'],
                ['situacion' => 'El domicilio cambió después de la revisión.', 'hacer' => 'Revisa si todavía aparece la opción de cambio. Después de remisión o guía requiere permisos especiales y puede obligar a revisar o emitir documentos de nuevo. Coordina con el área responsable antes de prometer la salida.'],
                ['situacion' => 'No sé si se guardó o envié dos veces.', 'hacer' => 'Espera la confirmación. Revisa el listado, busca el folio y abre el registro existente antes de crear otro. Si falló la conexión, conserva el formulario y vuelve a guardar cuando se restablezca.'],
                ['situacion' => 'El cliente ya no quiere el pedido.', 'hacer' => 'Usa Cancelar cuando esté disponible y registra el motivo solicitado. Si hay mercancía apartada o pagos, espera la gestión de liberación y revisión correspondiente. Eliminar un borrador y cancelar un pedido son acciones distintas.'],
            ],
            'checklist' => [
                'El cliente, tipo de pedido, almacén y sucursal destino son correctos.',
                'El folio coincide con el archivo vigente y las piezas confirmadas.',
                'La consulta está respondida y cerrada cuando aplica, sin cambios ni faltantes por atender.',
                'El destinatario, teléfono, código postal y dirección están completos cuando se requiere envío.',
                'Mercancía, envío, cargos adicionales y seguro coinciden con lo acordado y con el resumen.',
                'Cada pago tiene el monto, forma, banco y comprobante que le corresponden; el resumen indica que cubre lo requerido.',
                'No quedan comprobantes rechazados pendientes de sustitución.',
                'Guardé el pedido y, si corresponde, comprobé que Enviar pedido lo dejó pendiente de auditoría.',
            ],
            'recomendaciones' => [
                'Usa un solo registro por pedido. Si te lo devuelven, corrige ese registro y conserva su historial.',
                'Distingue el folio de pedido que capturas, el folio interno que genera GELIA y el número de remisión de auditoría. Comunica a las otras áreas los identificadores visibles del mismo registro.',
                'Mantén la misma versión de piezas, cantidades, destinatario y montos en archivo, cotización, pagos y comentarios. Cuando cambie algo, actualízalo y espera la revisión necesaria.',
                'Los comentarios ayudan a explicar un caso, pero los datos deben registrarse también en sus campos. Ejemplo: “El cliente acepta sustituir la pieza indicada; revisar cotización actualizada”.',
                'Revisa a diario rechazados, sin existencias y observaciones. Usa Actualizar si esperas una respuesta; los avisos de retraso no son fechas prometidas.',
                'Para imprimir, usa Descargar PDF en este manual y abre el archivo descargado. La fecha de revisión del contenido es distinta de la fecha de descarga; consulta la versión en GELIA si el proceso cambió.',
            ],
            'glosario' => [
                ['palabra' => 'CEDIS', 'significado' => 'Área que revisa, pesa, empaca o despacha mercancía según el pedido.'],
                ['palabra' => 'PDV', 'significado' => 'Punto de venta: la operación de tienda donde se separan las piezas para recogerlas.'],
                ['palabra' => 'Consulta', 'significado' => 'Solicitud para revisar las piezas y, cuando se necesita, su peso.'],
                ['palabra' => 'Cerrar consulta', 'significado' => 'Confirmar que revisaste la respuesta y acordaste la mercancía con el cliente para continuar.'],
                ['palabra' => 'Auditoría / auxiliar', 'significado' => 'Persona o área que revisa datos, documentos y pagos antes de aprobar.'],
                ['palabra' => 'Remisión', 'significado' => 'Documento que prepara o adjunta auditoría para respaldar el pedido aprobado.'],
                ['palabra' => 'Guía / rastreo', 'significado' => 'Documento y número que identifican el paquete ante la paquetería.'],
                ['palabra' => 'Reexpedición', 'significado' => 'Cargo adicional de envío a ciertas zonas; revisa el que corresponda al destino.'],
                ['palabra' => 'Exhibición de pago', 'significado' => 'Cada pago registrado. Un pedido puede tener uno o varios.'],
                ['palabra' => 'Cobertura', 'significado' => 'Comparación entre lo que debe cubrirse y los pagos o saldos registrados.'],
                ['palabra' => 'Resguardo', 'significado' => 'Mercancía conservada para enviarla después; requiere completar lo pendiente y liberar su salida.'],
                ['palabra' => 'Bitácora', 'significado' => 'Historial de acciones y cambios del pedido: qué ocurrió, cuándo y quién lo hizo.'],
            ],
        ];
    }

    private static function pasos(): array
    {
        return [
            [
                'titulo' => 'Abre Nuevo pedido y elige al cliente',
                'donde' => 'Registrar pedidos → Nuevo pedido → Datos del cliente y pedido',
                'acciones' => [
                    'Busca por nombre o número y selecciona al cliente. Revisa su información antes de continuar.',
                    'Selecciona Tipo de pedido: Tienda o Envío. Lee la explicación que aparece debajo; indica qué preparación necesita.',
                    'Si GELIA encuentra un borrador en curso, elige Continuar borrador para retomarlo o Iniciar limpio para otro pedido.',
                ],
                'resultado' => 'Tienes un registro en captura. Aún no se ha enviado a auditoría.',
                'visual' => ['titulo' => 'Datos del cliente y pedido', 'campos' => [['Cliente', 'Cliente de ejemplo · 1042'], ['Tipo de pedido', 'Envío']], 'accion' => 'Nuevo pedido', 'nota' => 'Selecciona al cliente antes de preparar la solicitud.'],
            ],
            [
                'titulo' => 'Registra de dónde salen las piezas y adjunta el pedido',
                'donde' => 'Datos del cliente y pedido',
                'acciones' => [
                    'Elige Almacén de salida. Si se pide Sucursal destino, selecciona dónde se custodiará o entregará la mercancía; es distinto del almacén de salida.',
                    'Captura el Folio del pedido y usa Adjuntar PDF o foto. El archivo debe mostrar claramente qué piezas y cantidades solicita el cliente.',
                    'Para envíos, selecciona la paquetería que corresponda. Si aparece modalidad de recolección o preparación en Tienda, selecciona la opción acordada.',
                    'Usa Guardar borrador y espera la confirmación. Si aparece un aviso de error de guardado, atiéndelo antes de cerrar.',
                ],
                'resultado' => 'La solicitud queda identificada y con un archivo para que el área responsable revise la mercancía.',
                'visual' => ['titulo' => 'Solicitud inicial', 'campos' => [['Almacén de salida', 'Almacén de ejemplo'], ['Folio', 'PED-EJEMPLO-1042'], ['Archivo del pedido', 'pedido-ejemplo.pdf · adjunto'], ['Paquetería', 'Paquetería acordada']], 'accion' => 'Guardar borrador', 'nota' => 'Aquí adjuntas el pedido o cotización. El comprobante va en Pago.'],
            ],
            [
                'titulo' => 'Solicita la revisión o preparación de mercancía',
                'donde' => 'Datos del cliente y pedido → opción de consulta o preparación',
                'acciones' => [
                    'Usa la acción que muestra tu pedido: solicitar pesaje o consulta de mercancía a CEDIS, o Solicitar preparación en Tienda.',
                    'En envíos con pesaje se revisan mercancía, cajas y pesos. En revisiones de mercancía el peso puede ser opcional según la paquetería.',
                    'Si seleccionaste Recoge en tienda, usa Solicitar apartado en PDV y sigue el caso específico de esta guía.',
                    'Espera la respuesta. Puedes consultar el mismo registro desde el listado; no crees un segundo pedido para pedir avance.',
                ],
                'resultado' => 'La solicitud llega al área responsable y queda pendiente de respuesta.',
                'visual' => ['titulo' => 'Consulta enviada', 'campos' => [['Responsable', 'CEDIS o Tienda, según tu pedido'], ['Situación', 'Pendiente de respuesta']], 'accion' => 'Solicitar pesaje / consulta / preparación', 'nota' => 'Se muestra la acción que corresponde a tu ruta.'],
            ],
            [
                'titulo' => 'Revisa la respuesta y confirma con el cliente',
                'donde' => 'Respuesta CEDIS / respuesta de preparación',
                'acciones' => [
                    'Revisa disponibilidad, cantidades, observaciones y fotos. Si hay pesaje, revisa también las cajas y pesos informados.',
                    'Si faltan piezas o hay diferencias, acuerda con el cliente cómo resolverlas. Atiende cada incidencia antes de avanzar.',
                    'Cuando la respuesta esté vigente y el cliente confirme las piezas, usa Cerrar consulta / Confirmar mercancía con cliente.',
                    'Si cambió lo solicitado, usa la opción disponible para actualizar o volver a pedir revisión. Espera la nueva respuesta y confirma otra vez. Un traslado de Tienda a CEDIS debe recibirse antes de cerrar.',
                ],
                'resultado' => 'Se habilitan cotización y pago cuando corresponden. Recoge en tienda termina con el apartado confirmado y sigue su propia ruta.',
                'visual' => ['titulo' => 'Respuesta CEDIS', 'campos' => [['Mercancía', 'Piezas revisadas · sin incidencias'], ['Envío 1 · peso real', '4 kg · ejemplo'], ['Evidencia', 'Fotos de las piezas revisadas']], 'accion' => 'Cerrar consulta / Confirmar mercancía con cliente', 'nota' => 'Confirma después de revisar la respuesta con el cliente.'],
            ],
            [
                'titulo' => 'Completa la cotización y los datos de entrega',
                'donde' => 'Cotización',
                'acciones' => [
                    'Captura el Total de mercancía de las piezas confirmadas. Para envíos revisa costo, tipo de guía, reexpedición y seguro cuando apliquen.',
                    'Si hay varias cajas o envíos, registra los costos de cada uno cuando se solicite. Revisa el total del resumen.',
                    'Selecciona una dirección verificada del cliente. Confirma destinatario, teléfono, código postal y domicilio. Si otra persona recibirá, registra sus datos.',
                    'Si no existe dirección, solicita su registro o revisión. Link de dirección permite pedir los datos al cliente cuando tengas esa opción; recibirlos todavía no significa que estén verificados. La dirección manual es una excepción con permiso.',
                    'Si aplicas saldo a favor, revisa el monto disponible y el total que queda por cubrir. GELIA aplica primero los saldos próximos a vencer. Si usas crédito, debe estar autorizado.',
                ],
                'resultado' => 'El resumen refleja el importe y los datos de entrega que deben coincidir con lo acordado.',
                'visual' => ['titulo' => 'Cotización · ejemplo sin cargos adicionales', 'campos' => [['Mercancía', '$1,000.00'], ['Envío', '$150.00'], ['Dirección', 'Dirección verificada del cliente'], ['Total a cubrir', '$1,150.00']], 'accion' => 'Revisar resumen', 'nota' => 'Si hay seguro, reexpedición o saldo a favor, el total cambia.'],
            ],
            [
                'titulo' => 'Registra el pago y adjunta su comprobante',
                'donde' => 'Pago y remisión → Pago',
                'acciones' => [
                    'Registra monto, forma de pago y fecha. Elige el banco receptor y la referencia cuando el método los solicite.',
                    'Adjunta un comprobante legible a cada pago que lo requiera. Si se pagó en varias partes, registra cada una con sus propios datos.',
                    'Revisa que el resumen indique que los pagos y saldos cubren lo requerido. Un archivo adjunto por sí solo no completa el registro del pago.',
                    'Crédito sigue las condiciones autorizadas y no pide banco ni comprobante. Si el cliente todavía no paga, conserva el borrador o usa Espera de pago si esa opción está habilitada.',
                ],
                'resultado' => 'Los pagos quedan registrados para revisión del auxiliar. Capturarlos todavía no significa que fueron validados.',
                'visual' => ['titulo' => 'Pago y remisión', 'campos' => [['Forma de pago', 'Transferencia'], ['Monto', '$1,150.00'], ['Banco receptor', 'Banco de ejemplo'], ['Comprobante', 'comprobante-ejemplo.jpg'], ['Pendiente por cubrir', '$0.00']], 'accion' => 'Registrar pago', 'nota' => 'El auxiliar revisará el comprobante y su correspondencia con el pedido.'],
            ],
            [
                'titulo' => 'Envía a revisión y sigue el mismo pedido',
                'donde' => 'Enviar pedido → listado y detalle',
                'acciones' => [
                    'Revisa la lista de comprobación de este manual y usa Enviar pedido.',
                    'Si GELIA indica campos faltantes o un bloqueo, corrige lo señalado y vuelve a intentar. Comprueba que el registro quedó pendiente de auditoría.',
                    'El auxiliar revisará pagos y documentos. Si devuelve el pedido, abre el motivo, corrige en ese mismo registro y reenvía. El reenvío inicia una nueva revisión de pago y remisión.',
                    'Desde el listado busca por folio, cliente o número. Usa Ver para consultar el detalle y Bitácora para entender los cambios. En móvil encontrarás bandejas en Filtros y acciones adicionales en Más.',
                ],
                'resultado' => 'El pedido está en revisión. Después de aprobarse continuará su preparación, guía y salida según la ruta.',
                'visual' => ['titulo' => 'Seguimiento del pedido', 'campos' => [['Folio', 'PED-EJEMPLO-1042'], ['Estado', 'Pendiente de auditoría'], ['Siguiente responsable', 'Auxiliar']], 'accion' => 'Enviar pedido', 'nota' => 'Busca este mismo registro para consultar la respuesta o corregirlo.'],
            ],
        ];
    }

    private static function casos(): array
    {
        return [
            ['titulo' => 'Envío habitual por paquetería', 'ejemplo' => 'El cliente solicita piezas para recibirlas en su domicilio.', 'hacer' => 'Registra el pedido y la paquetería, solicita revisión o pesaje, confirma mercancía y cierra consulta. Completa dirección, costos y pago; envía a auditoría.', 'esperar' => 'CEDIS informa piezas y pesos; auditoría revisa pago y remisión; guías registra rastreo cuando aplica; el área de salida confirma la recolección.'],
            ['titulo' => 'Tienda o mostrador con preparación habilitada', 'ejemplo' => 'Las piezas están en una sucursal y deben prepararse o trasladarse.', 'hacer' => 'Elige la modalidad y el almacén disponibles. Registra la sucursal destino si se pide y solicita preparación en Tienda. Si hay traslado a CEDIS, espera su recepción. Revisa la respuesta, cierra consulta y completa los pasos de monto y pago que muestre el formulario.', 'esperar' => 'Tienda informa preparación, evidencia o incidencias. Si no tienes la ruta de Tienda habilitada, el formulario puede pedir separación de mercancía a CEDIS.'],
            ['titulo' => 'Recoge en tienda: apartado en PDV', 'ejemplo' => 'El cliente recogerá el mismo día y necesitas que separen sus piezas.', 'hacer' => 'Selecciona Recoge en tienda, adjunta el pedido y usa Solicitar apartado en PDV. Revisa la respuesta del personal de preparación.', 'esperar' => 'Tienda confirma el apartado y genera su carátula. Este registro termina con el apartado confirmado: no captura pago ni se envía a auditoría desde este formulario. Apartado confirmado no significa entrega al cliente; la entrega física sigue el procedimiento de tienda.'],
            ['titulo' => 'Falta una pieza o llega con observaciones', 'ejemplo' => 'CEDIS informa que una pieza no está disponible o necesita revisión.', 'hacer' => 'Abre la incidencia y consulta al cliente. Usa la acción disponible para contactar, esperar, retirar, sustituir o cancelar. Retirar o sustituir cambia los importes; revisa archivo, cotización y pagos. Pedir contacto o esperar no resuelve por sí solo un faltante.', 'esperar' => 'El pedido permanece detenido mientras exista un faltante sin resolver. CEDIS puede confirmar que ya hay existencias. Si cambia mercancía de un pedido pagado, puede necesitar una nueva revisión de pago y remisión.'],
            ['titulo' => 'Resguardo abierto o complemento', 'ejemplo' => 'El cliente deja mercancía para enviar después o agrega piezas a un pedido principal.', 'hacer' => 'Selecciona el tipo correcto. Para complemento, vincula el principal del mismo cliente que GELIA permita; confirma que es el registro correcto. El complemento usa la logística del principal. En resguardo abierto, dirección y costo de envío se completan después con Completar envío.', 'esperar' => 'Cada registro conserva su revisión de pago y documentos según corresponda. El complemento no pide una consulta de pesaje independiente. El resguardo no sale hasta que se complete y libere. Los cambios del principal pueden afectar el envío del conjunto.'],
            ['titulo' => 'El cliente proporciona su guía', 'ejemplo' => 'El cliente compra la guía por su cuenta.', 'hacer' => 'Marca la opción correspondiente. Cuando esté disponible Cargar guía del cliente, adjunta el PDF y captura el número de rastreo. Confirma que destinatario y paquete coincidan.', 'esperar' => 'La mercancía sigue requiriendo revisión y aprobación. Tener la guía no confirma que el paquete salió; el área responsable registra la recolección.'],
            ['titulo' => 'Envío municipal con costo pendiente o por cobrar', 'ejemplo' => 'La ruta permite completar el costo después o cobrar el envío al recibir.', 'hacer' => 'Usa estas opciones solo cuando aparezcan y correspondan al acuerdo. Registra destinatario y condiciones. Cuando el costo quede pendiente, usa Anexar pago de envío al contar con importe y comprobante. Por cobrar deja el cobro de transporte al destinatario; revisa el total del resumen.', 'esperar' => 'El auxiliar revisa el anexo cuando corresponda. En la ruta municipal de Tienda, el personal responsable registra empaque y despacho. La modalidad de cobro no sustituye la revisión del pago de mercancía.'],
            ['titulo' => 'Auditoría devuelve el pedido o rechaza un comprobante', 'ejemplo' => 'El monto no coincide, el archivo no es legible o hay datos incorrectos.', 'hacer' => 'Consulta el motivo en el detalle. Corrige los datos indicados y sustituye el comprobante rechazado si corresponde. Revisa nuevamente el total y reenvía el registro existente.', 'esperar' => 'Auditoría debe revisar la nueva información y aprobarla antes de continuar. Los documentos y validaciones anteriores pueden necesitar renovarse.'],
        ];
    }

    private static function respuestas(): array
    {
        return [
            ['area' => 'CEDIS: revisión y pesaje', 'envias' => 'Pedido o cotización vigente, piezas solicitadas, almacén y paquetería cuando aplica.', 'recibes' => 'Disponibilidad, revisión de piezas, observaciones y evidencias; cajas y pesos cuando aplica.', 'sigues' => 'Compara con lo solicitado, resuelve diferencias con el cliente y cierra consulta cuando esté lista.'],
            ['area' => 'Preparación en Tienda', 'envias' => 'Solicitud, modalidad, almacén, sucursal destino y destinatario municipal si se solicitan.', 'recibes' => 'Preparación confirmada, evidencia, incidencia o avance de traslado. Para Recoge en tienda: apartado confirmado y carátula.', 'sigues' => 'Atiende incidencias. Si hay traslado a CEDIS espera su recepción. En apartado revisa la confirmación y sigue el procedimiento de entrega de tienda.'],
            ['area' => 'Direcciones', 'envias' => 'Datos completos del domicilio o solicitud de captura mediante Link de dirección cuando tengas permiso.', 'recibes' => 'Dirección registrada y verificada, o petición de corregir datos.', 'sigues' => 'Selecciona la dirección correcta; recibir datos del cliente no sustituye su verificación. Revisa a quién se entrega.'],
            ['area' => 'Auxiliar / auditoría', 'envias' => 'Pedido confirmado, datos de entrega, montos, pagos y comprobantes completos.', 'recibes' => 'Avance de revisión, pago validado y remisión; aprobación o devolución con motivo. Los anexos de envío también pueden aprobarse o rechazarse.', 'sigues' => 'Si se aprueba, sigue el avance. Si se devuelve, corrige y reenvía. No confundas pago registrado con pago validado.'],
            ['area' => 'Encargado de guías', 'envias' => 'Datos de entrega y paquetería que ya figuran en el pedido aprobado.', 'recibes' => 'Número de rastreo, PDF de guía o aviso de datos que deben corregirse, cuando la ruta necesita guía.', 'sigues' => 'Verifica el destinatario. Si el cliente proporciona guía, cárgala cuando se habilite. Tener rastreo no confirma salida.'],
            ['area' => 'CEDIS o Tienda: salida física', 'envias' => 'El mismo pedido aprobado y sus condiciones de entrega; resguardo liberado cuando aplica.', 'recibes' => 'Empaque y confirmación de recolección o despacho, según la ruta.', 'sigues' => 'Informa al cliente con el estado registrado y el rastreo disponible. Empacado o apartado todavía no equivale a entregado.'],
            ['area' => 'Saldos a favor / administración', 'envias' => 'Monto de saldo que deseas aplicar al pedido, si tienes permiso y existe saldo disponible.', 'recibes' => 'Saldo aplicado o reservado, total recalculado y avisos si algo requiere revisión.', 'sigues' => 'Confirma el total que falta cubrir. Si cambia mercancía o envío, revisa de nuevo el saldo aplicado y los pagos.'],
        ];
    }
}
