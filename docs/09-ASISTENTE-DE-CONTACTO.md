# Asistente de Contacto

Actualizado: **14 de octubre de 2026**. El asistente incluye preguntas configurables, instantáneas de respuestas, foto, simulación administrativa y selector de país/teléfono. La guía y las entregas fechadas de abajo se complementan con el estado actual; los conteos históricos no son la suite vigente. Ver [05 · Auditoría](05-AUDITORIA-FINAL.md).

## Estado vigente y esquema

Las cuatro migraciones del módulo están registradas en la base local según consulta de solo lectura del 14 de octubre: configuración inicial, campos del mensaje, configuración de conversación y respuestas. No se ejecutó ninguna migración durante esta actualización documental. Cada instalación debe revisar su propio esquema mediante el procedimiento de [04 · Despliegue](04-DESPLIEGUE.md).

Las fases del asistente descritas en este documento están implementadas. Quedan pendientes dispositivos reales, VoiceOver/TalkBack, envío efectivo dentro de WhatsApp y aceptación por hosting. El usuario descartó las comprobaciones externas como tarea de esta sesión; siguen siendo requisitos del operador antes de publicar.

La revisión anterior que registró una prueba fallida de navegación es histórica: la última suite completa posterior aprobó **788** y omitió **2**. No se atribuye esa cifra solo al asistente. Los cambios del blog no añaden funciones al asistente ni alteran su catálogo.

El módulo recopila consultas mediante un chat guiado y prepara un enlace prellenado de WhatsApp. El botón flotante circular abre una ventana compacta con apariencia de WhatsApp, burbujas de conversación y un área de respuesta inferior. Se identifica como asistente virtual y no afirma que una persona esté conectada. No utiliza IA, servicios de chatbot, APIs de mensajería ni suscripciones. Cada frontend puede adaptar el componente sin duplicar la recepción de consultas.

## Preparación y activación

El responsable de la instalación debe revisar las migraciones pendientes antes de ejecutarlas. Esta implementación no migra automáticamente la base local ni cambia datos existentes. Las dos migraciones del asistente son:

```sh
php artisan migrate --path=database/migrations/2026_10_13_025958_create_contact_assistant_settings_table.php --no-interaction
php artisan migrate --path=database/migrations/2026_10_13_025959_add_assistant_fields_to_contact_messages_table.php --no-interaction
```

Requieren las tablas de Contacto, Servicios y legales del Starter. La gestión granular de permisos necesita también las migraciones existentes de roles y permisos. No ejecutar `migrate:fresh` ni volver a sembrar roles para activar el asistente en una instalación con permisos personalizados.

En **Comunicación → Asistente de Contacto**, configurar el WhatsApp de destino con `+` o `00` y código de país, guardar y activar. Se normaliza a formato internacional, de 8 a 15 dígitos. La validación comprueba estructura y longitud; no confirma que el número exista ni tenga WhatsApp. Sin destino válido, con el módulo desactivado o antes de aplicar su migración, no aparece el widget.

La página permite cambiar nombre, bienvenida, introducción, mensaje final, etiqueta del botón, obligatoriedad del correo, selector de servicios, texto de consentimiento y enlace de privacidad. El correo siempre está disponible y es opcional por defecto. Los servicios se leen del catálogo existente, únicamente activos y ordenados; no hay otro catálogo. La selección es opcional. Si no hay servicios activos se ofrece una consulta general.

La ruta de privacidad debe ser interna, por ejemplo `/legal/privacy`. La ruta predeterminada solo se muestra si la Política de Privacidad está publicada en Páginas legales. El consentimiento es obligatorio y nunca está premarcado; se conservan fecha, texto y revisión de la política al enviar. Un formulario caducado o cuyo consentimiento cambió se actualiza y exige volver a aceptarlo, conservando las respuestas en memoria.

## Incorporación en Blade

El layout del cliente debe cargar su CSS compilado con las utilidades Tailwind del componente. En el Starter se utiliza `resources/css/app.css`. Incorporar una sola vez cerca del cierre de `body`, después de las preferencias de cookies:

