# Plan de mejora del blog administrativo

Fecha: 14 de octubre de 2026.
Estado: fases 1 y 2 implementadas y verificadas; fases 3 y 4 pendientes.

## Objetivo y alcance

Reforzar Artículos y Categorías del blog para reducir errores de edición, guardados parciales, pérdida de cambios y enlaces rotos. Mejorar la claridad del panel conservando Laravel, Filament, las Policies y la biblioteca multimedia existentes.

Este plan no garantiza ausencia absoluta de errores: define comportamientos verificables y pruebas para los riesgos principales. Se ejecutará una fase a la vez, revisando el diff y las pruebas antes de avanzar.

No incluye rediseño público del blog, comentarios, autores adicionales, newsletter, traducciones, programación de publicaciones, autoguardado, historial completo de versiones ni paquetes nuevos. Se mantienen las rutas actuales, los roles y la fecha de publicación en UTC. Imagen, extracto y categorías conservan su carácter opcional; no se impondrán nuevos requisitos editoriales sin una necesidad acordada.

## Estado actual de las fases

| Fase | Estado |
| --- | --- |
| 1 · Guardado, validación y permisos | Implementada y verificada |
| 2 · Edición segura y URLs estables | Implementada y verificada |
| 3 · Claridad y eficiencia del panel | Pendiente |
| 4 · Regresión, accesibilidad y cierre | Pendiente de cierre integral; ya existe cobertura específica de las fases entregadas |

El diagnóstico y las tareas siguientes se conservan como trazabilidad del plan original. Sus observaciones sobre ausencia de transacciones, aviso o protección de edición fueron resueltas por las fases 1–2, según la entrega al final. El estado global se mantiene en [05 · Auditoría](05-AUDITORIA-FINAL.md).

## Base comprobada al preparar el plan

- PHP 8.4.24, Laravel 13.35.0, Filament 5.10.1 y PHPUnit 12.5.33; Pest también está instalado.
- Artículos con título, slug único, extracto, HTML enriquecido, categorías múltiples, imagen, estado, fecha y SEO individual.
- `Post::published()` excluye borradores, publicaciones sin fecha y fechas futuras.
- El formulario exige fecha no futura al publicar. No se habilitará publicación programada.
- El HTML se sanitiza al asignar contenido y al renderizar la vista pública. Los adjuntos del editor están desactivados.
- Artículos y categorías tienen Policies. Las categorías utilizadas están protegidas por Policy y FK restrictiva.
- Las imágenes utilizan el selector compartido y referencias a `media.id`.
- El sitemap tiene invalidación por cambios del modelo a través de `HasSeo`.
- Pruebas ejecutadas en esta revisión: `ContentManagementTest`, `SearchAdministrationTest` y `SitemapControlsTest`: **68 aprobadas, 551 aserciones**. Cubren también otros contenidos; no equivalen a cobertura completa del blog.

Existen cambios locales previos en `PostResource.php` y otros módulos, incluidos la distribución del formulario y el contador de borradores. Se conservarán y revisarán antes de implementar. La preparación del plan añadió solo este documento; las fases 1 y 2 posteriores modificaron los archivos indicados en su entrega.

## Hallazgos iniciales y riesgos que orientaron el plan

| Prioridad | Observación en el código | Trabajo propuesto |
| --- | --- | --- |
| Alta | `ContentFields::title()` vuelve a asignar el slug cada vez que cambia el título durante la creación. | Conservar un slug modificado manualmente; no alterar URLs al editar el título. |
| Alta | Las páginas del blog no habilitan explícitamente transacciones y el proveedor del panel tampoco las habilita. | Guardar artículo, relaciones y cambios de URL de manera atómica; probar fallo de relaciones. |
| Alta | No hay un mecanismo propio de detección de edición simultánea en las páginas del blog. | Evitar que un formulario antiguo sobrescriba cambios de otra sesión. |
| Alta | `Post` no crea redirecciones cuando cambia el slug. | Mantener enlaces anteriores de artículos publicados mediante el sistema de redirecciones existente. |
| Media | No se configura aviso de cambios sin guardar en las páginas ni en el panel revisado. | Activar el comportamiento nativo en el blog y verificar navegación real. |
| Media | El listado de artículos solo muestra título, estado y fecha; categorías solo nombre y slug. | Añadir información y filtros útiles sin consultas por fila ni acciones masivas peligrosas. |
| Media | El selector de categorías usa `preload()`. | Comprobar consultas y limitar resultados al buscar para catálogos grandes. |

