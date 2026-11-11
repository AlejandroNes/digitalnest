# Despliegue del Starter Kit

Actualizado: 14 de octubre de 2026. Este documento es un procedimiento para el operador, no un registro de despliegue completado. Estado verificable: [05 · Auditoría](05-AUDITORIA-FINAL.md).

## Requisitos y preparación

Cada cliente necesita un repositorio, dominio, base de datos y almacenamiento independientes. Usar PHP 8.4 con PDO MySQL, GD con WebP, mbstring, OpenSSL, XML y las extensiones exigidas por Composer; MySQL 8.4; Node compatible con el `engines` del Vite bloqueado. Conservar `composer.lock` y `package-lock.json`. No ejecutar `composer update` ni cambiar versiones al desplegar.

Comprobar en el entorno destino:

```sh
php -v
composer check-platform-reqs
node --version
npm --version
```

El entorno local auditado usa MySQL 9.7.1. La suite automatizada usa SQLite en memoria; confirmar también las migraciones y la suite en una base MySQL de pruebas independiente antes de publicar, especialmente si el destino es 8.4. Nunca apuntar pruebas con `RefreshDatabase` o `DatabaseMigrations` a la base de producción.

## Nueva instalación

Estos comandos son instrucciones para el responsable del despliegue; no se ejecutan automáticamente desde el panel. Instalar desde los lockfiles en una copia nueva autorizada:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
cp .env.example .env
```

Completar `.env` antes de migrar. Ejemplo, con valores reales provistos de forma segura:

```dotenv
APP_NAME="Empresa"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://empresa.example
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=empresa
DB_USERNAME=empresa_app
DB_PASSWORD="SECRETO"
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_DOMAIN=null
CACHE_STORE=database
CACHE_PREFIX=empresa_cache_
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
LOG_LEVEL=warning
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.example
MAIL_PORT=465
MAIL_USERNAME="USUARIO"
MAIL_PASSWORD="SECRETO"
MAIL_FROM_ADDRESS=contacto@empresa.example
MAIL_FROM_NAME="Empresa"
CONTACT_NOTIFICATIONS_ENABLED=true
CONTACT_NOTIFICATION_RECIPIENTS=administracion@empresa.example
CONTACT_SMTP_ALLOWED_HOSTS=smtp.example
```

Generar una clave únicamente para una instalación nueva. Conservar de forma segura la `APP_KEY` existente en actualizaciones: cambiarla invalida cookies y sesiones y hace ilegibles las credenciales SMTP cifradas. No versionar `.env`, logs, mensajes, bases de datos ni imágenes de clientes.

```sh
php artisan key:generate --no-interaction
php artisan migrate --seed --force --no-interaction
php artisan storage:link --no-interaction
php artisan app:create-admin
npm run build
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
```

`app:create-admin` es interactivo y oculta la contraseña. Para aprovisionar la cuenta superadministradora designada, ejecutar por separado `php artisan app:setup-super-admin` de forma interactiva y con autorización sobre esa cuenta; ver [08 · Roles](08-ARQUITECTURA-ROLES-Y-PANELES.md). Solo esa cuenta puede guardar la plantilla de permisos del administrador. El seeder sincroniza permisos y no crea usuarios; conservar la configuración de permisos existente al actualizar. No pasar secretos como argumentos: `app:setup-super-admin` todavía admite `--password`, pero esa opción puede quedar visible en historial o procesos. El seeder no crea cuentas con credenciales predeterminadas. Las migraciones son reversibles, pero un rollback puede eliminar contenido de las tablas creadas; revisar antes de ejecutarlo. Para actualizar, mantener `.env`, clave, base y archivos, revisar las nuevas migraciones y recompilar cachés y recursos durante la ventana de mantenimiento del operador. No usar `migrate:fresh` en una instalación con datos.

En producción, `composer install` conserva el script existente `filament:upgrade` para publicar los assets de Filament. La compilación necesita las dependencias de desarrollo de npm, aunque Composer se instale con `--no-dev`. Publicar `public/build` y retirar cualquier `public/hot` de desarrollo. Las fuentes de Vite pueden necesitar red durante la compilación; existe un aviso opcional de `fontaine` que no bloquea el build y no se añadió otro paquete.

## Servidor web y HTTPS

La raíz del sitio debe ser **`public/`**, nunca la raíz del repositorio. Usar PHP-FPM y el front controller `public/index.php`; Nginx debe resolver rutas con `try_files $uri $uri/ /index.php?$query_string`. Apache debe conservar las reglas de `public/.htaccess`. Servir `robots.txt` mediante Laravel: no crear un archivo estático que oculte la ruta dinámica.

Bloquear archivos ocultos y acceso a `.env`, `.git`, `vendor`, `storage/logs` y archivos privados. Permitir únicamente `public/index.php` como script PHP y prohibir ejecución de PHP/scripts en `/storage` y directorios de subidas. El enlace `/storage` publica solo `storage/app/public`. Configurar límites de subida coherentes: imágenes hasta 5 MB, 6000 × 6000; `upload_max_filesize`, `post_max_size` y el límite del servidor deben permitirlo sin aceptar tamaños ilimitados.

El usuario de PHP debe poder escribir en `storage/` y `bootstrap/cache/`; ajustar propietario/grupo del hosting y permisos mínimos, sin `chmod 777`. Configurar HTTPS y redirigir HTTP en el servidor. Si hay proxy inverso, declarar exclusivamente proxies y hosts de confianza según el hosting para que Laravel detecte HTTPS y la IP real; no confiar en encabezados reenviados de cualquier cliente. Verificar cookies `Secure`, `HttpOnly`, `SameSite=Lax` y acceso al panel después del cambio.

La aplicación envía `nosniff`, `SAMEORIGIN`, una política de referrer y `private, no-store` para respuestas dinámicas, incluidas las de error. Envía HSTS solo en HTTPS de producción, sin `includeSubDomains` ni preload. Mantener recursos estáticos de Vite e imágenes con caché HTTP del servidor si procede. No cachear HTML con formularios, sesiones o consentimiento en CDN/proxies. No se aplica CSP restrictiva: cualquier futura política debe comprobar Filament, Livewire, scripts de consentimiento, Analytics y assets del cliente.

## Correo, privacidad y SEO

Laravel usa las variables `MAIL_*` para sus correos generales, incluida recuperación de contraseña. Las notificaciones de contacto usan los valores del panel si están establecidos y las variables de entorno como respaldo; SMTP del panel solo se utiliza cuando su interruptor está activo. Destinatarios y activación vacíos en el panel usan `CONTACT_*`. El panel no modifica `.env` ni la configuración global de Laravel Mail.

Los hosts SMTP del panel deben estar en `CONTACT_SMTP_ALLOWED_HOSTS`; incluye 465/TLS implícito y 587/STARTTLS. El operador puede autorizar combinaciones adicionales en `CONTACT_SMTP_PORT_SCHEMES`, por ejemplo `2525:smtp`, únicamente según el proveedor. El panel y el modo de entorno de Comunicación exigen TLS y verifican certificados. `MAIL_EHLO_DOMAIN` utiliza el dominio de `APP_URL` como respaldo. No desactivar TLS o certificados para resolver errores. Nombre y dirección del remitente deben estar autorizados por el proveedor. Configurar SPF, DKIM y DMARC en DNS con los valores del proveedor. Probar desde el panel con un destinatario autorizado y comprobar recepción real; la prueba automatizada no verifica entrega a bandeja de entrada. No usar el mailer `log` o failover a logs con mensajes reales o recuperación de contraseña en producción.

El contacto se guarda antes del envío. Una notificación fallida conserva el mensaje y el visitante solo recibe confirmación del guardado. La notificación se intenta una sola vez: no hay reintentos automáticos ni cola obligatoria. Decidir la política de retención y eliminación autorizada de consultas de cada cliente. No activar workers ni cron adicionales: esta versión envía contacto y convierte imágenes de forma síncrona. Si un cliente introduce jobs o tareas programadas, configurar y probar sus workers/scheduler explícitamente.

En el panel: identidad, contacto, zona horaria, SMTP, legales publicados y texto de cookies. Activar indexación SEO únicamente en el dominio de producción. Revisar canonical, `/robots.txt` y `/sitemap.xml`; registrar el sitemap y el token de Search Console. GA4 acepta solo un ID válido y se carga después del consentimiento estadístico. Rechazar y cambiar preferencias deben seguir disponibles. Cambiar configuración de consentimiento o contenido publicado de privacidad invalida decisiones anteriores. El responsable del cliente debe revisar textos y requisitos aplicables.

## Comunicación en distintos hostings

La aplicación requiere SMTP saliente con TLS, PHP 8.4/OpenSSL y CA actualizadas, MySQL persistente, HTTPS y directorios escribibles. No necesita Redis, workers ni cron para contacto. La compatibilidad depende de esas capacidades: un hosting que bloquea SMTP necesita intervención del operador o una integración separada autorizada.

| Entorno | Preparación del operador | Comprobación antes de aceptar |
| --- | --- | --- |
| Compartido | Confirmar PHP/extensiones, raíz `public/`, escritura, enlace/servicio de archivos y puertos SMTP permitidos. Solicitar al proveedor la configuración exacta. | Formulario público y sesión del panel funcionan; conexión SMTP autorizada; datos sobreviven a una actualización. |
| VPS | Configurar web/PHP-FPM, HTTPS, permisos mínimos, CA, DNS y firewall saliente según el proveedor. | Confirmar resolución del host, certificado válido y tiempos de respuesta bajo límites de PHP/proxy. |
| Administrado | Configurar variables secretas, base persistente y almacenamiento persistente compatible. Mantener la clave entre releases y réplicas. | Todas las réplicas leen la misma base, clave y archivos; consultas y configuración sobreviven a reemplazar una instancia. |

Si el servidor no tiene Node, compilar `public/build` en el entorno de construcción autorizado desde `package-lock.json`. Si tampoco dispone de Composer/SSH, preparar `vendor` desde `composer.lock` en un entorno compatible y publicar el artefacto mediante el mecanismo del proveedor. Las extensiones de producción siguen siendo necesarias. El operador debe disponer de un mecanismo seguro para ejecutar migraciones revisadas y reconstruir cachés (consola/tarea de despliegue del proveedor); no exponer endpoints públicos para Artisan ni secretos en el artefacto. Estos son procedimientos, no comandos ejecutados por esta mejora.

### Configuración y conservación

1. Conservar `APP_KEY`, `.env`, base, mensajes y archivos en cada actualización. No volver a generar la clave, ejecutar `migrate:fresh` ni seeders que sobrescriban datos. Conservar el acceso restringido a respaldos operativos del cliente.
2. Configurar `MAIL_MAILER=smtp`, host, puerto, esquema, credenciales y remitente para correo general, incluida recuperación de contraseña. Si se usa `MAIL_URL`, su configuración tiene prioridad dentro del transporte de entorno. Para Comunicación, 465 requiere `smtps`, 587 `smtp`; otros puertos del entorno son configuración explícita del operador, siempre con TLS. No usar `log`/failover a logs con datos reales.
3. Autorizar los servidores del panel con `CONTACT_SMTP_ALLOWED_HOSTS`. Para otro puerto, declarar su esquema, por ejemplo `CONTACT_SMTP_PORT_SCHEMES=2525:smtp`, y reconstruir la caché de configuración. No añadir direcciones arbitrarias desde el panel.
4. En el panel, guardar destinatarios, remitente autorizado y activación; los valores vacíos utilizan el respaldo del sitio. Validar los datos efectivos en el resumen. Un cambio de servidor con credenciales guardadas requiere sustituir ambas o eliminarlas explícitamente; guardar campos vacíos conserva las existentes.
5. Los ajustes de correo se consultan en base de datos en cada petición y no quedan congelados por `config:cache`. Los cambios de `.env` sí requieren reconstruir la caché por el operador. No borrar sesiones o rate limiting para solucionar SMTP.
6. Revisar SPF/DKIM/DMARC, autorización del remitente, límites de envío, DNS y certificados con el proveedor. El panel no modifica esta infraestructura. El timeout de socket es de diez segundos; medir también la duración total de la petición y los límites del hosting.

### Aceptación real por instalación

Solo realizar envíos a destinatarios controlados y con autorización. Registrar entorno, fecha/hora, responsable y resultado; no incluir contraseñas ni contenido privado en el reporte.

1. Guardar los ajustes y abrir otra petición/sesión autorizada: comprobar valores y credenciales conservadas. Repetir después de una actualización de staging sin cambiar clave/base/archivos.
2. Enviar una prueba a un buzón controlado, revisar Recibidos/Spam y registrar recepción. «Aceptada por el servidor» no confirma entrega al buzón.
3. Enviar un contacto identificable: comprobar guardado, intento/aceptación y recepción. Responder desde el cliente habitual y verificar `Reply-To` del visitante, conservando `From` autorizado. Marcar leído no significa respondido.
4. Probar recuperación de contraseña por separado: usa la configuración general del entorno.
5. En staging, con un transporte de prueba que falle, comprobar que el visitante obtiene confirmación de guardado, la consulta permanece en la bandeja y no se reenvía automáticamente. No sabotear credenciales ni red de producción.
6. Comprobar acceso denegado a editor/inactivo, límites de prueba, ayuda y formularios a 360/390/768/1440 px, teclado, foco y consola Livewire/JavaScript.

**Estado actual:** resultados globales en [05 · Auditoría](05-AUDITORIA-FINAL.md) y detalles de transporte en [Comunicación](07-PLAN-COMUNICACION.md). El usuario descartó las verificaciones externas como tarea de la sesión; este procedimiento no las reactiva. No se han configurado ni certificado hostings compartidos, VPS o administrados reales; recepción, DNS, persistencia entre despliegues, recuperación de contraseña y revisión visual requieren el destino y las autorizaciones pendientes.

## Caché y personalización

Se cachean durante cinco minutos atributos de identidad pública, configuración SEO pública, revisión de privacidad y datos de integraciones. Los fragmentos permanecen cifrados en su almacenamiento de caché; no introducir secretos en su código. Los destacados de proyectos cachean IDs durante una hora, con claves por límite y revisión común; su lectura vuelve a comprobar publicación y relaciones actuales. Los modelos invalidan sus claves al guardar/eliminar y después de commit o rollback. SMTP, detalles de contacto, consultas recibidas y decisiones de cada visitante no se almacenan en esa caché. La limpieza de caché de aplicación requiere autorización e invalida las claves propias de ajustes, privacidad, integraciones, sitemap y revisión de destacados para cualquier límite; preserva sesiones, rate limiting y otras aplicaciones.

Los cambios masivos mediante SQL/query builder omiten eventos Eloquent: después de una importación o cambio manual, usar la acción autorizada de limpieza. No se cachean colecciones de modelos en los destacados; no reintroducir esa serialización. Laravel 13 mantiene deshabilitada la deserialización de clases por defecto. Usar datos simples para nuevas entradas de caché. No se requieren tags ni Redis.

Sustituir vistas `public/`, componentes y `errors/` por el diseño del cliente. `x-content-image` reserva dimensiones y ofrece `srcset` con archivos existentes y dimensiones persistidas, con fallback de lectura para archivos históricos locales, sin regenerar ni modificar originales; usar `priority=true` solo en imágenes principales. Para discos remotos, verificar las dimensiones persistidas y las operaciones de Storage antes de cambiar el almacenamiento; no se añadió ni verificó una infraestructura remota. Las vistas de error no consultan la base ni Vite, por lo que también funcionan durante fallos de esos servicios; requieren `APP_DEBUG=false` para ocultar diagnósticos.

Extender módulos con modelos, migraciones, Policies y Resources convencionales. Conservar `x-seo`, `x-cookie-consent`, `x-analytics` y `x-contact-form` donde corresponda; no duplicar sus scripts ni cargar Analytics por otra vía.

## Verificación previa a publicar

En una copia de desarrollo/CI con dependencias de prueba y sin datos reales, ejecutar:

```sh
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run build
git diff --check
```

La auditoría de esquema MySQL es de solo lectura y usa la conexión MySQL del entorno con una base explícita. No ejecuta migraciones ni usa los traits que borran tablas:

```sh
MYSQL_AUDIT_DATABASE=empresa php artisan test --compact tests/Feature/MySqlReadOnlyTest.php
```

Sin esa variable, la prueba se omite y la suite permanece aislada en SQLite. Verifica índices, referencias de archivos y columnas de credenciales; no sustituye una suite CRUD completa contra MySQL de pruebas.

Para CI, usar `vendor/bin/pint --test` en lugar del comando que modifica archivos. Instalar exclusivamente desde lockfiles en CI autorizado. No se ha añadido despliegue automático ni infraestructura. Antes de una release, repetir la instalación en un clon limpio y ejecutar la suite con MySQL en una base vacía de pruebas, además de SQLite; esta guía no equivale a haber realizado esa comprobación.

Verificar manualmente login/logout, recuperación de contraseña, URLs denegadas para editor, CRUD, archivos inválidos y referenciados, contacto con fallo SMTP, política publicada y consentimiento antes de GA4. Comprobar páginas 403/404/500, panel y frontend del cliente en móvil y con teclado. Mantener indexación desactivada en staging. La versión y publicación de la release corresponden al responsable, tras la auditoría integral.


## Biblioteca multimedia: integración de la fase 3

El campo `App\Filament\Forms\Components\MediaPicker` abre el navegador compartido sin guardar el formulario padre. `ContentFields::image()` lo integra en contenido y SEO; los consumidores guardan `media.id`, no `media_assets.id`. Elegir una imagen existente no copia archivos. Una carga realizada desde el selector queda en la biblioteca aunque se cancele la selección o el formulario padre; puede revisarse mediante el filtro «Sin uso».

`imageContext('logo')` permite JPEG/PNG/WebP hasta 2 MB y `imageContext('favicon')` permite PNG hasta 256 KB. Los originales se validan al confirmar y al guardar, incluyendo imágenes existentes. El contexto por defecto conserva 5 MB por archivo y los límites de fase 1. Todos los contextos utilizan `MediaUploader` y su hash de deduplicación. Los logos y el favicon se sirven desde el original; no se transforma un favicon PNG en WebP.

La migración de identidad es aditiva y debe aplicarse únicamente tras autorización y revisión del entorno. La migración se aplicó con autorización expresa a la instalación local `laravel_corporate_starter` el 9 de octubre de 2026, usando además `--database=mysql`. Se verificaron las tres columnas nullable y sus FKs `RESTRICT` mediante consultas de solo lectura. Para otras instalaciones, revisar la conexión y obtener la autorización correspondiente antes de ejecutar:

```sh
php artisan migrate --path=database/migrations/2026_10_09_224627_add_media_references_to_general_settings_table.php --no-interaction
```

Añade `general_settings.logo_media_id`, `logo_dark_media_id` y `favicon_media_id`, todas nullable y restrictivas hacia `media.id`. Mantiene los paths anteriores y limpia únicamente la caché pública de identidad. Antes de aplicarla, las lecturas públicas y el guardado de otras preferencias funcionan; los selectores de identidad nueva permanecen desactivados. No ejecutar rollback sobre nuevas selecciones sin revisar la restauración de referencias. No mover, importar ni eliminar archivos legados automáticamente.

Para extender un módulo, usar `MediaPicker::make('image_id')` o `ContentFields::image()` en su formulario, declarar la FK nullable/restrictiva a `media`, la relación BelongsTo y su atributo permitido en el modelo, y añadir su referencia a `MediaAsset::referenceQueries()` y a las etiquetas de uso. Probar guardado, recuperación y borrado bloqueado. El campo compartido aplica la Policy multimedia; el módulo conserva su propia Policy para autorizar el guardado. No crear otro FileUpload o sistema de almacenamiento para ese campo.

Consultar el informe de fase 3 en [06-PLAN-BIBLIOTECA-MULTIMEDIA.md](06-PLAN-BIBLIOTECA-MULTIMEDIA.md) para resultados y pendientes. La revisión visual, la consola JavaScript y las carreras MySQL requieren un entorno disponible; no quedan certificadas por la suite PHP o la compilación.


## Blog e integraciones en esta versión

El blog incluye transacciones, autorización actualizada, aviso de salida y detección de formularios obsoletos. El cambio de slug público desde sus páginas crea una redirección 301 en la misma operación; cambios por SQL u otros consumidores no generan redirecciones automáticamente. Tras actualizar código, recargar formularios ya abiertos. No hay tablas nuevas para las fases 1 y 2 del blog. Ver [10 · Blog](10-PLAN-MEJORA-BLOG.md).

Las integraciones de búsqueda incluyen GA4, GTM y fragmentos personalizados autorizados. No insertar dos veces el mismo proveedor desde el layout y el panel. Mantener `x-site-integrations` en las posiciones previstas, consentimiento por finalidad y permisos de gestión. La clasificación de un fragmento debe corresponder a lo que realmente ejecuta el proveedor; los fragmentos pueden ejecutar JavaScript y requieren revisión de confianza. No son una forma de almacenar claves privadas.

El esquema local consultado el 14 de octubre registra las migraciones presentes de asistente, respuestas, portafolio, permisos e integraciones. En otro destino, revisar su propio estado y migrar de acuerdo con el proceso del operador. No copiar como autorización ni como comprobación actual los resultados históricos de otra instalación.
