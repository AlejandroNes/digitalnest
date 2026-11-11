# Biblioteca multimedia: estado, integración e historial

Actualizado: **14 de octubre de 2026**. Las tres fases de ingestión, navegador visual e integración del selector están implementadas. Las verificaciones de MySQL/concurrencia y la revisión visual integral siguen pendientes. Resultados globales: [05 · Auditoría](05-AUDITORIA-FINAL.md).

## Guía vigente

### Componentes y contrato de datos

- `MediaAsset` es el registro editorial con nombre y ALT; cada uno posee un `Media` en la colección `images`. Los consumidores guardan **`media.id`**, no `media_assets.id`.
- `MediaUploader` centraliza nuevas cargas y lotes. Conserva el nombre original como metadato, normaliza el archivo y deduplica nuevas subidas por `content_hash`; no importa, mueve ni renombra automáticamente archivos históricos.
- `MediaLibraryBrowser` ofrece navegación visual, búsqueda, filtros, selección y carga integrada. `MediaPicker` lo reutiliza dentro de otros formularios sin guardar el registro padre.
- Una carga confirmada se conserva en la biblioteca aunque se cancele el selector o el formulario padre. Una selección existente no copia archivos.
- `MediaAsset::referenceQueries()` y sus etiquetas registran los consumidores, incluidos identidad, contenido, SEO, proyectos/galería y foto del asistente. Policy, comprobaciones y FKs protegen el borrado de medios usados.

### Límites actuales

| Concepto | Valor configurado |
| --- | --- |
| Formatos | JPEG, PNG y WebP; el favicon exige PNG |
| Archivo general | 5 MB |
| Dimensión / píxeles | Máximo 6000 px por lado y 8 millones de píxeles |
| Lote | 10 archivos y 25 MB totales |
| Ingestión | 30 por minuto según configuración |
| Logo / foto de asistente | 2 MB |
| Favicon | 256 KB |

Los límites de PHP-FPM y del servidor web deben permitir los tamaños configurados; la configuración CLI no certifica el flujo web. No se reconfiguraron servicios para actualizar esta documentación.

### Conversiones y renderizado

Los originales se conservan. `optimized` y `thumb` son WebP síncronos, con proporción conservada; la foto del asistente añade conversión específica de 200 × 200. No se requiere worker para estos flujos. La foto puede generar su conversión al simular sin guardar ajustes.

`MediaAsset::mediaUrl()` vuelve al original si falta el derivado y devuelve `null` si tampoco existe el original. `x-content-image` usa dimensiones, ALT, `srcset` y lazy loading donde corresponde; reservar prioridad para la imagen principal. Logos y favicon usan el original. El texto ALT se escapa, y la selección se vuelve a validar al guardar el consumidor.

### Integración de un módulo

```php
use App\Filament\Forms\Components\MediaPicker;

MediaPicker::make('image_id')->imageContext('blog');
```

También está disponible `ContentFields::image()`. Declarar FK nullable/restrictiva hacia `media`, relación `BelongsTo` y atributo permitido; añadir la referencia y etiqueta a `MediaAsset`. Verificar permisos propios del módulo y de biblioteca, persistencia, sustitución y borrado bloqueado. No crear un FileUpload paralelo para el mismo campo.

En identidad se utilizan `logo_media_id`, `logo_dark_media_id` y `favicon_media_id`, con compatibilidad para paths anteriores. Retirar una selección guardada limpia su fallback de identidad, conservando el archivo físico.

### Esquema y pruebas

Las migraciones de hash e identidad están registradas en la base local consultada el 14 de octubre. Esto no autoriza migrarlas en otros entornos ni certifica referencias históricas de clientes. Revisar `migrate:status` y seguir [04 · Despliegue](04-DESPLIEGUE.md).

Pruebas de referencia: `MediaUploaderTest`, `MediaPickerTest`, `MediaLibraryInterfaceTest`, `MediaSelectionExperienceTest`, `MediaModuleReliabilityTest`, `PublicImagesTest`, `GeneralSettingsTest` y las pruebas de contenido/identidad. El blog también verifica archivos desaparecidos después de abrir el formulario.

Pendientes: revisión integral de tamaños, claro/oscuro, teclado, foco y consola; límites web efectivos; CRUD y carreras referencia/borrado en MySQL de pruebas independiente. El usuario descartó la verificación externa como tarea de la sesión; no se ejecuta por el solo hecho de figurar aquí.

## Historial de diagnóstico y entregas

El contenido siguiente describe lo observado o propuesto en cada entrega anterior. Sus frases «no iniciado», límites CLI, conteos de pruebas, datos de ejemplo y autorizaciones pertenecen a ese momento. Para el estado actual prevalecen la guía anterior y el informe [05](05-AUDITORIA-FINAL.md); el historial no concede nuevas autorizaciones.

<details>
<summary>Diagnóstico inicial, plan de tres fases y evidencia histórica del 9 de octubre</summary>

## Alcance y método

Convertir la biblioteca existente en un gestor visual de imágenes y en el punto común de selección y subida del panel. Inspiración funcional: WordPress; implementación: Laravel, Filament y Livewire ya instalados. Conservar contenido, permisos, relaciones, URLs y archivos existentes.

Se leyeron `AGENTS.md`, `README.md` y todos los documentos actuales de `docs/` (01 a 05). Se revisaron modelos, migraciones, disco público, configuración Spatie/Livewire, Resource y páginas multimedia, campos compartidos, ajustes generales, SEO, renderizado público, Policies, roles y pruebas. Se consultaron Laravel Boost, el esquema y registros locales mediante consultas de solo lectura y la documentación/código de las versiones instaladas. No existe `.ai/rules` en este repositorio.

El árbol de Git contiene trabajo previo sin confirmar. La auditoría inicial añadió únicamente este documento, sin instalar paquetes, modificar código ni ejecutar migraciones. Los cambios posteriores autorizados se describen en los informes de cada fase. No se realizaron commits ni pushes. No se utiliza Chrome, conforme a la indicación del usuario; la revisión visual y los errores de JavaScript en navegador siguen pendientes.

### Entorno confirmado

| Componente | Versión o configuración verificada |
| --- | --- |
| Laravel / PHP | Laravel 13.35.0; PHP 8.4 |
| Filament / Livewire | Filament 5.10.1; Livewire 4.4.7 |
| Integración multimedia | `filament/spatie-laravel-media-library-plugin` 5.10.1 |
| Spatie Media Library / Image | 11.23.9 / 3.9.7 |
| Image Optimizer | 1.10.0 instalado; las conversiones actuales desactivan optimizadores externos |
| Imágenes | GD disponible, WebP disponible, Imagick no disponible en PHP CLI |
| Base de datos local | MySQL 9.7.1; el objetivo documentado de despliegue es MySQL 8.4 |
| Frontend | Tailwind 4.3.3 y Vite; tema propio del panel con Instrument Sans y `#6C5CE7` |
| Disco multimedia | `public`, local, raíz `storage/app/public`; enlace `public/storage` presente |
| Procesamiento | Conversiones `nonQueued()`: síncronas, sin worker obligatorio |
| Límites PHP CLI | `upload_max_filesize=2M`, `post_max_size=8M`, `memory_limit=128M` |

Los límites CLI no demuestran cuáles aplica PHP-FPM u otro proceso web. Se deberán comprobar en el servidor que recibe las subidas. No se ha cambiado su configuración.

## Diagnóstico del módulo actual

### Arquitectura y comportamiento comprobado

