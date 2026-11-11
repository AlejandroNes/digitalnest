# Comunicación: funcionamiento, aceptación e historial

Actualizado: **14 de octubre de 2026**. Las tres fases de mejora de comunicación están implementadas. Su aceptación externa no está certificada. Este documento complementa [04 · Despliegue](04-DESPLIEGUE.md); la evidencia global está en [05](05-AUDITORIA-FINAL.md).

## Operación vigente

### Contacto y bandeja

El contacto tradicional y el asistente conservan consultas en `ContactMessage`. Validación, CSRF, antispam, límites e idempotencia protegen el envío. El formulario se guarda antes del intento de notificación: un fallo SMTP no elimina la consulta y la confirmación del visitante describe el guardado.

La bandeja distingue origen, pendiente/leído e intento/aceptación de notificación, con búsqueda, filtros y fechas. Leer no significa responder; aceptación SMTP no demuestra recepción al buzón. Responder desde el cliente de correo habitual al visitante. Si falta una notificación, revisar primero la bandeja. No hay reenvíos automáticos ni cola obligatoria.

### SMTP y recuperación de contraseña

- Los ajustes del panel se aplican a contacto y prueba de envío. La recuperación de contraseña usa el correo general del entorno.
- Las credenciales del panel se cifran y no se vuelven a mostrar. Campos vacíos conservan secretos existentes; la sustitución o eliminación es explícita.
- Los servidores del panel deben estar autorizados en `CONTACT_SMTP_ALLOWED_HOSTS`. Se exigen TLS y verificación de certificados; 465 usa `smtps` y 587 `smtp` con STARTTLS. Otros puertos se declaran por el operador con su esquema.
- Respaldo del entorno mediante `MAIL_*`/`CONTACT_*`; revisar el resumen efectivo antes de activar. No modificar `.env` ni reconstruir cachés de infraestructura desde Filament.
- Guardar ajustes en base permite leer cambios entre peticiones; cambiar variables del entorno requiere la gestión de caché de configuración del operador.
- La prueba requiere permiso, validación y límites. No usar direcciones arbitrarias, mailer `log` con datos reales ni certificados desactivados.

### Asistente

Prepara un enlace de WhatsApp después del registro idempotente y conserva las respuestas de ese momento. No envía mensajes por API ni confirma entrega. Preguntas, foto, simulación y teléfonos se documentan en [09 · Asistente](09-ASISTENTE-DE-CONTACTO.md).

## Aceptación que queda fuera de esta entrega

Recepción real, recuperación de contraseña, SPF/DKIM/DMARC, restricciones del hosting y persistencia entre releases deben probarse en un destino identificado con buzones controlados. Se requieren datos del entorno y autorización de envío; no solicitar secretos por chat.

El usuario descartó esta verificación externa como tarea de la sesión. No se crearon bases ni se enviaron correos reales para retomarla. Las pruebas MySQL opcionales no se sustituyen por SQLite ni por una inspección de índices.

La revisión visual completa de la bandeja/SMTP en móvil, teclado y consola sigue pendiente. Las comprobaciones de Chrome del blog y del asistente no certifican esta pantalla. Las instrucciones antiguas de trabajar sin Chrome se conservan como contexto histórico, no como una nueva regla general.

## Historial de las mejoras autorizadas

Los conteos y observaciones siguientes corresponden a sus fechas originales. No reemplazan la suite vigente del informe [05](05-AUDITORIA-FINAL.md) ni equivalen a tres despliegues reales.

<details>
<summary>Diagnóstico, plan e informes anteriores de Comunicación</summary>

Fecha: 9 de octubre de 2026. Estado: desarrollo de las tres fases autorizado y aplicado; validación automatizada local concluida. La aceptación externa de producción y la revisión visual siguen pendientes. No se enviaron correos reales.

## Objetivo y alcance

Preparar **Configuración de correo** y **Mensajes de contacto** para un uso fiable en producción, con instrucciones breves dentro de cada sección. Conservar Laravel Mail, Filament, las tablas actuales, los permisos y los mensajes existentes.