```blade
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    {{-- Contenido propio del cliente --}}
    <x-cookie-consent />
    <x-contact-assistant />
</body>
```

Las vistas públicas de inicio, contacto y contenido ya incluyen el componente. Renderizarlo varias veces en una respuesta no duplica el widget. Las entradas propias `resources/css/contact-assistant.css` y `resources/js/contact-assistant.js` se incluyen mediante Vite únicamente cuando puede funcionar. Compilar con `npm run build`; no instalar otra librería de chat ni Alpine adicional. El JavaScript público del Starter no incluye Alpine: este flujo utiliza JavaScript nativo y un `<dialog>` modal.

La tipografía se hereda del sitio. Los colores y la posición pueden adaptarse desde el layout, sin convertirlos en controles administrativos:

```blade
<x-contact-assistant style="--contact-assistant-accent: #5145b4; --contact-assistant-on-accent: #fff; --contact-assistant-bottom: 0px;" />
```

Variables adicionales: `--contact-assistant-chat-background`, `--contact-assistant-user-bubble`, `--contact-assistant-surface`, `--contact-assistant-text`, `--contact-assistant-muted`, `--contact-assistant-border`, `--contact-assistant-bubble` y `--contact-assistant-z-index`. Sus valores deben mantener contraste legible. El tema predeterminado responde a la preferencia oscura del dispositivo; un tema del cliente puede fijar estas variables explícitamente.

Se reservan las áreas seguras y el espacio ocupado por `[data-cookie-banner]`. Otros elementos flotantes pueden marcarse con `data-floating-obstacle`. Para contenido insertado después de inicializar el componente, usar la variable de posición o adaptar la integración del cliente. Cambios de tamaño, scroll y apertura de preferencias existentes se observan; si no queda espacio para el botón, se da prioridad al aviso de cookies. Revisar la convivencia con los elementos específicos de cada cliente. `document.dispatchEvent(new Event('contact-assistant:layout'))` recalcula las posiciones existentes.

## Flujo, errores y privacidad

El flujo presenta nombre, servicio, WhatsApp, correo, mensaje, consentimiento y resumen. Los servicios activos aparecen como botones; seleccionar uno avanza automáticamente. También se ofrece «Necesito orientación». El correo opcional y el mensaje permiten omitir la respuesta; un correo configurado como obligatorio nunca se puede omitir. Se puede retroceder o pulsar «Cambiar» en una respuesta del historial o en el resumen. Al corregir, el historial refleja los valores actuales y no duplica respuestas anteriores. La navegación usa controles nativos, etiquetas, foco visible, foco en cada pregunta, errores anunciados, cierre con Escape y retorno del foco al botón. El diálogo adapta su tamaño a la ventana y permite scroll interno; respeta movimiento reducido.

Las preguntas muestran brevemente «Escribiendo…» y revelan su texto progresivamente, sin bloquear la respuesta. La bienvenida y la introducción aparecen completas. Cerrar o corregir cancela los temporizadores; reabrir conserva el paso y las respuestas sin repetir la animación de la misma pregunta. Con movimiento reducido se muestra el texto completo directamente; cambiar esa preferencia durante la animación la completa. Un estado accesible anuncia la pregunta completa, sin narrar cada letra.

El lanzador evita los elementos flotantes existentes. Al abrir el diálogo modal se limita el desplazamiento inferior para conservar una ventana utilizable cuando el aviso de cookies ocupa mucho espacio; el aviso queda temporalmente inactivo por el comportamiento nativo del diálogo. El historial y las respuestas tienen scroll independiente, y la altura se recalcula con `visualViewport` al cambiar el espacio disponible.

Nada se guarda al abrir, cerrar o abandonar pasos. Solo la acción final registra una consulta válida. Se envía JSON a `contact-assistant.store` con CSRF, honeypot, token cifrado y el límite compartido de Contacto: 3 intentos/minuto y 20/hora por IP. Los campos se validan de nuevo en el servidor. Las respuestas permanecen en memoria; no se guardan datos personales en localStorage, sessionStorage ni cookies del widget.