- `app/Models/MediaAsset.php` es el registro editorial: título en `name` y ALT en `alt_text`. Implementa `HasMedia` mediante `InteractsWithMedia`.
- Cada registro posee una colección `images`, `singleFile()`, que acepta JPEG, PNG y WebP en el disco público. **Una imagen es un MediaAsset con un Media; no una galería de archivos dentro del mismo registro.**
- La tabla `media` de Spatie conserva archivo, MIME, bytes, disco, conversiones y propiedades JSON. La relación propietario es polimórfica y no tiene una FK hacia `media_assets`.
- `MediaAssetResource` y sus páginas `ListMediaAssets`, `CreateMediaAsset` y `EditMediaAsset` están registrados por descubrimiento del panel. Las tres rutas `/admin/media-assets`, `/create` y `/{record}/edit` existen.
- El formulario exige título, ALT y un archivo en creación. La imagen se oculta en edición: cambiar metadatos no reemplaza el archivo ni rompe referencias.
- Hay búsqueda por título y ALT, ordenación y paginación nativas. El listado usa una tabla con miniatura; en móvil utiliza `stackedOnMobile()`. No tiene grid multimedia ni filtros específicos.
- Se generan `optimized` (máximo 1600 × 1600) y `thumb` (máximo 320 × 320), ambos WebP, calidad 80 y proporciones conservadas mediante `Fit::Max`. No hay recorte destructivo ni worker necesario.
- `MediaAsset::mediaUrl()` comprueba la existencia del derivado, vuelve al original si falta y devuelve `null` si también falta este. La columna administrativa de Spatie no utiliza directamente este helper.
- `MediaAsset::isReferenced()` comprueba destacadas y SEO de artículos/servicios, imagen de testimonios y OG global. La Policy y el método `delete()` bloquean el borrado de medios utilizados. Las FKs a `media.id`, con `restrictOnDelete()`, añaden protección de base de datos.
- `MediaAssetPolicy` controla ver, crear, actualizar y eliminar. Administradores activos tienen las cuatro operaciones; editores activos pueden ver, subir y editar metadatos, pero no eliminar. Invitados e inactivos no acceden al panel. No hace falta instalar otro gestor de permisos.
- Las páginas Resource son componentes Livewire de Filament. No existe actualmente un navegador multimedia Livewire reutilizable ni un servicio propio de ingestión compartido.

### Uso real de imágenes en otros módulos

| Consumidor | Persistencia y selección actuales | Qué conservar y qué mejorar |
| --- | --- | --- |
| Artículos | `posts.image_id → media.id`; `ContentFields::image()` | Conservar FK e ID; sustituir Select textual por selector visual |
| Servicios | `services.image_id → media.id`; mismo campo compartido | Misma integración sin copiar archivos |
| Testimonios | `testimonials.image_id → media.id`; mismo campo compartido | Miniatura, selección visual y ALT compartido |
| SEO de artículos/servicios | `seo_image_id → media.id`; `SeoFields::section()` usa `ContentFields::image()` | Conservar precedencia SEO y restricciones de borrado |
| SEO global | `search_settings.og_image_id → media.id`; mismo campo compartido | Abrir la misma biblioteca y subir desde el selector |
| Logo, logo oscuro y favicon | Rutas string en `general_settings`; FileUpload independiente en `GeneralSettings::imageField()` | Centralizar las nuevas selecciones/subidas, manteniendo compatibilidad con rutas antiguas |
| Frontend de artículos/servicios | `HasContentImage`, `ContentImage` y vistas públicas | Conservar dimensiones, ALT escapado, lazy loading y prioridad de imagen principal |
| SEO público | Servicio `Seo` y componente `x-seo` | Conservar fallback: imagen SEO → destacada → OG global |
| Correos | `resources/views/mail/layout.blade.php` usa `GeneralSetting::imageUrl('logo')` | La adaptación de identidad debe mantener esta interfaz |
| Editor enriquecido | `fileAttachments(false)`; sanitizador sin `<img>` permitido | Conservarlo. No introducir almacenamiento ni imágenes embebidas por una vía paralela |
| Categorías, FAQ y legales | No tienen campos de imagen propios | No añadir campos especulativos; futuros campos podrán reutilizar el componente |

`ContentFields::image()` ya evita duplicación al guardar un ID de `media`, limita la búsqueda a 50 resultados y restringe propietario/colección en validación. Sin embargo, no ofrece miniaturas, vista previa, filtros ni subida dentro del formulario. Tampoco autoriza explícitamente la biblioteca en ese campo: hoy los roles que editan contenido ya pueden verla, pero un componente reutilizable debe comprobarlo por sí mismo.

### Evidencia local y pruebas

La base local contiene **1 MediaAsset y 1 Media**, con original PNG de 1000 × 1000, 47.379 bytes y ambos derivados existentes. No hay referencias de imagen en artículos, servicios, testimonios, OG global ni rutas de identidad guardadas. Esto comprueba el estado de esta instalación, no el de otros clientes.

El archivo se almacena en `1/<nombre-aleatorio>.png`. `media.name` conserva un nombre base legible, pero `custom_properties` no contiene el nombre original completo. Por tanto, no es correcto afirmar que se perdió toda referencia al nombre: sí falta conservarlo de forma completa e inequívoca.

Se ejecutó en esta auditoría:

```sh
php artisan test --compact --filter='MediaLibraryTest|PublicImagesTest|SearchAdministrationTest|SearchVisibilityTest|GeneralSettingsTest|PanelNavigationTest'
php artisan route:list --path=admin/media-assets
```

Resultado: **68 pruebas aprobadas, 567 assertions**. Las pruebas usan SQLite en memoria y Storage fake cuando corresponde; no vacían la base local. Cubren carga individual, conversiones WebP, búsqueda, ALT, reutilización, referencias, borrado, archivos inválidos, IDs inexistentes, rutas falsificadas, permisos, renderizado responsive y precedencia SEO. No equivalen a comprobar una carga real de 5 MB, un grid visual, concurrencia MySQL ni un selector modal todavía inexistente. No se volvió a ejecutar el build ni la suite completa porque esta entrega modifica exclusivamente documentación.

### Hallazgos y prioridad

| Prioridad | Hallazgo real o limitación | Consecuencia / respuesta recomendada |
| --- | --- | --- |
| Alta | Logos/favicon usan subida independiente | La biblioteca no es todavía el punto común de imágenes de todo el panel |
| Alta | No hay selector visual con carga integrada | El usuario debe salir del formulario para subir y después buscar el archivo por texto |
| Alta | Nombres de almacenamiento aleatorios y nombre original completo no registrado | Incorporar normalización segura y metadato original inmutable para nuevas cargas |
| Alta | UI permite 5 MB; PHP CLI limita a 2 MB | Confirmar límites web reales antes de prometer 5 MB; cualquier cambio de infraestructura corresponde al usuario |
| Alta | Conversiones síncronas; hasta 6000 × 6000, sin límite total de píxeles | 36 millones de píxeles pueden superar 128 MB al decodificar; limitar coste y procesar lotes secuencialmente |
| Media | No hay carga múltiple ni resultado por archivo | Añadir lotes acotados con un registro por imagen; no quitar `singleFile()` |
| Media | No hay deduplicación de nuevas subidas por contenido | Seleccionar no duplica, pero subir otra vez los mismos bytes crea otro medio |
| Media | Tabla, sin grid, detalle ni filtros | Diseñar tarjetas cuadradas y un panel de información accesible |
| Media | Referencias conocidas codificadas en `isReferenced()` | Al añadir identidad y módulos futuros, extender explícitamente la lista y las FKs |
| Media | DB y filesystem no comparten una transacción | Faltan pruebas de fallo de disco/conversión y resultados parciales; no prometer rollback físico automático |
| Media | Dimensiones leídas del disco al renderizar; metadatos no persistidos | Cachear dimensiones en propiedades de Media para nuevas cargas y compatibilidad con anteriores |
| Media | Miniatura administrativa depende de flags Spatie | Añadir fallback explícito y estado «Archivo no disponible» ante originales/derivados ausentes |
| Baja | `media_assets` solo tiene índice primario; búsqueda por contenido de texto | Medir con una colección representativa; no añadir índices B-tree que no resuelven `LIKE '%texto%'` |
| Baja | SEO no expone ALT/dimensiones de OG | Añadir los metadatos cuando sean conocidos, sin inventarlos ni cambiar el fallback |

El límite Spatie general es 10 MB y Livewire temporal usa por defecto 12 MB; el formulario de biblioteca restringe a 5 MB. Son capas distintas. El plan añadirá una validación compartida para las vías multimedia, sin reducir indiscriminadamente otros tipos de subida del proyecto.

## Arquitectura recomendada y decisiones técnicas

### Un único catálogo y un único almacenamiento

Conservar `MediaAsset`, `media`, colección `images`, disco `public`, el modelo Media de Spatie y `DefaultPathGenerator`. Mantener `image_id`, `seo_image_id` y `og_image_id` apuntando a **media.id**, nunca intercambiarlos con `media_assets.id` o UUIDs del uploader.

Las rutas nativas `<media.id>/<archivo>` ya aíslan los archivos. Dos imágenes distintas llamadas `foto.jpg` pueden almacenarse como `31/foto.jpg` y `32/foto.jpg` sin colisión, sin un contador global ni sufijos aleatorios. No cambiar el generador de rutas o el eliminador de archivos: ambos forman parte de la compatibilidad y seguridad de Spatie.

La solución utilizará tres piezas con responsabilidades concretas:

