# Portafolio y proyectos

Actualizado: 14 de octubre de 2026. Documenta el formulario simplificado y el catálogo modal actuales. Los resultados globales y límites están en [05 · Auditoría](05-AUDITORIA-FINAL.md).

## Administración

El grupo **Portafolio** contiene Proyectos y Categorías de proyectos. `ProjectResource` presenta información principal, imagen de biblioteca y datos complementarios mediante componentes Filament.

| Campo del formulario | Comportamiento |
| --- | --- |
| Título | Obligatorio; no cambia automáticamente el slug existente al editar |
| Descripción | Texto plano opcional; conserva saltos de línea y se escapa al mostrar |
| Imagen | Obligatoria en el formulario; `MediaPicker` compartido |
| Categoría | Obligatoria en el formulario; relación existente |
| Estado | Borrador/publicado; publicación en tabla con validación y autorización |
| Cliente/empresa | Opcional |
| Fecha de realización | Opcional; no futura |
| Enlace externo | Opcional; solo HTTP/HTTPS |

El formulario actual no expone slug, galería, destacados, orden, ubicación ni SEO individual. Esos datos/relaciones legados se conservan al editar otros campos. No reintroducir las antiguas pestañas ni eliminar columnas como parte de una limpieza documental.

El modelo conserva `short_description` y HTML `content`; el atributo de compatibilidad `description` presenta una descripción plana combinada. Los cambios ajenos a esa descripción preservan el contenido legado. Los archivos compartidos no se eliminan al reemplazar o borrar un proyecto.

## Modelos, relaciones y migraciones

- `Project`, `ProjectCategory`, `ProjectImage` y enum `ProjectStatus` (`draft`, `published`).
- `Project::category()` y `galleryImages()`; `image` y `seoImage` apuntan a `media.id`.
- Migraciones `2026_10_13_030000_create_project_categories_table.php`, `030001_create_projects_table.php` y `030002_create_project_images_table.php` con el mismo prefijo de fecha completo.
- Las migraciones son la referencia de tipos, longitudes y FKs. El formulario puede ser más restrictivo que las columnas legadas; una relación nullable en base no hace opcional la selección exigida por el panel.

`ProjectPolicy` implementa ver, crear, editar y eliminar; no hay restauración ni papelera prometidas. `ProjectCategoryPolicy` bloquea eliminar una categoría con proyectos. Las comprobaciones de permisos de publicación en tabla vuelven a consultar al usuario actual.

## Catálogo público

| Ruta | Comportamiento actual |
| --- | --- |
| `GET /proyectos` · `projects.index` | Vista con componente Livewire `Portfolio` |
| `?categoria=slug` | Filtro por categoría activa; reinicia paginación al cambiar |
| `?proyecto=slug` | Abre inicialmente el detalle modal de un proyecto publicado |
| `GET /proyectos/{slug}` · `projects.show` | 301 a `/proyectos?proyecto=slug`; 404 si no está publicado |

El componente pagina **9 proyectos**, ordenados por ID descendente. Carga categoría e imagen de forma anticipada. Los filtros muestran categorías activas con proyectos publicados; una categoría inactiva no se ofrece como filtro, pero por sí sola no retira la publicación de un proyecto.

El modal muestra imagen, descripción y datos opcionales. Se revalida publicación en cada render; un borrador no se obtiene manipulando ID/slug. Los enlaces externos se filtran y se abren con `noopener noreferrer`.

La vista vigente es `resources/views/public/projects/index.blade.php` y el componente es `resources/views/livewire/portfolio.blade.php`. Una plantilla antigua de detalle, si se conserva, no representa una ruta de detalle activa. La página utiliza SEO global; el sitemap incorpora el catálogo `/proyectos`, no una URL individual por modal.

## Lectura desde Blade o servicios

```php
use App\Models\Project;

$projects = Project::query()->published()->ordered()->with(['category', 'image.model'])->paginate(9);
$featured = Project::featuredPublished(limit: 3);
$byCategory = Project::query()->published()->byCategory('desarrollo-web')->get();
$related = $project->relatedProjects(limit: 3);
$gallery = $project->galleryImages()->with('media.model')->get();
```

Los helpers legados de destacados, relacionados y galería siguen disponibles para un frontend específico. No implican que el panel simplificado o el modal muestren esas opciones. Consultar datos desde controlador/componente, evitando consultas repetidas en Blade.

## Caché corregida de destacados

`Project::featuredPublished($limit)` cachea **IDs**, nunca colecciones de modelos. Usa una revisión común y una clave por límite, con caducidad de una hora para las listas. Guardar/eliminar un proyecto o limpiar caché de aplicación invalida la revisión para cualquier tamaño, incluido 7 u 11.

La consulta final vuelve a exigir publicado/destacado, ordena y carga relaciones actuales. Esto evita el error de deserialización con Laravel 13 y la aparición de proyectos retirados por una lista obsoleta. No habilitar `cache.serializable_classes=true` como solución alternativa.

Cambios por SQL/query builder omiten eventos Eloquent: requieren invalidación explícita para refrescar composición/orden de las listas. La revalidación de publicación evita mostrar un borrador aunque un ID siga cacheado. Los datos históricos de listas con claves antiguas dejan de usarse y caducan.

## Seguridad, pruebas y extensión

Pruebas: `ProjectManagementTest`, `PortfolioTest`, `PortfolioTransportTest`, `ApplicationCacheTest` y `CacheTransactionsTest`. Cubren formulario, autorización, archivos compartidos, borradores, filtros/paginación, modal, enlaces, caché persistente e invalidación.

No se certifican aquí concurrencia MySQL entre procesos ni el diseño final de cada cliente. Personalizar Blade conservando scopes, validación, Policies, referencias multimedia, SEO y consentimiento. Las mejoras de blog de [10](10-PLAN-MEJORA-BLOG.md) no deben atribuirse automáticamente a las páginas de portafolio.