El cliente bloquea doble clic durante el registro. Ante fallo de red o almacenamiento, permite reintentar con el mismo token; el servidor reutiliza la solicitud original para evitar duplicados. Después de guardar, no vuelve a enviar al reabrir el widget. Si se cierra durante el registro, se conserva el resultado pero no se abre WhatsApp automáticamente; al reabrir queda disponible el enlace de respaldo. Un estado 419 requiere recargar la sesión; un 429 requiere esperar. Un cambio de consentimiento renueva el formulario y desmarca la casilla. El usuario debe confirmar el consentimiento actualizado antes de continuar.

## WhatsApp y solicitudes recibidas

El servidor construye exclusivamente `https://wa.me/NUMERO?text=MENSAJE_CODIFICADO` usando el destino configurado y los datos guardados. El mensaje incluye nombre, WhatsApp y los campos opcionales que tengan valor. El número de destino no forma parte de la configuración pública del componente; aparece en el enlace solo después del registro.

Tras guardar se intenta abrir el enlace y se ofrece **Abrir WhatsApp** como respaldo permanente si el navegador bloquea la ventana. El visitante debe confirmar el envío dentro de WhatsApp. Preparar o abrir el enlace no demuestra que el mensaje se haya enviado ni recibido; el estado conservado es `link_prepared`, sin afirmar entrega. No se envían mensajes automáticamente.

Las consultas aparecen en **Comunicación → Mensajes de contacto**. Filtrar por **Origen → Asistente de Contacto** para distinguirlas del formulario tradicional. Se conserva el seguimiento pendiente/leído, el servicio elegido y el consentimiento. La lectura no significa respuesta. Si se elimina un servicio, su nombre original permanece en la consulta. La eliminación y retención siguen las Policies y el procedimiento existente de Contacto; no se añade un CRM ni seguimiento de apertura.

Permisos de configuración: `contact_assistant.view` y `contact_assistant.update`. Las solicitudes utilizan los permisos existentes de Contacto. El superadministrador tiene control total; un administrador sigue sus permisos configurados; un editor requiere permisos explícitos para configuración o bandeja; un usuario normal no obtiene acceso administrativo.

## Verificación y límites

```sh
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run build
git diff --check
```

Las pruebas Pest verifican el renderizado Blade, validación y persistencia reales, catálogo, consentimiento, autorización, antispam, compatibilidad de Contacto y enlaces codificados. También ejecutan pruebas Node del cliente sobre el HTML renderizado: avance, retroceso, corrección, foco, rechazo de campos, error de registro, reintento, doble clic y respaldo ante bloqueo de apertura. La simulación DOM comprueba la lógica; no sustituye una revisión visual o un lector de pantalla real.

La revisión en navegador de esta entrega se intentó: Chrome no ofreció una ventana accesible a la herramienta y el navegador integrado no estuvo disponible. Quedan pendientes comprobaciones manuales en 320 px, móvil con teclado virtual, escritorio, teclado/lector de pantalla y coexistencia con banners específicos. Tampoco se certifica apertura real de WhatsApp, rendimiento de campo/Core Web Vitals ni migraciones en MySQL de producción mediante la suite SQLite. No ejecutar pruebas con `RefreshDatabase` contra una base real con datos.

Sin JavaScript o sin soporte de diálogo nativo, el botón no se habilita; el formulario tradicional de Contacto permanece disponible. El operador debe aplicar las migraciones revisadas, compilar los recursos y verificar el frontend concreto antes de publicar.

## Resultado de la Fase 2 — 12 de octubre de 2026

Veredicto: **APTO para integración en el frontend**, con la aceptación visual y de producción pendiente indicada arriba.

Última ejecución conjunta: `php artisan test --compact`, **539 aprobadas y 2 omitidas**, 3.475 aserciones. Las 14 pruebas nuevas del widget pasaron, incluido el recorrido del cliente con renovación del consentimiento y creación real mediante el controlador. `vendor/bin/pint --test`, `npm run build` y `git diff --check` terminaron correctamente. El build conserva el aviso previo de `fontaine` opcional; no se instaló ninguna dependencia. Las entradas propias del widget pesan aproximadamente 3,45 kB de JavaScript y 1,20 kB de CSS comprimidos con gzip, sin incluir las utilidades compartidas de Tailwind.