1. **`app/Services/MediaUploader.php`:** ingestión compartida para alta individual y múltiple; validación de contenido/nombre, metadatos, hash y uso de `FileAdder` nativo. No escribe un segundo repositorio de archivos.
2. **`app/Filament/Support/MediaLibraryTable.php` y vistas de tarjeta/detalle:** definición común del grid, consultas, búsqueda y filtros para la biblioteca y el selector. El Resource existente conserva rutas y autorización.
3. **`app/Filament/Forms/Components/MediaPicker.php` y `app/Livewire/MediaLibraryBrowser.php`:** campo de imagen con acción modal Filament y navegador compartido. El campo conserva estado escalar `media.id`; la ventana ofrece biblioteca, subida y detalle. El navegador usa Tables/Schemas nativos de Filament, no una API o SPA nueva.

Los nombres de archivos nuevos son los propuestos para la futura implementación. Se generarán con Artisan cuando proceda y se comprobarán las firmas de las APIs antes de escribir código. Las carpetas nuevas convencionales de componentes quedan incluidas en la propuesta, sujetas a aprobación del plan.

`ContentFields::image()` conservará su interfaz de fábrica, pero devolverá el campo compartido. `SeoFields` y los Resources consumirán esa misma pieza. El envío de selección no aceptará rutas de estado arbitrarias desde el navegador: cada instancia tiene una clave estable, devuelve un ID y el campo padre verifica contexto, Policy y valor. Debe funcionar en formularios con varias imágenes y campos anidados sin actualizar otro campo por accidente.