La compatibilidad se define por capacidades, no por la marca del hosting: PHP 8.4 y las extensiones requeridas por el proyecto (incluido OpenSSL), base persistente, HTTPS, permisos de escritura y conexión a un SMTP autorizado con TLS. Se contempla hosting compartido, VPS y plataformas administradas sin exigir Redis, procesos permanentes, workers ni cron. Un proveedor que bloquee todas las conexiones SMTP no puede resolverse cambiando campos del panel: el operador debe habilitar la conexión o aprobar otra integración. No se promete entrega universal ni llegada garantizada a la bandeja de entrada.

## Diagnóstico del estado actual

Revisión de `AGENTS.md`, README, arquitectura, requisitos, plan general, despliegue y contexto de la biblioteca multimedia; inspección de servicios, modelos, migraciones, páginas, recursos, Policies, rutas, plantillas y pruebas de Comunicación. `.ai/rules` no existe. El repositorio contiene trabajo previo sin commit, que se conservará.

Stack comprobado: Laravel 13.35.0, Filament 5.10.1, Livewire 4.4.7 y Symfony Mailer 8.1.7. Se consultó Laravel Boost y se contrastaron las opciones SMTP con el código instalado de Laravel y Symfony.

| Área | Funcionalidad que se conserva | Problema o límite observado |
| --- | --- | --- |
| Contacto público | Validación, CSRF, honeypot, límites por IP, token cifrado y deduplicación por envío | El envío de correo es síncrono; los límites de tiempo del hosting y la conectividad deben verificarse |
| Persistencia | El mensaje se guarda antes de notificar; un fallo SMTP no lo elimina | La prueba local no demuestra persistencia entre despliegues en un hosting concreto |
| Bandeja | Búsqueda, filtros, paginación, detalle, pendiente/leído y eliminación autorizada | No muestra el intento o la aceptación de la notificación, aunque existen sus timestamps |
| SMTP | Configuración del entorno o del panel; hosts permitidos, credenciales cifradas y campos de escritura | El panel solo permite 465/587; puerto y seguridad se eligen independientemente; otros puertos autorizados no son seleccionables |
| Transporte | SMTP del panel exige TLS y verifica certificados; timeout configurado en 10 segundos | El modo de entorno no impone explícitamente las mismas opciones TLS. El modo del panel no incorpora el `local_domain`/EHLO del entorno |
| Notificaciones | Activación, destinatarios y remitente con respaldo del entorno | Se puede guardar la activación sin destinatarios efectivos; el fallo aparece al notificar. Falta un resumen comprensible de qué configuración se usará |
| Respuesta | Plantillas HTML/texto, identidad del sitio y contenido escapado | `ContactMessageReceived` no establece `Reply-To` con el correo del visitante |
| Prueba de correo | Usa configuración guardada, permisos y límite de 3 pruebas/minuto | Los errores son genéricos y no ayudan a distinguir datos incompletos, cifrado ilegible y fallo de transporte |
| Seguridad | Policies, acceso administrativo, secretos ocultos y logs sin contenido sensible | Ampliar pruebas de revocación de permisos y conservación/sustitución de credenciales, especialmente al cambiar de servidor |

**Evidencia ejecutada:**

```sh
php artisan test --compact tests/Feature/MailSettingsTest.php tests/Feature/ContactMailTest.php tests/Feature/ContactSubmissionTest.php tests/Feature/ContactAdministrationTest.php
```

Resultado: **68 pruebas aprobadas, 381 aserciones**. Las pruebas usan fakes/mocks e infraestructura de prueba aislada; no certifican una conexión SMTP real. Laravel Boost confirmó mediante una consulta de solo lectura **0 mensajes y 0 registros de configuración de correo** en la base local. No se leyeron ni expusieron secretos.

## Decisiones técnicas