No se ejecutaron migraciones sobre la base local, no se configuraron servicios ni credenciales, no se enviaron mensajes reales y no se hicieron commits o pushes. Se conservaron los cambios concurrentes de Portafolio. Las pruebas SQLite/DOM simulado no certifican MySQL de producción, un lector de pantalla real ni Core Web Vitals medidos.


## Rediseño de conversación — 13 de octubre de 2026

Se sustituyó la presentación de formulario por el chat de WhatsApp descrito arriba. Los cambios se limitan al componente Blade, CSS, JavaScript, sus pruebas y este documento. Se reutilizan configuración, catálogo, recepción, permisos, consentimiento, CSRF, antispam e idempotencia existentes. No se añadieron dependencias, migraciones, APIs ni cambios de infraestructura.

Las pruebas del cliente cubren selección de servicios, orientación sin catálogo, servicios desactivados, correo obligatorio y opcional, omisión, corrección desde el resumen, historial actualizado, cierre y reapertura, renovación de consentimiento, fallo de registro, reintento y prevención de duplicados. Los temporizadores se prueban con reloj controlado: escritura progresiva, texto Unicode, cancelación de preguntas anteriores, cierre, movimiento reducido y compatibilidad con el receptor global exigido por el navegador.

La revisión visual usa HTML Blade generado por las pruebas con datos ficticios y recursos compilados, servido temporalmente en localhost; no modifica la base local. Se comprobó en Chrome la ventana, el foco inicial, selección automática de servicios, cierre con Escape, retorno del foco y reapertura con avance conservado, a 320 px y con altura reducida. Se corrigieron durante esta revisión una invocación incompatible de temporizadores nativos y el espacio excesivo reservado al aviso de cookies.

Pendiente de validación manual en dispositivos reales: teclado virtual de iOS/Android, VoiceOver/TalkBack y envío efectivo dentro de WhatsApp. La emulación de Chrome y la simulación DOM no certifican esos comportamientos. El build mantiene el aviso previo de `fontaine` opcional; no se instaló esa dependencia.


Verificación final de esta entrega:

- `php artisan test --compact --filter=Contact`: **167 aprobadas**, 856 aserciones; incluye las 16 pruebas del widget y los escenarios Node.
- `php artisan test --compact`: **543 aprobadas, 1 fallida y 2 omitidas**, 3.494 aserciones. La prueba fallida es `PanelNavigationTest::test_administrator_navigation_contains_all_modules_in_their_groups_and_links_work`: durante el trabajo se modificaron concurrentemente los grupos de navegación del panel, fuera del alcance del asistente, y la prueba aún espera los nombres anteriores. Una ejecución anterior a esos cambios había aprobado 544 pruebas y omitido 2. No se alteraron esos cambios para resolver el fallo.
- `vendor/bin/pint --dirty --format agent` ejecutado; formato del archivo PHP de esta tarea comprobado adicionalmente con `vendor/bin/pint --format agent tests/Feature/ContactAssistantWidgetTest.php`. Pint con `--dirty` procesa todos los archivos pendientes aunque se añada una ruta: se revirtió su ajuste incidental de espacio en un recurso editado concurrentemente.
- `npm run build`: correcto; widget aproximadamente 4,58 kB de JavaScript y 1,88 kB de CSS con gzip, sin contar utilidades compartidas.
- `git diff --check` sobre los siete archivos de esta tarea: correcto. La comprobación global detectó espacio en blanco en un cambio concurrente del recurso de artículos, que se conservó.

Los recursos están compilados. No hay nuevas migraciones ni pasos de activación distintos de los ya documentados. En instalaciones donde el módulo ya esté activo basta recargar el frontend; las comprobaciones reales de dispositivo y envío indicadas arriba deben realizarse antes de publicar. No se ejecutaron migraciones locales, cambios de credenciales, mensajes reales, commits ni pushes.


