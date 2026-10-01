@php
    /*
     * Datos editables de la política.
     * Cuando alguno cambie, sólo es necesario modificarlo en este bloque.
     */
    $responsibleName = 'Jesús Gabriel De la cruz Zárate';
    $brandName = 'NeoBash';
    $privacyEmail = ''; // Ejemplo: privacidad@tudominio.com
    $lastUpdated = '1 de octubre de 2026';
@endphp
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Política de privacidad de GELIA-NV y de su aplicación móvil para Android.">
    <title>Política de privacidad — GELIA-NV</title>
    <style>
        :root {
            color-scheme: light;
            --background: #f5f7fb;
            --surface: #ffffff;
            --text: #172033;
            --muted: #526079;
            --border: #dbe2ee;
            --accent: #3157d5;
            --accent-soft: #eef2ff;
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            background: var(--background);
            color: var(--text);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 16px;
            line-height: 1.65;
        }
        main {
            width: min(100% - 2rem, 56rem);
            margin: 2rem auto;
            padding: clamp(1.25rem, 3vw, 3rem);
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 1rem;
            box-shadow: 0 1rem 3rem rgba(30, 45, 80, .08);
        }
        h1, h2, h3 { line-height: 1.25; }
        h1 { margin: 0; font-size: clamp(1.8rem, 5vw, 2.65rem); }
        h2 { margin: 2.4rem 0 .7rem; padding-top: .35rem; font-size: 1.35rem; }
        h3 { margin: 1.35rem 0 .35rem; font-size: 1.05rem; }
        p, ul { margin: .65rem 0; }
        li + li { margin-top: .45rem; }
        a { color: var(--accent); overflow-wrap: anywhere; }
        strong { color: #11192a; }
        .eyebrow {
            display: inline-block;
            margin-bottom: .65rem;
            color: var(--accent);
            font-size: .78rem;
            font-weight: 750;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .updated { margin: .45rem 0 0; color: var(--muted); }
        .summary {
            margin: 1.5rem 0;
            padding: 1rem 1.15rem;
            background: var(--accent-soft);
            border-left: .3rem solid var(--accent);
            border-radius: .45rem;
        }
        .data-table { width: 100%; margin: 1rem 0; border-collapse: collapse; font-size: .95rem; }
        .data-table th, .data-table td {
            padding: .75rem;
            border: 1px solid var(--border);
            text-align: left;
            vertical-align: top;
        }
        .data-table th { background: #f7f9fc; }
        .note { color: var(--muted); font-size: .94rem; }
        .contact { margin-top: 1rem; padding: 1rem 1.15rem; border: 1px solid var(--border); border-radius: .6rem; }
        footer {
            margin-top: 2.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
            color: var(--muted);
            font-size: .9rem;
        }
        @media (max-width: 40rem) {
            main { width: 100%; margin: 0; border: 0; border-radius: 0; box-shadow: none; }
            .data-table, .data-table tbody, .data-table tr, .data-table th, .data-table td { display: block; }
            .data-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
            .data-table tr { margin-bottom: .8rem; border: 1px solid var(--border); border-radius: .5rem; overflow: hidden; }
            .data-table th, .data-table td { border: 0; }
            .data-table td + td { border-top: 1px solid var(--border); }
        }
        @media print {
            body { background: #fff; }
            main { width: 100%; margin: 0; padding: 0; border: 0; box-shadow: none; }
            a { color: inherit; }
        }
    </style>
</head>
<body>
<main>
    <header>
        <span class="eyebrow">GELIA-NV · Aplicación móvil y plataforma web</span>
        <h1>Política de privacidad</h1>
        <p class="updated"><strong>Última actualización:</strong> {{ $lastUpdated }}</p>
    </header>

    <div class="summary">
        GELIA-NV es una herramienta de gestión interna para personal autorizado. Aunque la aplicación se distribuye mediante Google Play, el servicio no está abierto al público: sólo funciona con cuentas internas preexistentes del sistema GELIA-NV. La app no registra usuarios ni permite crear cuentas. Trata información de cuentas, clientes y operaciones únicamente para autenticar el acceso, habilitar funciones autorizadas, mantener la seguridad y permitir la continuidad operativa. No vende datos personales, no muestra publicidad y no utiliza la información para publicidad dirigida.
    </div>

    <section aria-labelledby="responsable">
        <h2 id="responsable">1. Responsable y alcance</h2>
        <p><strong>{{ $responsibleName }}</strong>, bajo la marca personal <strong>{{ $brandName }}</strong>, es el desarrollador y titular de la publicación de la aplicación móvil GELIA-NV para Android (identificador <code>mx.neobash.gelianv</code>). GELIA-NV es el nombre del sistema y no constituye por sí mismo una persona física o moral independiente.</p>
        <p>Esta política se aplica a la aplicación móvil GELIA-NV, a su versión web y a los servicios de servidor necesarios para su funcionamiento. La organización que proporciona al usuario una cuenta de GELIA-NV administra sus accesos y determina qué información empresarial puede consultar o registrar. Respecto de los datos de sus empleados, clientes, pedidos y operaciones, dicha organización es la responsable de definir las finalidades y condiciones del tratamiento; el sistema GELIA-NV y su infraestructura técnica procesan la información necesaria para prestar el servicio conforme a esas instrucciones.</p>
        <p>La publicación de la app en Google Play tiene como finalidad facilitar su instalación y actualización para la operación interna. No convierte a GELIA-NV en un servicio disponible para el público general.</p>
        <p>GELIA-NV no ofrece registro público ni registro dentro de la aplicación. La app tampoco solicita datos para crear usuarios nuevos. Las cuentas, perfiles y permisos ya existen en el sistema interno y son creados, administrados o autorizados previamente por la organización correspondiente. La app únicamente permite que el personal autorizado acceda con esas credenciales.</p>
    </section>

    <section aria-labelledby="datos">
        <h2 id="datos">2. Datos que tratamos</h2>
        <p>Según las funciones habilitadas y los permisos del usuario, GELIA-NV puede acceder, recopilar, transmitir, almacenar o mostrar las siguientes categorías:</p>
        <table class="data-table">
            <thead><tr><th>Categoría</th><th>Ejemplos y forma de uso</th></tr></thead>
            <tbody>
                <tr><td><strong>Identificación y contacto del usuario interno</strong></td><td>Nombre, identificador interno, nombre de usuario, correo electrónico y fotografía de perfil previamente asociados con la cuenta interna. La app consulta y muestra estos datos, pero no los solicita para registrar una cuenta nueva.</td></tr>
                <tr><td><strong>Cuenta, autenticación y seguridad</strong></td><td>Identificador de inicio de sesión, contraseña durante su validación, token de sesión, permisos, versión del alcance de acceso y, si el usuario configura una llave de acceso, identificador y clave pública de la credencial, plataforma, nombre, transportes y fechas de creación o uso.</td></tr>
                <tr><td><strong>Dispositivo e información técnica</strong></td><td>Identificador aleatorio generado por GELIA-NV para la instalación, nombre o tipo de dispositivo, plataforma, versión de la app, fecha de última actividad, dirección IP y registros técnicos o de seguridad generados por el servidor.</td></tr>
                <tr><td><strong>Clientes y datos comerciales</strong></td><td>Número de cliente, nombre o razón social, RFC, lista de descuento, vendedor, tipo y estado del cliente, sucursal, pedidos, folios, productos, SKU, códigos de barras y demás información empresarial autorizada para el usuario.</td></tr>
                <tr><td><strong>Operaciones y contenido aportado</strong></td><td>Registros de resguardo, recepción, custodia, entrega, devolución e incidencias; nombres de quien entrega o retira; relación con el titular; observaciones; cantidades; almacén; fechas; historial de acciones y evidencias relacionadas.</td></tr>
                <tr><td><strong>Fotos, archivos y firma</strong></td><td>Fotografía de perfil; fotos de paquetes, tickets, daños u otras evidencias; documentos seleccionados por el usuario; y firma manuscrita capturada para acreditar una entrega.</td></tr>
                <tr><td><strong>Preferencias</strong></td><td>Tema visual, tamaño de fuente, densidad de contenido y otras opciones de presentación vinculadas con la cuenta.</td></tr>
            </tbody>
        </table>

        <h3>Cámara, fotografías y archivos</h3>
        <p>La aplicación solicita el permiso de cámara únicamente cuando el usuario decide tomar una fotografía como evidencia operativa. Las fotos tomadas desde GELIA-NV se procesan para reducir su tamaño, se transmiten al servidor de GELIA correspondiente y no se guardan deliberadamente en la galería. El usuario también puede elegir una imagen mediante el selector de archivos o fotografías del sistema; la aplicación no solicita acceso general a toda la galería.</p>

        <h3>Contraseña, huella y llaves de acceso</h3>
        <p>La contraseña se transmite de forma cifrada al servidor para validar el acceso y no se almacena en el dispositivo. Cuando el usuario utiliza huella, rostro, PIN o bloqueo del dispositivo para una llave de acceso, la verificación la realiza Android o el proveedor de credenciales elegido por el usuario. GELIA-NV no recibe ni almacena la huella, el rostro, el PIN ni la clave privada; sólo recibe la respuesta criptográfica necesaria para autenticar la cuenta.</p>

        <p class="note"><strong>La versión revisada de la app no solicita</strong> ubicación, contactos, micrófono, SMS, registro de llamadas, calendario ni identificador de publicidad. Si en el futuro una función requiere otra categoría de datos o un permiso sensible, esta política y las divulgaciones dentro de la app se actualizarán antes de utilizarlo.</p>
    </section>

    <section aria-labelledby="origen">
        <h2 id="origen">3. Cómo obtenemos los datos</h2>
        <ul>
            <li>De la organización que administra la cuenta y de sus sistemas GELIA, donde ya se encuentran la identidad interna, permisos, sucursales, catálogos, clientes y operaciones autorizadas.</li>
            <li>Directamente del usuario durante el uso de la app, cuando inicia sesión, ajusta su perfil, toma o selecciona evidencia, firma o registra una operación. Esta captura corresponde a funciones internas y no al registro o creación de usuarios.</li>
            <li>Automáticamente del dispositivo y del servidor, para crear una sesión, sincronizar información, mantener registros de seguridad, prevenir abuso y diagnosticar fallas.</li>
        </ul>
    </section>

    <section aria-labelledby="finalidades">
        <h2 id="finalidades">4. Para qué usamos los datos</h2>
        <ul>
            <li>Autenticar al usuario mediante contraseña o llave de acceso y mantener una sesión segura.</li>
            <li>Verificar permisos y mostrar únicamente la información autorizada para su cuenta, sucursal y funciones.</li>
            <li>Sincronizar un catálogo de clientes para búsquedas autorizadas y continuidad operativa, incluso cuando la conexión sea intermitente.</li>
            <li>Gestionar clientes, perfiles, preferencias, turnos, pedidos, puntos de venta, resguardos, recepciones, custodias, entregas, devoluciones e incidencias.</li>
            <li>Conservar evidencias, firmas y trazabilidad de las operaciones realizadas en la plataforma.</li>
            <li>Proteger cuentas y sistemas, revocar accesos, prevenir fraude o uso indebido, aplicar límites de solicitudes e investigar incidentes.</li>
            <li>Proporcionar soporte, corregir errores, mejorar estabilidad y cumplir obligaciones contractuales, administrativas o legales.</li>
        </ul>
        <p>No usamos los datos para publicidad, elaboración de perfiles publicitarios ni comercialización de bases de datos.</p>
    </section>

    <section aria-labelledby="almacenamiento">
        <h2 id="almacenamiento">5. Almacenamiento local y funcionamiento sin conexión</h2>
        <p>Para mantener la sesión y permitir las funciones autorizadas, la app almacena localmente:</p>
        <ul>
            <li>El token de acceso dentro del almacenamiento cifrado de Android.</li>
            <li>Datos básicos del perfil, permisos, dispositivo y preferencias.</li>
            <li>Una copia delimitada por usuario y alcance del catálogo de clientes autorizado, junto con metadatos de sincronización.</li>
        </ul>
        <p>La app elimina la sesión y el catálogo local al cerrar sesión, cuando cambia el usuario o cuando el servidor revoca el acceso correspondiente. Los datos de sesión, el catálogo local y el contenido del WebView están excluidos de las copias de seguridad y de la transferencia de dispositivo configuradas por la aplicación.</p>
    </section>

    <section aria-labelledby="compartir">
        <h2 id="compartir">6. Con quién compartimos los datos</h2>
        <p>Los datos se comparten o ponen a disposición sólo en la medida necesaria con:</p>
        <ul>
            <li><strong>La organización titular de la cuenta</strong>, sus administradores y usuarios autorizados, de acuerdo con funciones y permisos.</li>
            <li><strong>Proveedores de infraestructura y soporte técnico</strong> que alojan, respaldan, almacenan, protegen o mantienen GELIA-NV, actuando por cuenta del responsable y sujetos a obligaciones de confidencialidad y seguridad.</li>
            <li><strong>Android y el proveedor de credenciales elegido por el usuario</strong>, cuando se utiliza el selector del sistema, la cámara o una llave de acceso. Estos servicios procesan la interacción conforme a sus propias políticas; GELIA-NV no recibe los datos biométricos ni la clave privada.</li>
            <li><strong>Autoridades competentes</strong>, cuando exista una obligación legal, orden válida o necesidad de proteger derechos, seguridad e integridad de usuarios, organizaciones o sistemas.</li>
        </ul>
        <p><strong>No vendemos ni alquilamos datos personales.</strong> Tampoco los compartimos con redes publicitarias, corredores de datos ni terceros para publicidad dirigida.</p>
    </section>

    <section aria-labelledby="seguridad">
        <h2 id="seguridad">7. Seguridad</h2>
        <p>Aplicamos medidas técnicas y organizativas razonables según la naturaleza de la información, entre ellas comunicaciones mediante HTTPS, autenticación y autorización por permisos, sesiones revocables, almacenamiento cifrado del token en Android, separación del catálogo local por usuario y alcance, eliminación de datos locales al cerrar sesión o revocarse el acceso, exclusión de información sensible de las copias de seguridad y registros de auditoría en operaciones.</p>
        <p>Ningún método de transmisión o almacenamiento es completamente infalible. Los usuarios deben proteger el bloqueo de su dispositivo, no compartir credenciales y comunicar de inmediato cualquier acceso no reconocido al administrador de su organización.</p>
    </section>

    <section aria-labelledby="conservacion">
        <h2 id="conservacion">8. Conservación y eliminación</h2>
        <p>Conservamos los datos mientras la cuenta o la relación de servicio permanezca activa y durante el tiempo necesario para cumplir las finalidades descritas, mantener la trazabilidad de operaciones, atender disputas, proteger la seguridad y cumplir obligaciones legales o contractuales. La duración concreta puede depender de las políticas de la organización responsable y del tipo de operación.</p>
        <p>Las cuentas son internas y son administradas directamente por la organización que las crea. Cuando una persona deja de requerir acceso, un administrador autorizado puede desactivar o eliminar la cuenta conforme a los procedimientos internos y a las necesidades de conservación de la organización. Los registros operativos pueden mantenerse cuando sean necesarios para auditoría, seguridad, prevención de fraude, cumplimiento de obligaciones, integridad de los registros empresariales o ejercicio y defensa de derechos.</p>
        <p>La app elimina del dispositivo la sesión y el catálogo local al cerrar sesión, cambiar de usuario o revocarse el acceso. Desinstalar la app también elimina sus datos locales, pero no elimina automáticamente la cuenta interna ni los registros almacenados en el servidor. GELIA-NV no promete la eliminación automática de datos en un plazo de 90 días.</p>
    </section>

    <section aria-labelledby="derechos">
        <h2 id="derechos">9. Administración interna y derechos de privacidad</h2>
        <p>GELIA-NV no permite crear cuentas desde la app y tampoco ofrece dentro de ella un formulario, enlace público o función de autoservicio para solicitar la eliminación de cuentas o datos. La aplicación se limita a autenticar cuentas de trabajo creadas previamente y administradas por la organización correspondiente.</p>
        <p>Las altas, bajas, correcciones, desactivaciones y reglas de conservación de las cuentas internas se gestionan mediante los procedimientos administrativos de la organización que proporcionó el acceso. Las consultas sobre información laboral, clientes u operaciones deben dirigirse al administrador interno o al responsable de privacidad de dicha organización.</p>
        <p>Lo anterior no limita los derechos que la legislación aplicable reconozca a las personas titulares. La organización responsable deberá atender las solicitudes que reciba por sus canales internos y podrá verificar la identidad y la relación con la cuenta antes de actuar.</p>
    </section>

    <section aria-labelledby="menores">
        <h2 id="menores">10. Personas menores de edad</h2>
        <p>GELIA-NV es una herramienta de gestión interna para personal autorizado y no está dirigida a niñas, niños ni adolescentes. No existe registro público de usuarios. Si se detecta que se incorporaron datos de un menor sin una base válida, el responsable deberá solicitar su revisión o eliminación.</p>
    </section>

    <section aria-labelledby="transferencias">
        <h2 id="transferencias">11. Procesamiento en otras ubicaciones</h2>
        <p>Los proveedores de infraestructura o soporte pueden procesar información desde ubicaciones distintas a la del usuario. Cuando esto ocurra, se aplicarán medidas contractuales, técnicas y organizativas apropiadas y los requisitos de protección de datos que resulten aplicables.</p>
    </section>

    <section aria-labelledby="cambios">
        <h2 id="cambios">12. Cambios a esta política</h2>
        <p>Podemos actualizar esta política cuando cambien las funciones de GELIA-NV, sus prácticas de datos o los requisitos legales y de Google Play. La versión vigente se publicará permanentemente en esta misma dirección e indicará su fecha de actualización. Cuando un cambio sea relevante, se comunicará dentro de la app o por los medios disponibles para la organización.</p>
    </section>

    <section aria-labelledby="contacto">
        <h2 id="contacto">13. Contacto</h2>
        <p><strong>Aplicación y servicio:</strong> GELIA-NV<br>
            <strong>Desarrollador y titular de la publicación:</strong> {{ $responsibleName }}<br>
            <strong>Marca personal:</strong> {{ $brandName }}<br>
            @if ($privacyEmail)
                <strong>Correo de privacidad:</strong> <a href="mailto:{{ $privacyEmail }}">{{ $privacyEmail }}</a><br>
            @endif
            <strong>Consultas sobre cuentas y datos internos:</strong> administrador de GELIA-NV de la organización que proporcionó la cuenta. Este canal pertenece a la gestión interna de la organización y no constituye una función de eliminación ofrecida por la aplicación.
        </p>
    </section>

    <footer>
        Esta política es pública y no requiere iniciar sesión, aunque GELIA-NV y su aplicación móvil para Android sean herramientas de acceso interno restringido.
    </footer>
</main>
</body>
</html>