1. Mantener `ContactMail` como servicio único de notificaciones y pruebas; reutilizar su validación del transporte, remitente y destinatarios. No crear un segundo sistema de envío.
2. Conservar la precedencia: SMTP del panel habilitado reemplaza el transporte para contacto y pruebas; deshabilitado utiliza el entorno. Los campos vacíos de remitente/destinatarios mantienen su respaldo actual. El correo público de Configuración general no equivale automáticamente al remitente SMTP.
3. Mantener separados los correos de autenticación: siguen usando Laravel Mail y `MAIL_*`. La guía explicará esta diferencia; no se modificará globalmente el mailer para resolver contacto.
4. Mantener hosts y puertos autorizados por el desarrollador. Añadir una lista configurable de puertos permitidos con defaults 465/587; puertos alternativos, por ejemplo 2525 con STARTTLS, se habilitarán únicamente si el proveedor y el operador lo requieren. No aceptar destinos arbitrarios ni añadir un control para desactivar certificados/TLS.
5. Validar las combinaciones de puerto/seguridad: 465 con TLS implícito y 587 con STARTTLS; cualquier puerto adicional necesita una combinación segura declarada por el operador. Reutilizar la misma definición en formulario y servicio.
6. Incorporar el dominio EHLO desde la configuración existente (`MAIL_EHLO_DOMAIN`/dominio de la aplicación), sin convertirlo en un campo técnico obligatorio para el administrador. Validar SMTP seguro también para el modo de entorno utilizado por Comunicación en producción.
7. Conservar envío síncrono y un timeout prudente. El timeout de socket no garantiza un límite total de diez segundos para toda la petición: medir el flujo completo con los límites del servidor destino.
8. Conservar el intento único automático. No se añaden reintentos, colas ni reenvíos masivos. Una notificación sin aceptación confirmada requiere revisar la bandeja y la configuración; no equivale necesariamente a un rechazo, pues podría haberse aceptado antes de fallar la actualización del registro.
9. Conservar las tablas y los timestamps existentes. Los estados informativos se calculan con ellos; **no se prevé ninguna migración** para el alcance base.

## Fase 1 — Configuración segura y portable

**Objetivo:** que los datos guardados produzcan una configuración válida y predecible en el hosting, preservando credenciales y configuración existente.

### Trabajo previsto

- Centralizar las opciones de host/puerto/seguridad utilizadas por el formulario y el servicio, sin introducir una capa genérica de proveedores.
- Añadir puertos alternativos autorizables desde configuración; mantener los actuales como defaults. Documentar esta capacidad en `.env.example` con valores vacíos o de ejemplo, sin secretos.
- Validar combinaciones TLS, aplicar el dominio EHLO y preservar verificación de certificados y timeout.
- Validar los destinatarios y el remitente **efectivos** antes de guardar notificaciones activadas; un campo vacío puede ser válido si su respaldo del entorno es válido. No hacer conexiones de red al guardar.
- Mostrar un resumen sin secretos: notificaciones activadas/desactivadas, modo de servidor, remitente efectivo y destinatarios efectivos, visible solo para quien tiene permiso.
- Conservar usuario y contraseña al guardar campos vacíos. Al cambiar de host, exigir sustitución o una confirmación explícita de reutilización; evitar enviar credenciales anteriores inadvertidamente a otro servidor permitido.
- Reautorizar con el usuario vigente antes de guardar o enviar pruebas. Conservar el modo de recuperación ante credenciales ilegibles, sin regenerar `APP_KEY` ni mostrar ciphertext.
- Añadir `Reply-To` validado al correo de contacto; mantener `From` como remitente autorizado del proveedor.

**Archivos previstos:** `app/Services/ContactMail.php`, `app/Models/MailSetting.php` si necesita helpers pequeños, `app/Filament/Pages/MailSettings.php`, `app/Mail/ContactMessageReceived.php`, `config/contact.php`, `.env.example`, `tests/Feature/MailSettingsTest.php` y `ContactMailTest.php`.

**Datos e integraciones:** sin migración, sin renombrar campos ni modificar datos existentes automáticamente. Conservar el singleton, cifrado, plantillas e identidad de Biblioteca multimedia. No cambiar la recuperación de contraseña.

**Pruebas:** modos panel/entorno y `MAIL_URL`; 465/587 y puerto adicional expresamente permitido; combinaciones inválidas, destinos no autorizados, TLS/certificados y EHLO en el transporte construido sin enviar a Internet; notificaciones activas sin destinatarios efectivos; guardar/recuperar con respaldo del entorno; conservación y sustitución de secretos; permisos revocados; `From` y `Reply-To` correctos. Comprobar que guardar no realiza conexiones SMTP.