Son observaciones de código y riesgos a cubrir. Salvo los comportamientos descritos directamente, no se han reproducido fallos de concurrencia o guardados parciales durante esta revisión.

## Fase 1 — Guardado, validación y permisos

1. Habilitar transacciones en creación y edición del blog, sin cambiar globalmente otros módulos. Incluir sincronización de categorías y futuras redirecciones en la misma operación.
2. Mantener las validaciones existentes y añadir rechazo de contenido visualmente vacío después de sanitizar, por ejemplo `<p><br></p>`. Conservar el contenido válido cuando falle otra validación.
3. Probar estado inválido, fecha ausente/futura, slug duplicado, categorías inexistentes o eliminadas durante la edición, imágenes desaparecidas y permisos revocados después de abrir el formulario.
4. Revalidar la autorización efectiva en servidor al guardar/eliminar. Verificar también usuarios inactivos y accesos directos; no basta con ocultar acciones.
5. Traducir conflictos esperables de unicidad y relaciones a mensajes claros, manteniendo el formulario y sin filtrar SQL ni trazas. No capturar indiscriminadamente errores inesperados ni simular éxito.

**Archivos previstos:** `Posts/Pages/CreatePost.php`, `Posts/Pages/EditPost.php`, páginas equivalentes de categorías, `PostResource.php` y pruebas específicas del blog. Tocar Policies solo si una prueba demuestra una carencia.

**Aceptación:** un fallo no deja artículos parcialmente guardados ni relaciones incompletas; el usuario conserva lo escrito y recibe un error útil; una cuenta sin permiso no modifica datos. Los borradores siguen siendo privados.

## Fase 2 — Edición segura y URLs estables

1. Ajustar la generación del slug para que siga al título únicamente mientras no haya sido personalizado. Al editar un artículo existente, el título no cambia su slug.
2. Antes de modificar un slug publicado, mostrar una explicación de su impacto. Crear una redirección interna 301 desde la URL anterior usando `Redirect` e `InternalUrl`.
3. Validar colisiones con artículos existentes, redirecciones previas, cadenas y ciclos. Si no se puede conservar el enlace anterior de forma segura, rechazar el cambio de slug sin perder el resto del formulario. No reutilizar el `catch (Throwable)` silencioso de otros módulos.
4. No exponer borradores mediante redirecciones. Las URLs deben resolver siempre a contenido actualmente público o devolver el error correspondiente. No crear una vista previa pública de borradores.
5. Activar aviso nativo de cambios sin guardar solo en las páginas del blog. No añadir autoguardado ni almacenamiento del contenido en el navegador.
6. Detectar formularios obsoletos mediante una revisión confiable comprobada dentro de la transacción y con bloqueo del registro. Preferir una huella de los datos y categorías guardados, protegida frente a cambios del cliente; `updated_at` por sí solo no basta para cambios en el mismo segundo. En conflicto, conservar el formulario y pedir recargar conscientemente; sin mezcla automática ni sobrescritura silenciosa.

**Archivos previstos:** recursos/páginas del blog, `Post.php` si el ciclo de vida lo requiere, integración con `Redirect` y pruebas nuevas. `ContentFields.php` solo si el ajuste es reutilizable y se comprueban sus consumidores; en caso contrario, acotar el cambio al blog.

**Aceptación:** el slug manual se conserva; editar el título no rompe enlaces; las URLs antiguas funcionan después del cambio permitido; dos sesiones no pierden cambios silenciosamente; la publicación y los permisos se vuelven a comprobar al guardar.

## Fase 3 — Claridad y eficiencia del panel

1. Conservar la distribución local del formulario y revisar su uso real antes de reorganizarla. Incluir instrucciones breves para extracto, categorías, imagen y SEO; indicar qué campos son opcionales.
2. En publicación, explicar borrador/publicado y mostrar la zona horaria de forma consistente en formulario y listado. Mantener UTC y no convertir datos existentes. La fecha sigue siendo obligatoria al publicar; no añadir automatismos que publiquen accidentalmente.
3. Añadir categorías y última modificación al listado de artículos; filtrar por categoría y estado, conservando búsqueda y paginación. Añadir acceso al artículo público solo cuando esté realmente publicado y exista autorización para verlo en el panel.
4. Mostrar número de artículos por categoría mediante `withCount` y explicar por qué no se elimina una categoría en uso. No añadir borrado en cascada de artículos ni desvinculación automática.
5. Revisar la búsqueda de categorías sin precargar el catálogo completo y cargar relaciones de los listados de forma anticipada. Medir consultas con varias filas antes de añadir índices o caché.
6. Conservar SEO opcional, precedencia global/individual y selección multimedia compartida. Separar consejos editoriales de errores: longitudes recomendadas, ALT y proporciones no se convertirán automáticamente en requisitos nuevos.
7. Mantener confirmación de eliminación y estados de guardado/carga claros. No añadir columnas editables de publicación ni acciones masivas en esta fase.