## Fase 1 — Envío, países y móvil — 13 de octubre de 2026

El transporte del cliente ya no guarda `fetch` como un método nativo invocado con el objeto del asistente como receptor. Ahora lo ejecuta mediante una función que conserva el receptor global. Se añadió una regresión que exige ese contexto, además de diferenciar desconexión y timeout. El límite de 20 segundos cubre también la lectura de la respuesta. Los errores HTTP conservan su tratamiento: validación 422, sesión 419, disponibilidad 404 y almacenamiento; el 429 respeta `Retry-After`, incluida la ventana horaria. Un reintento utiliza el mismo token de idempotencia y no confirma registro hasta recibir una respuesta válida con un enlace seguro.

El paso de teléfono incorpora un selector buscable con bandera, nombre y prefijo. La región explícita de `navigator.languages` permite sugerir un país; un idioma sin región no se convierte en una ubicación. El fallback se configura mediante `CONTACT_ASSISTANT_DEFAULT_COUNTRY=BO` en `.env` o `contact.assistant_default_country`; `BO` es el supuesto inicial y un valor desconocido vuelve a Bolivia. No se modificó el `.env` local. La preferencia se puede corregir siempre, sin ubicación precisa, consultas por IP, servicios externos ni persistencia en el navegador. En prefijos compartidos se conserva la elección del visitante cuando es compatible; el prefijo no determina de forma inequívoca un país.

La búsqueda acepta nombre sin acentos, código de región o prefijo. El visitante puede escribir el número nacional o pegar uno con `+`/`00`: se elige el prefijo compatible y se separa del número, evitando duplicarlo. Los prefijos nacionales simples se eliminan únicamente cuando lo exige la longitud posible; se conserva el cero significativo italiano. No se intenta implementar las transformaciones complejas de marcación de cada país: si un formato local requiere reglas adicionales, utilizar el número internacional completo. Se valida prefijo, caracteres y longitudes posibles; no se certifica existencia, asignación ni registro en WhatsApp.