**Aceptación:** una configuración incompleta no se presenta como lista; las opciones autorizadas se guardan y recuperan; ningún secreto aparece en HTML, snapshots o logs; cambiar el transporte de contacto no altera autenticación ni pierde datos. Las configuraciones heredadas incompatibles se señalan para revisión, sin reescribirlas silenciosamente.

## Fase 2 — Bandeja clara y ayuda para el administrador

**Objetivo:** que el administrador entienda qué revisar, cómo configurar y qué significa el resultado de un envío.

### Trabajo previsto

- Mantener las dos entradas de navegación en Comunicación; no crear páginas o recursos independientes de ayuda.
- Incorporar textos breves de la guía incluida abajo mediante descripciones, helper texts y una sección desplegable de ayuda rápida. Los detalles de infraestructura quedarán en la documentación del desarrollador.
- En la bandeja y el detalle, distinguir **estado de lectura** de **estado de notificación**:
  - «Sin intento registrado»: ningún timestamp de intento o aceptación.
  - «Intentada, sin aceptación confirmada»: intento registrado y aceptación ausente.
  - «Aceptada por el servidor»: `notified_at` registrado; no afirmar entrega al buzón.
- Añadir fechas de intento/aceptación y filtro de notificación con paginación y consultas simples. Los registros históricos sin timestamps conservan un estado honesto.
- Aclarar que abrir un mensaje no demuestra respuesta al visitante. Mantener pendiente/leído y el contenido original inmutable.
- Facilitar respuesta desde el cliente habitual de correo mediante el `Reply-To` de fase 1; en el detalle, mostrar claramente el correo del visitante. No añadir un editor de respuestas ni un sistema de tickets.
- Mejorar la prueba con estados de carga, prevención de doble clic y mensajes seguros: configuración incompleta, credenciales que requieren revisión o transporte sin aceptación confirmada. No presentar respuestas SMTP crudas ni prometer diagnósticos más específicos de los que la excepción permite.
- Usar componentes nativos Filament, etiquetas españolas, diseño responsive, foco visible y el estilo existente: principal `#6C5CE7`, texto blanco en botones principales. La ayuda no debe dominar el formulario.

**Archivos previstos:** `app/Filament/Pages/MailSettings.php`, `resources/views/filament/pages/mail-settings.blade.php`, `app/Filament/Resources/ContactMessages/ContactMessageResource.php`, sus Pages si necesitan una vista de ayuda, `app/Models/ContactMessage.php` para helpers/scopes concretos, `app/Services/ContactMail.php` y pruebas de administración/correo. CSS del panel solo si los componentes nativos no bastan.

**Datos e integraciones:** utilizar `notification_attempted_at` y `notified_at` actuales. No convertir datos históricos, marcar mensajes automáticamente como respondidos ni cambiar permisos de usuarios ordinarios.

**Pruebas:** estado y filtros de notificación, fechas, búsqueda/paginación, contenido escapado, lectura independiente de correo, cambio de estado/eliminación con Policy, acceso directo denegado y revocación de permisos. Verificación real a 360/390/768/1440 px, teclado y consola cuando se autorice el navegador.

**Aceptación:** cada sección tiene una instrucción útil y breve; el administrador distingue guardado, lectura, aceptación SMTP y recepción; la interfaz funciona en móvil; los fallos no exponen datos sensibles ni borran consultas. La revisión visual pendiente se declara como tal.

## Fase 3 — Validación de producción y guía de despliegue

**Objetivo:** comprobar el funcionamiento con una configuración real del entorno destino y dejar un procedimiento repetible por cliente.

### Trabajo previsto