**Archivos previstos:** `PostResource.php`, `PostCategoryResource.php`, páginas de listado/edición y pruebas del panel. No cambiar CSS compartido ni el proveedor global salvo necesidad demostrada y revisada.

**Aceptación:** se identifican borradores, categorías y cambios recientes sin abrir cada artículo; filtros y paginación funcionan juntos; las consultas no crecen una por cada fila; la categoría en uso permanece intacta al intentar eliminarla.

## Fase 4 — Regresión, accesibilidad y cierre

**Pruebas automatizadas previstas:**

- Crear/editar borrador, publicar, retirar publicación y eliminar con permisos adecuados.
- Artículo y categorías se confirman o revierten juntos; fallo de relación y conflicto de slug sin guardado parcial.
- HTML malicioso y visualmente vacío; enlaces seguros; formato permitido conservado al reabrir el editor.
- Acceso directo y acciones Livewire sin permiso, tras revocación y con cuenta inactiva.
- Slug manual, edición de título, redirecciones, ciclos, colisiones y aislamiento de borradores.
- Dos editores con formularios abiertos y conflictos con cambios de categorías, incluyendo modificaciones dentro del mismo segundo.
- Imágenes compartidas: selección, sustitución, retirada, permisos y conservación de archivos utilizados.
- SEO, canonical, noindex y sitemap después de publicación, retirada, eliminación y cambio de slug; comprobar que no quedan URLs antiguas en el sitemap.
- Búsqueda, filtros, paginación y ausencia de N+1 en las nuevas columnas.

**Verificación visual:** 360, 390, 768 y 1440 px; teclado, foco, mensajes de error, aviso de salida, editor, selector multimedia y consola JavaScript. Distinguir los resultados reales de las comprobaciones aún pendientes. Usar únicamente datos ficticios en un entorno de pruebas.

**Comandos por fase:** pruebas específicas con `php artisan test --compact <archivos>`, después `php artisan test --compact`, `vendor/bin/pint --dirty --format agent` si cambió PHP, `git diff --check` y `npm run build` cuando haya cambios que afecten recursos visuales. Pint puede tocar cambios locales ajenos: revisar y evitar modificaciones incidentales.

**Entrega por fase:** diff y archivos cambiados, pruebas ejecutadas con sus resultados, decisiones, riesgos y pasos manuales. Registrar explícitamente cualquier verificación que no se haya podido realizar. No declarar completada la revisión visual a partir de pruebas PHP.

## Datos, migraciones y coordinación

- No se prevén nuevas tablas ni dependencias. Aprovechar las relaciones y restricciones existentes.
- No modificar datos ni aplicar migraciones a la instalación local para preparar este plan.
- Si una corrección requiere cambiar esquema, justificar primero una migración aditiva/reversible y su compatibilidad con datos existentes. No introducirla silenciosamente.
- Las pruebas automatizadas seguirán aisladas de datos reales. La validación MySQL o de despliegue no se incorpora por esta propuesta; el usuario descartó esa tarea en la revisión anterior.
- Antes de cada fase, revisar Git, versiones, reglas aplicables y documentación de Boost. Un agente por tarea; no editar simultáneamente `PostResource.php` u otros archivos compartidos.
- Mantener pruebas existentes y añadir cobertura específica solo donde detecte un riesgo distinto. Si un cambio afecta componentes compartidos, ejecutar también las pruebas de sus consumidores.

## Criterio de terminado

Para declarar el módulo completo deberán estar implementadas y verificadas las cuatro fases; publicación, permisos, relaciones, URLs e imágenes conservan sus contratos; no hay pérdida silenciosa de edición; las pruebas y formato pasan; la revisión visual tiene evidencia y las limitaciones externas están declaradas. El blog continúa siendo un módulo convencional de Filament adaptable a cada cliente.


## Entrega de fases 1 y 2 — 14 de octubre de 2026