`config/phone-countries.php` contiene un catálogo local de 245 países y territorios, derivado de los [metadatos de Google libphonenumber v9.0.41](https://github.com/google/libphonenumber/blob/v9.0.41/resources/PhoneNumberMetadata.xml), con nombres proporcionados por la extensión Intl ya instalada. Se conservó la licencia Apache-2.0 en `config/phone-countries.LICENSE`. No se instaló libphonenumber ni otra dependencia. El catálogo se usa tanto para el cliente como para la validación del servidor.

Las solicitudes nuevas pueden incluir `phone_country` (código de región permitido) y un número nacional o internacional. El servidor vuelve a normalizar y validar dentro del servicio antes de guardar, incluyendo llamadas directas. Las integraciones anteriores que envían solo `whatsapp` internacional siguen funcionando. Se almacena el número internacional en el campo existente; no hay nuevas migraciones ni se conserva una ubicación geográfica inferida.

En móvil, las acciones se muestran en un pie fijo; solo se desplaza el contenido de la respuesta. Al cambiar de pregunta se restablece el scroll de ese contenido. La cabecera se compacta cuando falta altura, manteniendo la identificación de asistente virtual. Se mantiene el historial en la posición de la pregunta actual al reducir la ventana y se ofrece un envío compatible cuando el navegador no implementa `requestSubmit`.

Verificación real: se preparó con el fixture existente una base SQLite exclusiva en `/private/tmp/contact-assistant-phase1-browser/contact-assistant-browser.sqlite`, y se sirvió Laravel temporalmente en localhost con sesión independiente y correo desactivado. Chrome completó la consulta ficticia, guardó **una sola** fila con `phone=+447700900123`, `source=contact_assistant` y `whatsapp_status=link_prepared`, abrió el enlace prellenado y mostró la confirmación y el respaldo permanente. No se pulsó enviar dentro de WhatsApp. La aplicación local y sus consultas no se modificaron. Los datos ficticios solo se conservan en esa base temporal.

Revisión móvil en Chrome: 320 × 710 px y 320 × 360 px, búsqueda «Espana», selección de España, foco en el teléfono, prefijo nacional boliviano, avance mediante el botón fijo y convivencia con cookies. La emulación de menor altura comprueba el espacio disponible, pero no sustituye un teclado virtual físico ni VoiceOver/TalkBack; esas comprobaciones siguen pendientes en iOS/Android reales.

Archivos de esta fase: `.env.example`, `config/contact.php`, catálogo y licencia de países, `PhoneCountries`, regla `AssistantPhone`, servicio `ContactAssistant`, Blade/CSS/JavaScript del widget, pruebas de teléfono y del widget y este documento. Los cambios concurrentes de navegación y dashboard se conservaron. Campos personalizados, orden administrativo, foto y simulador corresponden a la fase 2.


Resultados finales de la fase 1:

- `php artisan test --compact`: **563 aprobadas y 2 omitidas**, 3.620 aserciones. La prueba de navegación concurrente ya pasa en el estado actual del repositorio.
- `php artisan test --compact --filter=Contact`: **186 aprobadas**, 953 aserciones; incluye 19 casos nuevos de país/teléfono y las regresiones JavaScript.
- `vendor/bin/pint --dirty --format agent --config=/private/tmp/contact-assistant-pint.json`: correcto; la configuración temporal excluyó los archivos editados por otros trabajos. Los siete archivos PHP de esta fase también se comprobaron con Pint por ruta.
- `npm run build`: correcto; aproximadamente 5,78 kB de JavaScript y 2,07 kB de CSS del widget con gzip, sin incluir el catálogo serializado ni utilidades compartidas. Permanece el aviso previo de la dependencia opcional `fontaine`.
- `git diff --check` sobre los archivos de esta fase: correcto.

No hay migraciones nuevas ni pasos manuales de activación adicionales para una instalación ya operativa. Los recursos están compilados: recargar la página para usar la versión nueva. El país de fallback se puede cambiar desde `.env`; aplicar el procedimiento habitual de configuración del despliegue si utiliza caché. No se hicieron instalaciones, cambios de servicios persistentes, cambios de credenciales, commits ni pushes.


## Fase 2 — Preguntas configurables, foto y simulador — 13 de octubre de 2026

En **Comunicación → Asistente de Contacto**, «Preguntas y orden» permite añadir preguntas y ordenarlas con arrastre o flechas. Hay ocho tipos: texto corto, texto largo, correo, teléfono internacional, número, fecha, una opción mediante botones y varias opciones mediante casillas. Se configuran etiqueta, pregunta, ayuda, visibilidad y obligatoriedad; texto admite longitudes, número admite límites y fecha admite intervalos. Hay como máximo 20 preguntas, incluidos los cinco campos del sistema, y 20 opciones por pregunta. Las respuestas mostradas no pueden superar 4.000 caracteres en conjunto.

Nombre y WhatsApp permanecen activos y obligatorios. Los campos del sistema conservan sus identificadores y tipos, pero se pueden ordenar y cambiar sus textos. Los controles existentes «Mostrar selector de servicios» y «Correo electrónico obligatorio» siguen decidiendo esos comportamientos; correo obligatorio también fuerza su visibilidad. Correo opcional y mensaje pueden ocultarse. El consentimiento y el resumen permanecen al final, fuera del editor. No se ofrecen archivos, HTML ni instrucciones ejecutables como respuestas.

El servidor valida tanto la configuración como las respuestas y rechaza identificadores desconocidos, respuestas de preguntas ocultas, opciones repetidas y valores fuera de los límites. Una revisión del esquema forma parte del token de envío: si las preguntas cambian durante la conversación, el cliente recibe la configuración y el HTML escapado actualizado, conserva respuestas compatibles por identificador y vuelve al inicio para revisión y consentimiento. Los datos del visitante siguen solo en memoria hasta el envío final.

Cada consulta conserva en `assistant_answers` las preguntas, etiquetas, tipos, valores y textos de opciones del momento del envío. La bandeja muestra esa instantánea como texto escapado. Editar las preguntas posteriormente no cambia consultas anteriores ni sus enlaces de WhatsApp. Las instantáneas son inmutables; los estados de lectura siguen funcionando como antes. Las consultas antiguas y el formulario de contacto tradicional siguen siendo compatibles.

La foto utiliza el selector y permisos de la biblioteca existente: JPG, PNG o WebP, hasta 2 MB, con conversión local WebP recortada a **200 × 200 px**. Se genera al guardar o simular, sin cola ni servicios externos. Una imagen referenciada no puede eliminarse desde la biblioteca. Si falta el archivo convertido se muestra el icono por defecto. El original se conserva en la biblioteca. Simular una foto puede generar esta conversión reutilizable, aunque no guarda la configuración del asistente.

«Simular conversación» prueba el borrador usando el mismo Blade y JavaScript del widget público. Cada ejecución reinicia el chat. Puede utilizarse con el módulo desactivado; no crea consultas, no solicita un token público ni abre WhatsApp. La acción vuelve a verificar permisos en servidor; quien solo puede ver prueba la configuración guardada. El documento del iframe contiene exclusivamente el renderizado escapado del servidor. El sandbox permite scripts y mismo origen para cargar los módulos locales; no se considera una frontera para contenido HTML arbitrario. La altura se adapta a la ventana y mantiene controles accesibles en pantallas pequeñas.

### Activación manual

En la entrega original se añadieron dos migraciones, probadas en SQLite aislado. Su aplicación local ya figura registrada en la consulta del 14 de octubre. En otros entornos, revisar cuáles faltan y aplicarlas únicamente mediante el procedimiento autorizado del operador:

```sh
php artisan migrate --path=database/migrations/2026_10_13_182824_add_conversation_configuration_to_contact_assistant_settings_table.php
php artisan migrate --path=database/migrations/2026_10_13_182825_add_assistant_answers_to_contact_messages_table.php
```

Después, recargar el panel, guardar las preguntas/foto y ejecutar «Simular conversación». Los recursos ya están compilados en el entorno de desarrollo; en otro despliegue ejecutar `npm run build`. Sin las nuevas columnas, los ajustes nuevos permanecen deshabilitados y el chat anterior funciona. Si existe configuración nueva sin la tabla de respuestas preparada, el envío se bloquea para impedir perder respuestas.

El rollback se admite únicamente cuando las columnas nuevas no contienen configuración ni respuestas. Las migraciones rechazan eliminar instantáneas o preguntas guardadas; no purgar datos para forzar un rollback. No se añadieron paquetes ni se modificaron credenciales o servicios persistentes.

Archivos principales: `ContactAssistantFields`, `ContactAssistant`, modelos de ajustes/mensajes/medios, `MediaSelection`, página Filament y bandeja, controlador/request/componente, vistas del editor/simulador/campos, JavaScript/CSS del chat, dos migraciones y pruebas `ContactAssistantFieldsTest` y Node. Se conservaron los cambios concurrentes de navegación y dashboard.

Verificación histórica de esa entrega: `php artisan test --compact`, **607 aprobadas y 2 omitidas**, 3.835 aserciones. Las 44 pruebas de `ContactAssistantFieldsTest` también pasaron por separado. `vendor/bin/pint --dirty --format agent` con exclusiones temporales de cambios ajenos, Pint por rutas de esta fase, `npm run build` y comprobación de espacios de archivos modificados/nuevos: correctos. El build conserva el aviso previo de `fontaine` opcional. Chrome verificó el recorrido inicial y preguntas personalizadas, también a **320 × 360 px**, y el renderizado dentro del iframe administrativo con controles visibles. Se utilizó únicamente HTML de pruebas con datos ficticios en un servidor temporal.

Pendiente de aceptación en dispositivos reales: teclado virtual iOS/Android y VoiceOver/TalkBack. La suite SQLite no certifica migraciones de producción MySQL ni envío real dentro de WhatsApp. No se hicieron commits, pushes ni despliegues.