- Actualizar `docs/04-DESPLIEGUE.md` con requisitos mínimos y comprobaciones de hosting compartido, VPS y plataforma administrada.
- Para hosting sin Node/SSH, documentar compilación e instalación desde lockfiles en un entorno de construcción compatible y publicación del artefacto por el responsable. No asumir Node o Composer disponibles en el servidor de producción.
- Verificar conexión SMTP saliente, resolución DNS, certificados, remitente autorizado y configuración SPF/DKIM/DMARC provista por el servicio de correo. No modificar DNS o firewall desde el panel.
- Comprobar `APP_URL` HTTPS, clave existente, persistencia de MySQL, sesiones, caché y directorios escribibles. En plataformas con archivos efímeros, mantener la identidad y los archivos en almacenamiento persistente autorizado; reutilizar la solución existente.
- Probar caché de configuración en una copia independiente: cambios del panel se reflejan sin editar `.env`; cambios del entorno requieren reconstruir su caché por el operador. No limpiar sesiones ni límites de envío como solución genérica.
- Verificar actualizaciones conservando `APP_KEY`, datos, credenciales y mensajes históricos. No ejecutar seeders que sobrescriban datos ni reiniciar tablas.
- Ejecutar aceptación real con datos de prueba identificables: correo de prueba recibido, contacto guardado/notificado, respuesta al visitante mediante `Reply-To`, revisión de bandeja y comprobación separada de recuperación de contraseña.
- Simular fallo SMTP en staging y comprobar que la consulta permanece disponible. No provocar fallos ni cambiar credenciales reales para esta simulación en producción.

**Archivos previstos:** documentación de despliegue y este plan; pruebas actuales de contacto/correo y `PerformanceSecurityTest.php` cuando proceda. No se prevén cambios de infraestructura automatizados ni migraciones.

**Verificaciones:** ejecutar pruebas automatizadas aisladas, formato, build y diff check. Para pruebas MySQL de escritura utilizar una base independiente. La matriz de pruebas incluirá las tres clases de hosting; se registrará únicamente lo efectivamente comprobado y los límites de cada destino.

```sh
php artisan test --compact
vendor/bin/pint --dirty --format agent
vendor/bin/pint --test
npm run build
git diff --check
```

**Aceptación:** el destino real conserva ajustes tras nuevas peticiones/despliegues; se confirma al menos una recepción real y un contacto completo; un fallo controlado conserva el mensaje; credenciales y permisos están protegidos. No declarar todas las clases de hosting verificadas si solo se probó una.

## Guía simple para integrar en el panel

La guía ya está integrada mediante descripciones, ayudas, resumen guardado, sección desplegable y explicación de la bandeja.

### Configuración de correo → Notificaciones de contacto

> Activa las notificaciones si quieres recibir por correo las consultas del sitio. Añade los correos del equipo que debe recibirlas. Usa como remitente una dirección autorizada por tu proveedor. Las consultas también quedan guardadas en Mensajes de contacto.

**Pasos:** activar notificaciones → revisar destinatarios y remitente efectivos → guardar. Los campos vacíos usan la configuración preparada por el responsable del sitio; revisar el resumen antes de activar.

### Configuración de correo → SMTP

> Usa el servidor ya configurado para el sitio o introduce los datos entregados por tu proveedor de correo. Elige exactamente el servidor, puerto y seguridad indicados. Si tu servidor no aparece, pide al responsable del sitio que lo habilite.

**Datos necesarios:** servidor permitido, puerto, seguridad, usuario y contraseña proporcionados por el proveedor. Normalmente 465 corresponde a TLS y 587 a STARTTLS. No adivinar valores ni usar la contraseña del panel administrativo como contraseña SMTP.

> Dejar las credenciales vacías conserva las guardadas. Para cambiarlas, introduce las nuevas; para eliminarlas, utiliza la opción explícita. Si cambias de proveedor, revisa también el remitente y sustituye las credenciales.

### Configuración de correo → Enviar correo de prueba

> Guarda primero la configuración. Envía una prueba a un buzón que puedas revisar y comprueba Recibidos y Spam. «Aceptada por el servidor» significa que el servidor recibió el envío; confirma que el mensaje llegó antes de dar la configuración por lista.

**Si falla:** revisar datos del proveedor, remitente autorizado y configuración guardada. Si persiste, comunicar al responsable del sitio la hora y el resultado mostrado, sin enviar contraseñas. No repetir pruebas indefinidamente; existe un límite por minuto.

### Mensajes de contacto

> Revisa las consultas pendientes, abre cada mensaje y responde desde tu correo habitual al visitante. Marca como leído cuando lo hayas revisado. El estado de lectura es independiente de la notificación por correo.

**Si no llega una notificación:** revisar primero esta bandeja; la consulta puede estar guardada aunque el correo falle. «Sin intento registrado» no implica que el visitante no haya enviado el mensaje. No hay reenvíos automáticos.