### Cambios realizados

- Creación y edición de artículos/categorías con transacciones locales a sus páginas, incluidas las relaciones. Eliminación con transacción y autorización actualizada.
- Revalidación de permisos desde el usuario actual de base de datos al guardar/eliminar. Se conservan los roles y Policies existentes.
- Rechazo de contenido visualmente vacío tras convertir el estado del editor y sanitizarlo. No se cambian los requisitos opcionales de imágenes, extracto o categorías.
- Conflictos esperables de unicidad y referencias convertidos en errores de formulario o avisos de eliminación, sin SQL visible ni éxito ficticio. Los errores inesperados se propagan al manejo habitual.
- Generación del slug acotada al blog: artículos y categorías conservan una URL personalizada; el título no modifica el slug de un registro existente.
- Cambio de slug de artículo público con redirección 301 en la misma transacción. Los alias activos que apuntaban directamente a la URL anterior se actualizan al destino nuevo para evitar cadenas crecientes. Se conservan sus códigos y estado.
- Colisiones con URLs reservadas por redirecciones se rechazan; no se sobrescriben redirecciones existentes en el origen. Los cambios directos por SQL o fuera de estas páginas no generan redirecciones automáticamente.
- Las redirecciones no eluden `Post::published()`: al retirar publicación, la URL de destino devuelve 404. Un cambio de slug de un borrador no crea redirección.
- Comparación de huella de atributos y categorías con revisión bloqueada por Livewire, y bloqueo del registro dentro de la transacción. Detecta cambios de otra sesión incluso dentro del mismo segundo; no mezcla ni sobrescribe automáticamente. Incluye protección de edición y eliminación de formularios obsoletos.
- Aviso nativo de cambios sin guardar habilitado únicamente en las páginas de creación/edición del blog.

### Archivos de esta entrega

- `app/Filament/Resources/Posts/PostResource.php` y `Pages/{CreatePost,EditPost}.php`.
- `app/Filament/Resources/PostCategories/PostCategoryResource.php` y `Pages/{CreatePostCategory,EditPostCategory}.php`.
- `app/Filament/Support/BlogEditor.php`: lógica compartida únicamente por estas páginas.
- `tests/Feature/BlogEditingTest.php` y `tests/Feature/BlogPublicationTest.php`.
- Este documento. Se conservaron los cambios locales previos de distribución, navegación, otros módulos y caché de proyectos.

### Verificación y límites

- Cierre de fase 1: suite completa con **770 aprobadas y 2 omitidas**; Pint ejecutado.
- Cierre final de fases 1 y 2: `php artisan test --compact`, **788 aprobadas, 2 omitidas, 5.073 aserciones**.
- Pruebas específicas finales: **30 aprobadas, 230 aserciones**. Incluyen fallo de relación real con rollback y reintento; conflicto de unicidad durante guardado; categoría o archivo original desaparecidos; autorización revocada; dos formularios con cambios simultáneos de atributos o categorías; revisión protegida frente a modificaciones del cliente; URLs y redirecciones; canonical y sitemap.
- `vendor/bin/pint --dirty --format agent` y `git diff --check`: correctos.
- `npm run build`: correcto, con el aviso opcional conocido de `fontaine`; sin instalar paquetes.
- Chrome verificó generación de slug, conservación del slug manual al modificar el título y aparición del aviso nativo al salir con cambios. Solo se escribió texto ficticio sin guardar; el listado continuó sin artículos nuevos. No se considera realizada la auditoría visual completa de fase 4.
- La suite usa SQLite aislado. No se certifican carreras entre procesos MySQL ni despliegues; esas verificaciones siguen fuera de esta tarea por la decisión previa del usuario.
- Sin migraciones nuevas, instalaciones, cambios de servicios, credenciales, commits ni pushes. No se modificaron artículos reales para ejecutar las comprobaciones.

### Uso y pendientes

No hay pasos manuales de base de datos ni cambios de entorno para activar estas fases. Recargar formularios abiertos antes de la actualización. Si se detecta un conflicto, conservar el texto pendiente antes de recargar y revisar la versión guardada; no hay recuperación automática ni historial de versiones.

Permanecen pendientes las mejoras de listados/formularios de fase 3 y la revisión visual, accesibilidad y cierre integral de fase 4. La autorización de estas fases 1 y 2 no se interpreta como autorización para implementar las siguientes. La actualización transversal de Markdown del 14 de octubre conserva estos estados y no inicia una fase nueva.