Filament permite grids con `contentGrid()` y layouts de columnas; se aprovecharán búsqueda, filtros, paginación y Actions existentes. Esta decisión se apoya en la [documentación de layouts de Filament 5](https://filamentphp.com/docs/5.x/tables/layout).

### Nombres, metadatos y deduplicación

- `MediaAsset.name` sigue siendo el título editable; `alt_text`, el ALT compartido y obligatorio. Editar cualquiera de ellos no cambia archivos, nombres físicos ni URLs.
- Guardar en `media.custom_properties` el nombre original completo (solo nombre, sin ruta), dimensiones originales y dimensiones/MIME de las conversiones conocidas. El nombre original es una referencia administrativa escapada; no una ruta, URL ni identificador de almacenamiento.
- Para nuevas cargas: extraer el nombre base, transliterar, convertir a minúsculas, normalizar espacios/signos a guiones y limitar longitud. Extensión determinada exclusivamente por el MIME decodificado: `jpg`, `png` o `webp`. Ejemplo: `Foto Equipo Ávila 2026.JPG` → `foto-equipo-avila-2026.jpg`.
- Si el nombre base queda vacío, usar `imagen`, cuya ruta por ID sigue siendo única. Rechazar nombres con segmentos ejecutables como `shell.php.jpg`, separadores de ruta, controles o traversal; no limitarse a borrar la extensión peligrosa y dar por válido el archivo.
- No habilitar `preserveFilenames()` con el nombre recibido sin validar. Filament documenta riesgos del uso directo de nombres del cliente en discos públicos: [seguridad de nombres en FileUpload](https://filamentphp.com/docs/5.x/forms/file-upload#security-implications-of-controlling-file-names).
- Spatie permite asignar nombre físico y propiedades mediante sus APIs existentes; se mantienen sus comprobaciones de extensiones. Referencias: [Adding files](https://spatie.be/docs/laravel-medialibrary/v11/api/adding-files) y [custom properties](https://spatie.be/docs/laravel-medialibrary/v11/advanced-usage/using-custom-properties).
- Añadir `media_assets.content_hash`, nullable, SHA-256 de los bytes originales, con índice único. Los valores antiguos empiezan en `null`; dos originales distintos con el mismo nombre se aceptan. Dos cargas simultáneas con los mismos bytes convergen al mismo activo mediante la restricción única y recuperación controlada del conflicto.
- Antes de activar deduplicación completa, completar hashes de originales existentes con un comando acotado, idempotente y con modo de diagnóstico. Duplicados históricos permanecen intactos: escoger un registro canónico para el hash y reportar los demás, sin fusionar referencias ni borrar archivos. Archivos ausentes no reciben hash ficticio.
- Al reutilizar un duplicado, conservar título, ALT y nombre original del primer registro; no sobreescribir metadatos de una imagen usada en otro módulo. Mostrar «Esta imagen ya existe» y permitir seleccionarla. La comparación es exacta por bytes, no reconocimiento visual ni comparación perceptual.
- La mera selección nunca ejecuta FileAdder ni vuelve a copiar un archivo. Las imágenes derivadas son conversiones, no duplicados editoriales.

### Carga, seguridad y fallos

Mantener JPEG/PNG/WebP; rechazar SVG, GIF, AVIF, PDF, vídeos y scripts en este alcance. Validar servidor: usuario/Policy, archivo decodificable, MIME, extensión/nombre, tamaño, dimensiones y presupuesto de píxeles. Límites iniciales propuestos: 5 MB por imagen, máximo 6000 por lado, **8 millones de píxeles totales**, hasta 10 archivos y 25 MB por lote. Comprobar estos límites con el consumo real de GD antes de cerrar la fase; nunca ampliar memoria o timeouts automáticamente.

La reducción del máximo total de píxeles afecta únicamente cargas futuras; no invalida ni regenera imágenes existentes. Es una decisión visible del plan que el usuario debe aprobar. Con 128 MB no se ofrece una promesa de procesar 6000 × 6000.

Usar FileUpload de Filament con temporales Livewire (`storeFiles(false)`) para alimentar el servicio común. Las páginas antiguas de alta también delegarán en este servicio y no ejecutarán además el guardado automático del campo Spatie: una sola operación de almacenamiento por archivo. El componente del lote recoge título sugerido desde el original y ALT escrito por el usuario antes del guardado; no inventa ALT a partir del nombre del archivo.

Permitir elegir/arrastrar uno o varios archivos, mostrar progreso y errores individuales, y desactivar doble envío. Procesar conversiones por archivo, secuencialmente; el transporte tendrá concurrencia limitada. Validar límites de lote en servidor. Mantener el throttle temporal de Livewire y añadir limitación por usuario en la operación costosa de ingestión; invitados e inactivos no pueden usarla. En componentes propios con Schemas, aplicar `RestrictsFileUploadsToSchemaComponents`, conforme a las APIs instaladas.

Cada archivo tiene resultado independiente: creado, existente o fallido. No revertir las imágenes correctas porque otra falle, y no declarar éxito global ante un resultado parcial. El servicio debe compensar registros/archivos nuevos que deja una carga fallida, exclusivamente en el directorio que creó. No borrar el temporal del usuario antes de verificar el resultado necesario para un reintento.

La transacción SQL **no revierte un borrado físico**. Mantener los bloqueos por referencia y las FKs, comprobar permisos/referencias de nuevo al ejecutar y probar la carrera selección/borrado en MySQL. La eliminación se realiza individualmente mediante Spatie, sin un rollback de lote que resucite registros cuyos archivos ya fueron eliminados. Un fallo físico debe informar limpieza incompleta y registrar contexto técnico sin secretos; los archivos huérfanos requieren revisión explícita, nunca limpieza masiva automática.

No ofrecer reemplazo del archivo de un activo, importación por URL, carpetas compartidas, edición destructiva, eliminación masiva ni un sistema de papelera nuevo. El servidor de despliegue debe impedir ejecución de scripts en `/storage`; esto se verifica/documenta, no se reconfigura desde el panel. Los originales públicos pueden conservar EXIF del archivo subido: las conversiones web deben verificarse sin metadatos sensibles; no afirmar que preservar originales elimina esos datos.

### Diseño y rendimiento

- Tarjetas de **proporción 1:1**, alineadas por CSS Grid; título en una banda de altura uniforme, miniatura contenida y estado de selección visible. El grid no depende de la proporción original de la imagen ni de la longitud del título.
- No recortar el archivo para obtener cuadrados: preservar conversiones actuales y usar `object-fit: contain` en la biblioteca. La vista previa muestra la imagen completa, especialmente logos.
- Objetivo: dos columnas desde 360 px; tres/cuatro/cinco cuando el ancho disponible lo permita. Adaptar el modal por su espacio disponible, no asumir que el viewport completo es el ancho del selector. Una columna solo en espacios especialmente estrechos.
- Tema del panel: violeta `#6C5CE7`, botones principales con texto blanco, Instrument Sans, bordes discretos y modo oscuro existente. No agregar fuentes, gráficos, iconos de otra librería ni un segundo tema.
- Barra compacta: búsqueda, tipo JPEG/PNG/WebP, fecha de carga, en uso/sin uso, orden más reciente/título y botón «Subir imágenes». Paginación de 24/48; resultados vacíos con acción clara y opción de limpiar filtros.
- Detalle: imagen, título, ALT, nombre original, nombre físico, formato, bytes, dimensiones, fecha, URL y usos conocidos. Solo mostrar un enlace cuando el archivo exista; no exponer rutas absolutas del servidor.
- Modal selector: biblioteca/subida, detalle al lado en escritorio y apilado en móvil; pie con «Seleccionar imagen» y «Cancelar». Selección persistente al buscar/paginar, sin asignación hasta confirmar. Cancelar no altera el campo padre.
- Cargar solo los registros de la página, eager loading de `media`, miniaturas con `loading=lazy`, dimensiones/aspect ratio reservados. No consultar `isReferenced()` cinco veces por tarjeta: resolver uso mediante consultas agregadas/exists o subconsultas para las referencias conocidas.
- Nunca decodificar cada original al listar. Persistir dimensiones al subir; los antiguos usan fallback controlado y enriquecimiento por lotes. En peticiones públicas, recuperar metadatos guardados y conservar el fallback actual para registros sin ellos.
- Teclado, nombres accesibles, foco visible y restituido al campo origen; Escape cierra el modal; controles de 44 px; selección señalada por texto/estado además de color; errores asociados a campos y progreso anunciado. Evitar botones interactivos anidados dentro de una tarjeta clicable.
- No cargar toda la biblioteca en PHP o navegador, no descargar originales de alta resolución para miniaturas y no generar conversiones durante una lectura.

### Compatibilidad de identidad

En ajustes generales se añadirán FKs nullable `logo_media_id`, `logo_dark_media_id` y `favicon_media_id` hacia `media.id`, todas con `restrictOnDelete()`. Conservar columnas string antiguas sin borrarlas ni cambiarlas. `GeneralSetting::imageUrl()` resolverá primero la relación nueva y, si no se ha migrado esa selección, la ruta anterior. Actualizar sus atributos de caché e invalidación sin guardar modelos serializados.

Los nuevos campos utilizan MediaPicker. La selección debe validar el contexto también en servidor: logos conservan límite de 2 MB; favicon conserva PNG y 256 KB y debe servirse con el original PNG, no con la conversión WebP. Para logos se evita ampliar innecesariamente el alcance a transformaciones de marca.

No importar ni mover automáticamente rutas legadas: siguen funcionando y no se replican en el catálogo por una copia silenciosa. Una futura adopción física de archivos legados requeriría inventario y autorización explícita, especialmente si sus URLs se usan externamente. Esta compatibilidad no mantiene uploaders independientes: todas las nuevas subidas de identidad pasan por la biblioteca. Retirar una selección solo quita su referencia, no elimina el medio ni el archivo antiguo.

## Fase 1 — Base de ingestión segura y compatibilidad del catálogo

### Objetivo y alcance

Consolidar las operaciones de carga sobre Spatie y preparar metadatos, nombres legibles y deduplicación antes de ampliar la interfaz. Conservar las rutas y el uso actual de la biblioteca durante esta fase.

### Funcionalidades

- Crear el servicio compartido de subida y validación, utilizable con un archivo o lote, y conectar la alta individual existente.
- Nombres normalizados por MIME, nombre original como referencia, título/ALT separados y dimensiones persistidas.
- Hash de contenido, resolución de duplicados y conflictos concurrentes sin crear dos originales iguales.
- Límites de bytes, píxeles, número y tamaño agregado; autorización y limitación por usuario en servidor.
- Mantener `optimized`/`thumb`, síncronas, WebP y originales intactos; comprobar fallos de conversión/disco y compensación de registros nuevos.
- Preparar diagnóstico/enriquecimiento de metadatos antiguos por lotes, sin renombrar, regenerar ni eliminar archivos.

### Componentes y archivos

- Modificar `app/Models/MediaAsset.php`, `MediaAssetResource.php`, `Pages/CreateMediaAsset.php` y, si corresponde, la configuración multimedia de la aplicación.
- Añadir `app/Services/MediaUploader.php` y una regla de validación de imagen/nombre solamente si evita duplicación real entre formularios y servicio.
- Añadir una migración para `media_assets.content_hash` nullable con índice único; no editar migraciones históricas.
- Añadir un comando bajo `app/Console/Commands/` para diagnóstico/enriquecimiento, creado con Artisan, con procesamiento acotado y modo sin escritura por defecto.
- Extender `tests/Feature/MediaLibraryTest.php`; añadir pruebas del servicio/regla cuando sean necesarias para la matriz de entradas y concurrencia.

### Modelos, relaciones e integraciones

No cambiar propietarios, colección, disco ni FKs de consumidores. Usar propiedades JSON de Media para nombre/dimensiones y una sola columna indexada para deduplicación. No trasladar ALT/título a otra tabla. La alta individual nueva seguirá produciendo el mismo tipo de MediaAsset/Media que consumen los módulos existentes.

### Riesgos y medidas de protección

- Hashes históricos y duplicados: diagnóstico primero; asignar canónico sin tocar referencias. La deduplicación completa no se declara terminada con originales legados sin inspeccionar.
- Fallos fuera de SQL: validar antes de guardar, verificar escritura y compensar únicamente archivos nuevos de esa operación. Probar que un duplicado existente nunca se borra al fallar otra carga.
- Memoria/tiempos: límites propuestos, lectura acotada y procesamiento secuencial; validar servidor web antes de habilitar el tamaño anunciado.
- Restricción única: probar en MySQL de pruebas independiente; SQLite no demuestra correctamente las carreras concurrentes.
- No cambiar credenciales, workers, PHP, servicios o paquetes para superar una prueba.

### Pruebas y verificación funcional

- Unicode, tildes, espacios, nombres iguales con contenido diferente, nombre sin base útil y nombre excesivamente largo.
- MIME/extension falsificados, doble extensión ejecutable, traversal, SVG/PHP, archivo corrupto, dimensiones/píxeles/bytes fuera de límite y ALT ausente.
- Hash duplicado individual/en lote y dos cargas concurrentes; originales históricos sin nombre completo o sin archivo.
- Fallo de escritura/conversión, resultado parcial, reintento sin duplicar y revocación de permisos entre apertura y envío.
- Verificar original y derivados, título/ALT editables sin renombrar; regresiones de consumo público y restricciones de borrado.

### Criterios de aceptación

1. Una carga válida crea exactamente un MediaAsset, un Media y sus dos derivados; devuelve el ID correcto.
2. El nombre físico es legible, seguro y su ruta única; el nombre original completo queda disponible para nuevas cargas.
3. Reenviar los mismos bytes devuelve la imagen existente sin sobrescribir metadatos; la prueba concurrente pasa en MySQL aislado.
4. Entradas inválidas no dejan registros ni archivos nuevos; un fallo parcial está informado y no destruye resultados correctos.
5. Los originales, IDs, rutas y usos anteriores continúan funcionando; diagnóstico de históricos entrega excepciones explícitas.
6. Tamaño anunciado y límites web reales están comprobados, o el bloqueo de infraestructura queda registrado antes de aprobar la fase.

### Implementación de fase 1 y verificación — 9 de octubre de 2026

El usuario autorizó implementar esta fase y, posteriormente, aplicar su migración aditiva y enriquecer los metadatos existentes. La base técnica está implementada y aplicada localmente; el cierre completo de aceptación sigue pendiente de la prueba concurrente en MySQL independiente y de verificar los límites del servidor web. El desarrollo posterior de fase 2 se registra debajo; la fase 3 sigue pendiente.

**Cambios realizados:**

- `app/Services/MediaUploader.php`: única entrada de ingestión individual/lote, autorización actualizada desde BD, límite por usuario, validación previa, decodificación real, SHA-256, nombres legibles y transacción por archivo con compensación de nuevos archivos ante fallos. Devuelve el registro existente para bytes idénticos sin sobrescribir título/ALT. Un original ausente produce un error explícito.
- `app/Rules/MediaUploadImage.php`: validación compartida de contenido, extensión, nombres peligrosos, bytes, dimensiones y píxeles. Límites centralizados en `config/media-library.php`: 5 MB, 6000 px por lado, 8 MP totales; 10 archivos/25 MB por lote y 30 intentos por usuario/minuto.
- `MediaAssetResource.php` y `Pages/CreateMediaAsset.php`: alta individual con FileUpload nativo sin almacenamiento independiente, conectada al servicio; errores del servicio asociados al campo Filament. El formulario de edición y los consumidores mantienen sus contratos.
- `database/migrations/2026_10_09_220730_add_content_hash_to_media_assets_table.php`: columna nullable de 64 caracteres e índice único. Aplicada en pruebas aisladas y en `laravel_corporate_starter` con autorización expresa. No requiere cambios de relaciones, disco, generador de rutas ni del modelo existente; el hash se asigna explícitamente, fuera de atributos de asignación masiva.
- `app/Console/Commands/EnrichMediaMetadata.php`: diagnóstico sin escritura por defecto; `--write` añade hash/dimensiones disponibles sin inventar nombres antiguos. Usa `--chunk`, `--limit` y `--after` para acotar y continuar ejecuciones; informa duplicados y originales ausentes. Sin hashes persistidos, la comparación de duplicados está limitada al tramo inspeccionado.
- `tests/Feature/MediaUploaderTest.php`, `EnrichMediaMetadataTest.php` y `MySqlMediaConcurrencyTest.php`: cobertura de ingestión, integración Livewire, errores, compatibilidad histórica y prueba concurrente optativa.

**Datos reales:** diagnóstico previo: un medio, original disponible, cero duplicados. Tras la autorización se ejecutaron exclusivamente:

```sh
php artisan migrate --path=database/migrations/2026_10_09_220730_add_content_hash_to_media_assets_table.php --no-interaction
php artisan app:enrich-media-metadata --write --no-interaction
```

Se verificaron mediante Laravel Boost el índice único y el registro enriquecido; SHA-256 calculado sobre el original coincide con el hash persistido. Original PNG 1000×1000, optimized WebP 1000×1000 y thumb WebP 320×320 disponibles. IDs, nombre físico, rutas, título y ALT se conservan. El nombre original completo de la imagen histórica sigue desconocido y no se inventó.

**Resultados ejecutados:**

| Comprobación | Resultado |
| --- | --- |
| `php artisan test --compact` | 313 aprobadas, 2 omitidas, 1876 aserciones; ambas omisiones requieren configuración MySQL |
| `MYSQL_AUDIT_DATABASE=laravel_corporate_starter php artisan test --compact` | 314 aprobadas, 1 omitida, 1888 aserciones; SQLite para pruebas de escritura y MySQL únicamente para la auditoría existente de solo lectura |
| `vendor/bin/pint --dirty --format agent` | Ejecutado; formato corregido |
| `vendor/bin/pint --test` | Aprobado |
| `npm run build` | Aprobado; aviso existente de `fontaine` opcional, sin instalar paquetes |
| `git diff --check` | Aprobado |

Las pruebas incluyen colisión de nombres con bytes diferentes, reutilización de duplicados sin cambios de metadatos, Unicode, nombres peligrosos, límites de dimensiones/píxeles/lote, cabecera válida con imagen truncada, resultado parcial, permisos revocados, rate limiting, original ausente, fallo de escritura y conversión con limpieza, error visible en el campo Filament y enriquecimiento idempotente con cursor. Las pruebas anteriores de edición, consumo público y borrado referenciado siguen pasando.

**Pendientes para cerrar la aceptación:**

1. Facilitar una base MySQL de pruebas vacía e independiente cuyo nombre termine en `_test`. La prueba `MySqlMediaConcurrencyTest` exige `MYSQL_MEDIA_TEST_DATABASE`, comprueba que no sea la base del proyecto, aplica migraciones sin `migrate:fresh`, ejecuta dos procesos sincronizados y limpia exclusivamente sus datos/archivos de prueba. No se ejecutó: no hay base autorizada indicada. Comando, únicamente tras disponer de esa base:

   ```sh
   MYSQL_MEDIA_TEST_DATABASE=nombre_independiente_test php artisan test --compact tests/Feature/MySqlMediaConcurrencyTest.php
   ```

2. Verificar límites efectivos de PHP y servidor web, incluyendo uploads temporales Livewire. CLI observado: `upload_max_filesize=2M`, `post_max_size=8M`, `memory_limit=128M`; no demuestra los valores de PHP web. No se reconfiguró infraestructura. Los 5 MB por archivo/25 MB por lote son límites de la aplicación, no una garantía de capacidad web.
3. Verificación real en navegador pendiente: por instrucción del usuario no se utilizó Chrome. La integración de alta y los errores del campo se comprobaron con pruebas Livewire; esto no sustituye inspección visual ni comprobación de la consola JavaScript.

La API de lote queda disponible para la interfaz de fase 2; esta entrega no añade controles de carga múltiple, grid nuevo ni selector. No se instalaron/actualizaron dependencias y no se hicieron commits ni pushes.

## Fase 2 — Biblioteca visual completa y carga múltiple

### Objetivo y alcance

Entregar el gestor visual autónomo usando la base segura de la fase 1, con un diseño cuidado que pueda reutilizarse dentro del selector de la fase siguiente.

### Funcionalidades

- Grid de tarjetas cuadradas con miniaturas reales, diseño responsive, estados vacíos/carga/error y archivo no disponible.
- Búsqueda por título, ALT, nombre original y físico; filtros MIME, fecha y uso; ordenación y paginación.
- Vista previa y detalle del archivo; edición de título/ALT sin reemplazar originales ni nombres físicos.
- Carga individual/múltiple, arrastrar/seleccionar archivos, metadatos por imagen y resultados por archivo usando MediaUploader.
- Eliminación individual confirmada y autorizada, con mensaje claro cuando hay referencias. Mostrar usos conocidos sin habilitar módulos prohibidos al usuario.
- Mantener las rutas de alta y edición existentes; el grid podrá abrir detalle/edición mediante Actions nativas sin obligar a abandonar el listado.

### Componentes y archivos

- `MediaAssetResource.php`, `Pages/ListMediaAssets.php`, `CreateMediaAsset.php`, `EditMediaAsset.php`.
- Nueva definición compartida `app/Filament/Support/MediaLibraryTable.php` y vistas de tarjeta, detalle y preparación de subida bajo `resources/views/filament/`.
- `resources/css/filament/admin/theme.css`: estilos acotados al módulo, sin cambios al frontend público.
- `MediaAsset.php` y servicio existente para consulta de referencias/metadata si se necesita compartir esa lógica.
- `MediaLibraryTest.php` y nuevas pruebas funcionales de listado/acciones; fixtures de varios formatos y proporciones.

### Modelos, relaciones e integraciones

No requiere nuevas tablas ni cambios de FK. Consultas paginadas con eager loading y uso agregado de referencias actuales. Biblioteca autónoma y futuro navegador modal compartirán definición de tarjetas y consultas, evitando construir dos gestores diferentes.

### Riesgos y medidas de protección

- `contentGrid()` por sí solo no garantiza tarjetas cuadradas: usar una vista/layout específico y altura uniforme para título/acciones; verificar imágenes verticales, panorámicas y logos transparentes.
- Borrado/selección: reautorizar y consultar referencias al ejecutar; conservar protección de DB y del modelo. No introducir eliminación masiva ni reemplazo.
- Filtros «en uso» costosos: consultas acotadas, comprobar SQL con colección representativa; ningún chequeo de filesystem por cada filtro/tarjeta.
- Permisos de detalle: un editor puede ver la imagen pero no debe obtener enlaces efectivos a configuración crítica por la lista de usos.
- Lote largo: progreso y resultado explícitos; evitar solicitudes ilimitadas y doble envío.

### Pruebas y verificación funcional

- Búsqueda/filtros combinados, restablecimiento, orden, páginas posteriores y títulos largos; comprobar registros visibles, no solo configuración del Table.
- Guardar y recuperar metadatos; título/ALT con HTML escapado; URLs nunca derivadas de texto no confiable.
- Lotes válidos/mixtos/duplicados y límites de cantidad/bytes; cancelar/reintentar sin crear otro original.
- Archivo original/miniatura ausentes; borrar referenciado devuelve rechazo y conserva originales.
- Medir consultas con 24 y 48 tarjetas: la cantidad no debe crecer linealmente por referencia/propietario.
- Revisión visual a 360, 390, 768, 1024 y 1440 px, en modo claro/oscuro, con 30 imágenes variadas en una base de pruebas. Teclado, foco, lector de pantalla, touch y ausencia de errores Livewire/JS.

### Criterios de aceptación

1. Tarjetas 1:1, columnas perfectamente alineadas y sin desbordamiento horizontal; dos columnas utilizables desde 360 px.
2. Listado carga miniaturas y una sola página de registros; no carga originales grandes ni toda la colección.
3. Buscar/filtrar/paginar conserva una UX consistente; ningún dato mostrado depende de una consulta N+1.
4. Usuario autorizado puede subir diez imágenes dentro de los límites, editar metadatos y borrar una imagen sin usos; una referenciada no se elimina.
5. Preview muestra imagen completa y metadatos reales; faltantes se identifican sin enlaces rotos ni errores de renderizado.
6. Permisos y accesibilidad están probados; validación visual y logs del navegador se revisan realmente antes de cerrar la fase.

### Implementación de fase 2 y verificación — 9 de octubre de 2026

El usuario autorizó esta fase y confirmó posteriormente continuar sin Chrome, dejando pendiente la revisión visual. La implementación funcional está disponible y las comprobaciones automatizadas pasan. No se declara cerrada toda la aceptación visual/accesible ni la comprobación de JavaScript en navegador.

**Interfaz implementada:**

- Listado Filament en grid, con área de miniatura 1:1, imagen completa mediante `object-fit: contain`, banda de título de altura uniforme y acciones independientes de la zona clicable. Dos columnas por defecto, tres desde `md`, cuatro desde `lg` y cinco desde `2xl`; paginación de 24/48. CSS acotado al módulo, violeta del panel, modo oscuro y foco visible. La geometría y usabilidad reales en los cinco anchos previstos requieren la revisión visual pendiente.
- Búsqueda por título, ALT, nombre original y físico. Filtros de formato original JPEG/PNG/WebP, fechas desde/hasta y en uso/sin uso. Orden por fecha de carga o título, con fecha descendente por defecto y desempate nativo por clave.
- Al pulsar la tarjeta se abre la vista previa con imagen completa, título, ALT, nombres, formato, tamaño, dimensiones persistidas, fecha y usos conocidos. La URL del original solo se ofrece si existe; no se muestran rutas absolutas del servidor ni enlaces a ajustes restringidos. Las miniaturas ausentes muestran un placeholder sin descargar originales grandes para el grid; el detalle advierte cuando falta el original.
- Edición de título y ALT en modal, manteniendo rutas de edición/alta anteriores. No renombra ni sustituye archivos. Eliminación individual confirmada, reservada al permiso correspondiente; las imágenes referenciadas muestran la acción deshabilitada con explicación y se reautorizan antes de ejecutar.
- «Subir imágenes» abre FileUpload nativo individual/múltiple, con arrastre/selección, hasta diez archivos, progreso nativo de subida y aviso durante la preparación. Cada imagen tiene título editable y ALT obligatorio. La asociación entre archivos temporales y metadatos se valida en servidor. Resultados por archivo: guardada, existente o fallida, con errores explícitos; un reintento reutiliza las cargas correctas sin duplicarlas. Cancelar no crea registros de biblioteca.

**Componentes y cambios:**

| Archivo o área | Cambio |
| --- | --- |
| `app/Filament/Support/MediaLibraryTable.php` | Definición compartida del grid, consultas, filtros, acciones y datos de presentación |
| `app/Filament/Support/MediaUploadAction.php` | Acción compartida que prepara metadatos y utiliza `MediaUploader::uploadBatch()`; evita una transacción exterior para conservar resultados parciales |
| `MediaAssetResource.php`, `Pages/ListMediaAssets.php` | Conexión de tabla/acción, vista del listado y resultados de carga con propiedad Livewire bloqueada |
| `app/Models/MediaAsset.php` | Scopes `withUsage`/`whereUsed` y etiquetas de uso; detección mediante subconsultas correlacionadas en vez de consultas por tarjeta. La protección de borrado consulta las referencias actuales, incluidas las imágenes SEO |
| `resources/views/filament/media/` | Tarjeta, detalle, listado con resultados y estado de preparación |
| `resources/css/filament/admin/theme.css` | Presentación responsive del módulo, imágenes sin recorte, acciones táctiles, modo oscuro y foco |
| `tests/Feature/MediaLibraryInterfaceTest.php` | 28 casos automatizados de interfaz, autorización, archivos, referencias y rendimiento |

No se necesitan migraciones ni nuevas relaciones. Se conservan discos, nombres físicos, originales, derivados e IDs existentes. `CreateMediaAsset`, `EditMediaAsset`, `MediaUploader` y sus contratos de fase 1 siguen funcionando. No se instalaron dependencias, no se cambiaron credenciales/permisos ni se escribieron imágenes o contenido de prueba en la base real.

**Seguridad y rendimiento comprobados:**

- Revocación entre apertura del modal y guardado: la prueba detectó inicialmente autorización basada en el usuario previamente cargado. Se corrigió reconsultando al usuario y aplicando Gate/Policy antes de guardar, borrar y entregar el detalle.
- Borrado restringido por rol, referencia añadida después de abrir confirmación, originales ausentes, nombres/ALT con HTML escapado, rutas de archivo falsificadas, metadatos de otro archivo y ALT ausente.
- Lote de diez con duplicado y fallo de dimensiones, resultado parcial y reintento sin originales adicionales. Límite agregado rechazado antes de persistir; los errores se asocian al formulario del modal.
- Todas las referencias actuales detectadas: destacada y SEO de artículos/servicios, testimonios y OG global. El detalle muestra categorías de uso sin revelar enlaces de administración críticos.
- Listado paginado con `media` precargado e indicadores de uso obtenidos en la misma consulta principal. La prueba compara el renderizado de 24/48 tarjetas y limita el crecimiento de consultas; no se decodifican originales al listar ni se generan derivados durante lecturas.
- Modales y errores ejercitados mediante las APIs de pruebas nativas de Filament 5/Livewire, incluidas las respuestas parciales del modal. Las tres rutas existentes siguen registradas: listado, alta y edición.

**Verificaciones ejecutadas:**

- `php artisan test --compact tests/Feature/MediaLibraryInterfaceTest.php`: 28 aprobadas, 199 aserciones.
- `php artisan test --compact`: 341 aprobadas, 2 omitidas, 2075 aserciones. Las omisiones corresponden a la auditoría MySQL optativa y la concurrencia en una base MySQL independiente.
- `MYSQL_AUDIT_DATABASE=laravel_corporate_starter php artisan test --compact`: 342 aprobadas, 1 omitida, 2087 aserciones. Las pruebas de escritura usan SQLite aislado; MySQL se usa únicamente para la auditoría existente de solo lectura. La prueba concurrente MySQL sigue pendiente de una base independiente autorizada.
- `vendor/bin/pint --dirty --format agent` y `vendor/bin/pint --test`: aprobados.
- `npm run build`: aprobado; aviso opcional existente de `fontaine`, sin instalar paquetes.
- `git diff --check`: aprobado.

**Pendientes expresos:** revisión real a 360/390/768/1024/1440 px en claro/oscuro, teclado, foco restaurado, lector de pantalla, interacción táctil y consola Livewire/JavaScript. No se realizó porque el usuario confirmó continuar sin Chrome; la consulta de logs de Laravel Boost no encontró `storage/logs/browser.log`, lo cual no prueba ausencia de errores. La prueba con 30 imágenes variadas en navegador requiere un entorno/base de pruebas independiente. Continúan pendientes los límites efectivos del servidor web y la concurrencia MySQL de fase 1. La configuración actual de locale es `en`: las etiquetas propias del módulo están en español y los controles traducibles nativos de Filament usan el locale configurado; no se cambió la configuración general del sitio.

El selector reutilizable y la integración de identidad pertenecen a fase 3 y no se implementaron aquí.

## Fase 3 — Selector compartido, identidad, SEO y cierre integral

### Objetivo y alcance

Hacer que todos los campos de imagen existentes del panel puedan seleccionar o subir desde la misma biblioteca y verificar compatibilidad, seguridad, rendimiento y presentación de extremo a extremo.

### Funcionalidades

- Campo MediaPicker con miniatura actual, «Seleccionar imagen», selección modal, subida y opción de quitar referencia cuando el campo sea opcional.
- Navegador Livewire que reutiliza el grid, búsqueda, filtros, detalle y subida de las fases anteriores. El modal no persiste el formulario padre hasta su guardado normal.
- Confirmación explícita del ID seleccionado; selección estable al filtrar/paginar; cancelar sin alterar la selección anterior; restauración del foco.
- Integración en destacadas de artículos/servicios, testimonios, SEO individual y OG global mediante `ContentFields::image()`.
- Integración de logo, logo oscuro y favicon con FKs nuevas y fallback de rutas antiguas; restricciones de formato/tamaño por contexto en servidor.
- Ampliar detección de usos y protección del borrado a identidad. Cambios de identidad se reflejan en frontend/correos con invalidación de caché correcta.
- Renderizado público con dimensiones persistidas, srcset de variantes reales y fallback existente; SEO añade ALT/dimensiones/MIME de OG conocidos cuando proceda.
- Documentar en este plan y en la guía de despliegue la extensión de futuros campos, límites y verificaciones operativas. Sin módulos especulativos.

### Componentes y archivos

- Nuevos `app/Filament/Forms/Components/MediaPicker.php`, `app/Livewire/MediaLibraryBrowser.php` y vistas correspondientes en `resources/views/filament/` y `resources/views/livewire/`.
- `app/Filament/Support/ContentFields.php`, `SeoFields.php`, `MediaLibraryTable.php`; Resources de Posts, Services y Testimonials si requieren adaptación de tipos/estado.
- `app/Filament/Pages/SearchSettings.php`, `GeneralSettings.php` y sus vistas cuando sea necesario para integrar el campo.
- `app/Models/GeneralSetting.php`, `MediaAsset.php`, `app/View/Components/ContentImage.php`, `app/Services/Seo.php` y `resources/views/components/seo.blade.php`.
- Nueva migración aditiva para las tres FKs de identidad. Factory/fixtures correspondientes y pruebas de selector, identidad, SEO, imágenes públicas y seguridad.
- `docs/04-DESPLIEGUE.md` y este documento, tras aprobación y únicamente con instrucciones comprobadas.

### Modelos, relaciones y compatibilidad

Agregar las tres relaciones BelongsTo de identidad; conservar campos path y métodos públicos actuales. Incluir los nuevos IDs en los atributos de identidad cacheados; no cachear Media/GeneralSetting serializados. Añadir referencias a identidad en la comprobación de uso y filtros, con FK restrictiva como segunda protección.

No cambiar el esquema de imagen de posts/services/testimonials/SEO. Registros anteriores siguen guardando el mismo ID. La compatibilidad de rutas de identidad se prueba con fixtures aunque la instalación local no tenga rutas antiguas guardadas. No reescribir ni importar archivos legados silenciosamente.

### Riesgos y medidas de protección

- Estado manipulable: resolver y autorizar el MediaAsset propietario, colección, existencia y formato del ID al confirmar y también al guardar el consumidor. No aceptar un Media ajeno a `images`, una ruta o un ID de activo donde se espera ID de Media.
- Varias imágenes en un formulario: claves únicas y contrato de selección acotado; pruebas de `image_id` y `seo_image_id` simultáneos y de un campo dentro de Repeater.
- Permisos revocados: reautorizar lectura/carga/edición y la acción del módulo padre; un selector visible no concede permisos al módulo.
- Padre cancelado tras subir: la imagen nueva queda disponible en la biblioteca como imagen sin uso. No borrarla automáticamente, pues podría haberse seleccionado en otra sesión.
- Favicon y logos: validación específica aplicada también a imágenes existentes, no solo al upload. Mantener PNG original para favicon y no modificar branding por conversiones inesperadas.
- Cache: comprobar lectura tras commit/rollback y metadatos actualizados en otro request; no limpiar sesiones ni límites de subida.
- Extensión futura: todo nuevo campo de imagen debe registrar sus referencias en el catálogo de usos y añadir FK restrictiva. No crear un registro dinámico de plugins o relaciones universales por adelantado.

### Pruebas y verificación funcional

- Seleccionar una imagen en artículo, servicio, testimonio y SEO/global: conservar el mismo Media y el mismo archivo; contar registros/archivos antes y después.
- Subir desde cada contexto, seleccionar y guardar; deduplicar si los mismos bytes se suben desde otro módulo.
- Formularios create/edit, dos campos simultáneos, Repeater, limpiar selección opcional, cancelar modal, conservar datos no guardados y subir cuando el padre todavía no existe.
- Invitado/inactivo, editor intentando identidad/SEO global/borrado, Policy revocada, ID de otra colección, Media sin propietario, ID inexistente y estado falseado.
- Identidad nueva y legacy, cache, correo con logo; favicon no PNG o mayor de 256 KB rechazado incluso si ya existe en biblioteca.
- Borrado bloqueado por cualquiera de las referencias, incluidas nuevas FKs; carrera referencia/borrado en MySQL de pruebas.
- SEO conserva precedencia y robots/cookies; dimensiones, ALT escapado, srcset real, lazy/eager loading y ausencia de regeneración al consultar páginas.
- Revisión real desktop/móvil, modal dentro del panel, cambios de filtro, navegación por teclado, foco, logs recientes y rendimiento de consultas.

### Criterios de aceptación

1. Todos los campos existentes de imagen abren el mismo selector y permiten reutilizar o subir con el mismo servicio; no quedan nuevos uploaders independientes para identidad.
2. Seleccionar nunca crea/copia Media; subir bytes existentes devuelve el activo canónico. IDs y archivos previos permanecen válidos.
3. El modal funciona antes de guardar al padre, con múltiples campos y dentro de estructuras anidadas; cancelar preserva el estado anterior.
4. Identidad mantiene rutas anteriores, correo y helpers; nuevos medios referenciados no pueden borrarse. Favicon conserva su contrato PNG/256 KB.
5. Usuarios no autorizados no ejecutan acciones por RPC/URL directa; validación y Policy se aplican en servidor en cada punto de escritura.
6. Revisión visual/a11y y ejecución real sin errores Livewire/JavaScript documentadas; MySQL de pruebas verifica las nuevas FKs y concurrencia.
7. Suite completa, formato, build y diff check pasan; incidencias externas pendientes se indican y no se declara el módulo terminado mientras impidan estos criterios.

### Informe de implementación de fase 3 — 9 de octubre de 2026

**Estado:** código implementado, verificación automatizada aprobada y migración de identidad aplicada a la base real con autorización expresa del usuario el 9 de octubre de 2026. MySQL confirma las tres FKs restrictivas. No se declara la biblioteca completa: faltan revisar la interfaz en navegador y verificar CRUD/concurrencia y carreras de referencias en una base MySQL independiente de pruebas.

**Arquitectura entregada:** `MediaPicker` es un campo Filament con acciones y modal nativos. `MediaLibraryBrowser` es un componente Livewire con la misma configuración `MediaLibraryTable` y `MediaUploadAction` de fase 2. Las tarjetas representan MediaAsset; la selección devuelve el ID de su Media, conservando las FKs de los consumidores. El evento de selección solo modifica un Hidden del esquema temporal de la acción; la confirmación valida en servidor y actualiza el campo padre. Se usan claves por componente y ruta completa del campo, incluyendo Repeater. No se introduce otro almacenamiento ni dependencia.

**Cambios funcionales y archivos:**

- Nuevos `app/Filament/Forms/Components/MediaPicker.php`, `app/Livewire/MediaLibraryBrowser.php` y `app/Rules/MediaSelection.php`.
- Nuevas vistas `resources/views/filament/forms/components/media-picker.blade.php` y `resources/views/filament/media/{browser,selection-bridge}.blade.php`; estilos en `resources/css/filament/admin/theme.css`. Miniatura actual, selección, retirada opcional, grid compartido, detalle y carga con resultados por archivo. Se conserva el color principal del panel; la revisión visual sigue pendiente.
- `ContentFields::image()` integra artículos, servicios, testimonios, SEO individual y Open Graph global sin cambiar sus columnas ni IDs. Se conserva su firma para los consumidores existentes; el estado del nuevo campo se guarda directamente como FK del modelo. No necesitan uploaders propios.
- `MediaUploadAction::make($context)` aplica límites compartidos para contenido, logos y favicon, usando el servicio y deduplicación existentes. `MediaSelection` valida Media existente, propietario MediaAsset, colección, usuario vigente, Policy, original disponible, MIME real y tamaño real tanto al seleccionar/confirmar como al guardar el formulario consumidor.
- `GeneralSettings` sustituye las cargas independientes por el selector. `GeneralSetting` incorpora `logoMedia`, `logoDarkMedia`, `faviconMedia`, caché de IDs como atributos y resolución del original para branding/favicons. Los paths anteriores mantienen el fallback y no se importan ni borran archivos. Retirar una selección de identidad previamente guardada también limpia su path legado de respaldo para que no reaparezca el logo anterior; conserva el archivo físico.
- `MediaAsset` incluye identidad en sus consultas de uso y en la protección del borrado; las nuevas FKs añaden protección frente al borrado directo de Media. Mientras no exista la migración, las consultas omiten las referencias de identidad nuevas y los selectores de identidad están desactivados; el resto de configuración sigue siendo editable y el frontend conserva las rutas anteriores.
- `ContentImage` prioriza dimensiones persistidas y conserva el fallback histórico, variantes realmente existentes, lazy/eager y ausencia de regeneración. `Seo` conserva precedencia y añade ALT escapado, dimensiones conocidas y MIME de la variante efectiva. Los guardados de configuración general y SEO reconsultan el usuario antes de autorizar.
- Nuevas pruebas `tests/Feature/MediaPickerTest.php`; adaptación de `GeneralSettingsTest.php` al flujo compartido y ampliación de `CacheTransactionsTest.php`.

**Migración aplicada con autorización:** `database/migrations/2026_10_09_224627_add_media_references_to_general_settings_table.php`. Añade únicamente tres columnas nullable con FKs a `media.id`, `RESTRICT`, e invalida la caché pública de identidad. Conserva columnas antiguas, registros y archivos. `up()` y `down()` se probaron en SQLite aislado. Se ejecutó únicamente esta migración en `laravel_corporate_starter`, mediante `php artisan migrate --database=mysql --path=database/migrations/2026_10_09_224627_add_media_references_to_general_settings_table.php --no-interaction`. Quedó registrada en el batch 6. Las consultas posteriores a `information_schema` verificaron tres columnas bigint unsigned nullable y tres FKs a `media.id` con `DELETE_RULE=RESTRICT`. El rollback elimina las nuevas columnas y sus referencias: no usarlo sobre selecciones nuevas sin revisar cómo restaurar la identidad; los archivos permanecen.

**Resultados ejecutados:**

- Pruebas nuevas del selector: 16 aprobadas, 177 aserciones. Cubren confirmación/cancelación, dos campos, Repeater, carga y deduplicación antes de guardar el padre, create/edit y todos los consumidores, retirada, IDs/rutas/colecciones/propietarios inválidos, permisos revocados, MIME/tamaño de identidad, rutas legadas, correo, SEO, dimensiones y funcionamiento sin la migración aplicada.
- `php artisan test --compact`: 360 pruebas, 358 aprobadas, 2 omitidas, 2260 aserciones. Las omisiones requieren las variables de conexión MySQL indicadas abajo.
- `MYSQL_AUDIT_DATABASE=laravel_corporate_starter php artisan test --compact tests/Feature/MySqlReadOnlyTest.php`: 1 aprobada, 12 aserciones. Esta prueba se ejecutó antes de aplicar la migración de identidad; consulta el esquema anterior y no escribe datos. Las nuevas FKs se comprobaron posteriormente mediante consultas de solo lectura a `information_schema`.
- `vendor/bin/pint --dirty --format agent` y `vendor/bin/pint --test`: aprobados.
- `npm run build`: aprobado; permanece el aviso opcional de `fontaine`, sin instalar paquetes.
- `git diff --check`: aprobado. Se comprobaron además los 22 archivos de esta fase sin seguimiento, porque Git no los incluye en ese comando.
- Laravel Boost confirmó por consultas de solo lectura que el Media real id 1 conserva su nombre, tamaño y metadatos, que `general_settings` no contiene registros y que solo están aplicadas las migraciones multimedia anteriores. La migración autorizada añadió el esquema de identidad y su registro de ejecución; no cambió los registros de contenido ni los archivos. Antes y después se conservaron 1 Media, 1 MediaAsset y 0 registros de configuración general. El SHA-256 del original real sigue siendo `c28a4b02f422901dab6582c0760a476e75ec1ac519373e71904a8c12f5d660b9`.

**Comprobación posterior a la migración:** `php artisan test --compact tests/Feature/MediaPickerTest.php tests/Feature/GeneralSettingsTest.php tests/Feature/CacheTransactionsTest.php`: 37 aprobadas, 305 aserciones, en SQLite aislado. No se guardaron configuraciones o imágenes de prueba en la base real. La verificación del esquema real fue de solo lectura.

**Pendientes y límites:**

1. Revisión real de modal, puente de selección, carga anidada, claro/oscuro, 360/390/768/1024/1440 px, teclado, restauración del foco y consola Livewire/JavaScript. El usuario indicó continuar sin Chrome; los tests PHP y el build no reemplazan esa revisión.
2. Base MySQL vacía e independiente para CRUD, concurrencia y carreras referencia/borrado. `MYSQL_MEDIA_TEST_DATABASE` no está configurada; las pruebas aisladas de SQLite y la auditoría de solo lectura no demuestran estos casos en MySQL.
3. Confirmar límites efectivos del servidor web; los límites CLI conocidos no prueban el flujo web de 5 MB por imagen/25 MB por lote. No se reconfiguraron servicios.

## Verificación común y entrega de cada fase

Primero ejecutar el conjunto mínimo afectado. Después, al cerrar cada fase:

```sh
php artisan test --compact
vendor/bin/pint --dirty --format agent
vendor/bin/pint --test
npm run build
git diff --check
```

Probar escrituras/FKs/carreras en una base MySQL de pruebas explícitamente independiente. No ejecutar `RefreshDatabase`, migrations de prueba o `migrate:fresh` contra la base actual. La prueba MySQL de solo lectura existente no sustituye esta cobertura.

Entrega: archivos cambiados, nuevas migraciones propuestas/aplicadas en pruebas, comandos y resultados reales, decisiones, limitaciones y pasos manuales. No commits ni pushes. Completar una fase antes de iniciar otra; ninguna se ejecuta con la aprobación de este documento todavía pendiente.

## Autorizaciones, dependencias y límites del alcance

| Decisión o dependencia | Tratamiento |
| --- | --- |
| Comenzar el desarrollo | Requiere aprobación del usuario; esta entrega es exclusivamente auditoría y plan |
| Nuevas migraciones | Dos migraciones aditivas propuestas: hash e identidad. Ejecutarlas sobre la base con datos requiere autorización expresa; las pruebas usarán una base aislada |
| Límites nuevos | Aprobar 8 MP totales, 10 archivos y 25 MB por lote; conservar 5 MB por archivo si el servidor lo permite |
| PHP/servidor | Confirmar y, si hace falta, ajustar límites de subida/memoria por el desarrollador; no se reconfiguran desde el panel ni durante la auditoría |
| MySQL de pruebas | Disponer de una base vacía independiente para CRUD/concurrencia. No usar la base local existente como base destructiva de pruebas |
| Enriquecimiento de históricos | Comando diagnóstico sin escritura primero; ejecución que escriba hashes/dimensiones se revisa y autoriza antes de apuntar a datos reales |
| Archivos legados de identidad | Compatibilidad por fallback, sin mover/copiar/borrar. Adopción física posterior solo tras inventario y autorización específica |
| Nuevos paquetes, GD/Imagick, workers o servicios | Ninguno requerido por la propuesta base. Cualquier instalación o cambio de infraestructura necesitaría autorización adicional |
| Verificación visual | Requiere una sesión de navegador disponible cuando el usuario lo permita; no está realizada en esta auditoría |
| Optimización avanzada | No activar optimizadores binarios, AVIF, recortes ni cola por defecto. Las variantes WebP existentes cubren la base; medir antes de ampliar |

No se incluyen carpetas/taxonomías multimedia, editor tipo Photoshop, importación remota, vídeo/documentos, descarga ZIP, historial avanzado, backups, CDN obligatorio, Media Library Pro ni nuevas funcionalidades de negocio. El componente será reutilizable para nuevos módulos, pero esos módulos no se crean en estas tres fases.

## Referencias técnicas consultadas

- Laravel Boost: `application-info`, `database-schema`, `database-query` y `search-docs` para el stack instalado; consultas de Tables, Actions, FileUpload y seguridad Filament, uploads Livewire y comportamiento Laravel.
- [Filament 5: layouts de tablas](https://filamentphp.com/docs/5.x/tables/layout), [FileUpload](https://filamentphp.com/docs/5.x/forms/file-upload), [modales](https://filamentphp.com/docs/5.x/actions/modals) y [seguridad](https://filamentphp.com/docs/5.x/advanced/security).
- [Livewire 4: uploads](https://livewire.laravel.com/docs/4.x/uploads).
- [Spatie Media Library 11: añadir archivos](https://spatie.be/docs/laravel-medialibrary/v11/api/adding-files), [propiedades](https://spatie.be/docs/laravel-medialibrary/v11/advanced-usage/using-custom-properties).
- Verificación adicional en código instalado: `SpatieMediaLibraryFileUpload`, `DefaultPathGenerator`, `DefaultFileNamer`, `FileAdder`, `MediaObserver`, `InteractsWithMedia` y configuración Livewire. El acceso directo web a algunas páginas Spatie devolvió un tipo Markdown no admitido; se contrastaron los resultados de búsqueda oficiales con el código instalado.

La versión anterior de `docs/05-AUDITORIA-FINAL.md` declaraba completitud global; ese veredicto fue sustituido en la actualización del 14 de octubre. Este diagnóstico utiliza el estado observado y las pruebas ejecutadas ahora; no hereda su veredicto como evidencia de las funcionalidades solicitadas.


</details>