> Eliminar borra la consulta de la bandeja; hazlo solo cuando ya no sea necesaria y según las instrucciones de tu organización. Marcar como leído conserva el mensaje.

### Nota relevante junto a SMTP

> Estos ajustes sirven para las notificaciones de contacto y el correo de prueba. Los correos para recuperar la contraseña del panel los configura el responsable del sitio por separado.

## Dependencias, límites y autorizaciones

- **Inicio del desarrollo:** autorizado por «aplica las tres fases de una vez». Se aplicaron secuencialmente en esta tarea.
- **Hosting real:** necesitar su configuración y las restricciones de red. Los secretos se introducen por un canal seguro o directamente en el panel; no se solicitan en el chat ni se guardan en documentos.
- **Envíos reales:** requieren autorización y destinatarios de prueba controlados. Esta auditoría no envió mensajes.
- **Infraestructura:** habilitar hosts/puertos, modificar variables de entorno, DNS, certificados, cachés de producción o servicios corresponde al operador y requiere autorización. No se harán estos cambios al desarrollar la interfaz.
- **Base de datos:** no se prevén migraciones; cualquier necesidad posterior se justifica y revisa antes de modificar datos reales.
- **Navegador:** sigue vigente la instrucción de continuar sin Chrome. La fase visual no se certificará hasta contar con una sesión autorizada.
- **Alternativas:** si el hosting impide SMTP, se analizará aparte una API de correo. No instalar bridges ni dependencias sin autorización. Reintentos manuales/automáticos, colas, webhooks y métricas avanzadas no forman parte del alcance base.
- **Entrega:** sin commits, pushes, instalación o actualización de paquetes, migraciones ni modificaciones de datos/credenciales reales.

## Referencias verificadas

- Laravel Boost: documentación para el stack instalado sobre configuración, cifrado y componentes Filament; contraste con `Illuminate\Mail\MailManager` y `Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory` instalados.
- [Laravel 13: correo](https://laravel.com/docs/13.x/mail), [configuración](https://laravel.com/docs/13.x/configuration) y [cifrado de atributos](https://laravel.com/docs/13.x/eloquent-mutators#encrypted-casting).
- [Symfony Mailer: TLS obligatorio, dominio local y aceptación del transporte](https://symfony.com/doc/current/mailer.html). Las opciones se comprobaron también en la versión 8.1.7 instalada.
- [Filament 5: secciones](https://filamentphp.com/docs/5.x/schemas/sections).

## Informe de aplicación — 9 de octubre de 2026

### Cambios realizados

| Fase | Resultado implementado | Verificación y límites |
| --- | --- | --- |
| 1 | Puertos/esquemas centralizados; 465/587 protegidos y puertos adicionales declarados por operador; TLS/certificados/EHLO en ambos modos; activación valida remitente/destinatarios efectivos y transporte; resumen sin secretos; cambio de host exige sustituir ambas credenciales o eliminarlas; usuario vigente reautorizado; Reply-To validado. | Construcción del transporte real de Symfony sin conectar; pruebas de rechazo, guardado y recuperación; no se modificó el mailer general ni la base real. |
| 2 | Ayuda breve responsive con componentes nativos Filament; resumen y prueba con estados de carga y diagnóstico seguro; bandeja con lectura/notificación separadas, fechas y filtro; instrucciones de respuesta y eliminación. | HTML/componentes Livewire, filtros, estados, escape, permisos y secretos comprobados automáticamente. Navegador, teclado y consola visual pendientes. |
| 3 | Guía operativa para compartido/VPS/administrado, construcción sin Node/SSH, conservación de clave/base/archivos, caché y procedimiento de aceptación real; suite reforzada. | Prueba con `WithCachedConfig` nativo y configuración aislada en memoria: nuevas peticiones leen cambios del panel y preservan mensajes. No se ejecutó `config:cache` contra el entorno real ni se certificó un hosting destino. |

**Decisiones concretas:** al cambiar de host se exige sustitución completa o eliminación explícita, sin un control adicional de reutilización. Los puertos del panel salen de `CONTACT_SMTP_PORT_SCHEMES` y mantienen 465:smtps/587:smtp aunque haya declaraciones inválidas. En el entorno, `MAIL_PORT`/`MAIL_SCHEME` son decisiones del operador: los puertos conocidos deben coincidir; los adicionales permiten SMTP con STARTTLS obligatorio o SMTPS explícito. Las configuraciones incompatibles se señalan y no se reescriben automáticamente.

**Archivos tocados en esta tarea:** `.env.example`, `config/contact.php`, `app/Services/ContactMail.php`, `app/Mail/ContactMessageReceived.php`, `app/Filament/Pages/MailSettings.php`, `resources/views/filament/pages/mail-settings.blade.php`, `app/Models/ContactMessage.php`, `app/Filament/Resources/ContactMessages/ContactMessageResource.php`, `app/Filament/Resources/ContactMessages/Pages/ListContactMessages.php`, `tests/Feature/MailSettingsTest.php`, nuevo `tests/Feature/CommunicationProductionTest.php`, este documento y `docs/04-DESPLIEGUE.md`. Se conserva el trabajo previo sin commit.

**Pruebas nuevas:** transporte nativo seguro panel/entorno, declaración de puertos, combinaciones incompatibles, credenciales inseguras, activación con respaldos incompletos, caché de configuración y lectura posterior de cambios sin pérdida de mensajes, sustitución segura al cambiar host, revocación en base con usuario autenticado obsoleto, Reply-To/anti-inyección, resumen con cifrado ilegible, filtros/fechas de notificación y lectura independiente. Las pruebas existentes conservan cobertura de fallo SMTP, deduplicación, autorización y logs seguros; los mocks del servicio en pruebas de UI se hicieron parciales para permitir renderizar su resumen real.

### Aceptación externa pendiente

No se considera certificada la fase 3 en producción hasta completar el procedimiento de `docs/04-DESPLIEGUE.md` en una instalación identificada. Faltan hosting destino, SMTP autorizado, buzones controlados y autorización para enviar; no solicitar secretos por chat. Continúa vigente la instrucción de no usar Chrome: no se certifican dimensiones, foco/teclado ni ausencia de errores JavaScript mediante el build. La matriz de compartido/VPS/administrado es un procedimiento documentado, no tres despliegues comprobados. Las pruebas MySQL opcionales requieren una base explícita independiente, sin tocar la base actual.

### Resultados ejecutados

| Comando/comprobación | Resultado |
| --- | --- |
| Suite enfocada de Comunicación, incluidas las primeras 15 pruebas nuevas | 83 aprobadas, 502 aserciones; la prueba adicional de declaración de puertos se añadió después. |
| `php artisan test --compact tests/Feature/CommunicationProductionTest.php` | 16 aprobadas, 122 aserciones. |
| `php artisan test --compact` | Se detuvo por límite de memoria local de PHP de 128 MB en `FileinfoMimeTypeGuesser` durante pruebas de imágenes. No fue un fallo de aserción; este comando sin ajustes no se declara exitoso. |
| `php artisan test --compact -d memory_limit=512M` | 376 pruebas: **374 aprobadas, 2 omitidas**, 2382 aserciones. El ajuste solo afectó al proceso de prueba; no se modificó PHP/hosting. |
| `vendor/bin/pint --dirty --format agent` y formato explícito de archivos tocados | Ejecutados. |
| `vendor/bin/pint --test` | Aprobado. |
| `npm run build` | Aprobado; permanece el aviso previo de la dependencia opcional `fontaine`, sin instalarla. |
| `git diff --check` | Aprobado; se comprobó además whitespace con `git diff --no-index --check` en los 12 archivos tocados que siguen sin seguimiento en Git. |

Las dos omisiones son las pruebas MySQL optativas que requieren `MYSQL_AUDIT_DATABASE` y `MYSQL_MEDIA_TEST_DATABASE`; no se preparó ni reinició ninguna base real. Las verificaciones locales usan SQLite en memoria y fakes/mocks para envíos. Consultas, contenido histórico, rutas y permisos existentes se conservaron; no se ejecutaron migraciones ni seeders en el entorno real.

Se consultaron las [aserciones oficiales de PHPUnit 12.5](https://docs.phpunit.de/en/12.5/assertions.html) y Laravel Boost para caché de configuración, filtros y Envelope/Reply-To, además de contrastar TLS/EHLO con Symfony 8.1.7 instalado. No se actualizaron dependencias.


</details>
