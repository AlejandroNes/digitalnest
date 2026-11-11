# Arquitectura y contratos del Starter

Actualizado: 14 de octubre de 2026. Estado general y evidencia: [05 · Auditoría](05-AUDITORIA-FINAL.md).

## Organización

Monolito Laravel 13 con Filament 5 y Livewire 4. Cada cliente dispone de una instalación independiente. El panel administra datos; Blade y los controladores/componentes públicos los presentan con el diseño propio del cliente. Se usan modelos, Policies, validación, servicios concretos y componentes compartidos; no hay framework modular ni repository universal.

| Ubicación | Responsabilidad |
| --- | --- |
| `app/Filament/Resources` | CRUD y páginas por módulo |
| `app/Filament/Pages`, `Widgets`, `Support` | Ajustes, dashboard y apoyos del panel |
| `app/Http/Controllers`, `Requests`, `Middleware` | Rutas públicas, entrada validada y controles HTTP |
| `app/Models`, `Policies` | Persistencia, scopes, relaciones y autorización |
| `app/Services` | Caché, correo, consentimiento, SEO, multimedia y asistente |
| `app/Livewire` | Biblioteca, portafolio y gestión de integraciones |
| `resources/views` | Vistas públicas, componentes y errores sustituibles |
| `database`, `tests` | Migraciones, factories, seeders y pruebas aisladas |

## Modelos y paneles

- Identidad y seguridad: `User`, `Permission`, `RolePermission`; catálogo nativo de enums y asignaciones individuales. No se utiliza Spatie Permission.
- Ajustes: `GeneralSetting`, `SearchSetting`, `MailSetting`, `ContactAssistantSetting` y `CustomScript`.
- Contenido: `Post`/`PostCategory`, `Service`, `Testimonial`, `Faq`, `LegalPage`, `Project`/`ProjectCategory`/`ProjectImage`.
- Multimedia: `MediaAsset` conserva metadatos editoriales y el registro `Media` de Spatie conserva el archivo. Los consumidores referencian **`media.id`**.
- Comunicación: `ContactMessage`, con origen, lectura, intento/aceptación de notificación e instantáneas del asistente.
- Enrutamiento: `Redirect` valida rutas internas, conflictos y ciclos.

`/admin` es administrativo; `/panel` está desactivado por defecto. La habilitación de un panel no sustituye sus permisos. Ver [08 · Roles](08-ARQUITECTURA-ROLES-Y-PANELES.md).

## Contratos que deben conservarse

### Blog y publicación

`Post::published()` exige estado publicado y fecha no futura. El panel no admite programar publicaciones. HTML sanitizado, imágenes compartidas y categorías múltiples. Las páginas del blog guardan relaciones y registro en una transacción, revalidan permisos y detectan formularios obsoletos mediante huella protegida y bloqueo del registro. Los cambios de URL de artículos públicos desde el panel crean redirecciones 301 atómicas. Ver [10 · Blog](10-PLAN-MEJORA-BLOG.md).

### Multimedia

`MediaUploader`, `MediaLibraryBrowser` y `MediaPicker` son el punto común de ingestión y selección. Seleccionar no copia archivos; eliminar contenido no elimina el medio compartido. Mantener FKs restrictivas y el registro explícito de consumidores en `MediaAsset`. Las conversiones actuales son síncronas. Ver [06 · Multimedia](06-PLAN-BIBLIOTECA-MULTIMEDIA.md).

### Lecturas y caché

`ApplicationCache` invalida claves propias, también después de commit/rollback. Los ajustes públicos se cachean como datos simples; SMTP y consultas no forman parte de esa caché. Los destacados de proyectos cachean IDs por límite y revisión, luego consultan de nuevo publicación, estado destacado y relaciones. La limpieza general invalida cualquier tamaño de lista; las entradas antiguas caducan.

Cambios por SQL/query builder omiten eventos Eloquent: requieren invalidación explícita. No activar deserialización general de objetos para resolver errores de caché.

### Integraciones y consentimiento

`Seo`, `Sitemap`, `SiteIndexing` e `InternalUrl` mantienen metadatos y URLs públicas. `CookieConsent` controla finalidades y revisión de privacidad. `SiteIntegrations` limita las integraciones al frontend público; `ScriptMarkup` valida fragmentos. GA4 y GTM usan campos específicos; los fragmentos personalizados son código de proveedores de confianza, no un editor HTML universal. El cifrado no convierte un script en seguro.

### Contacto

El mensaje se persiste antes del intento SMTP; fallo de correo no elimina la consulta. No hay reenvío automático. La recuperación de contraseña usa correo general del entorno; los ajustes de Comunicación no reconfiguran globalmente Laravel Mail. El asistente registra la consulta y prepara un enlace a WhatsApp; no verifica entrega ni envía por API.

## Extensión y seguridad

Agregar módulos convencionales con migraciones, modelos, Policies y Resources. Conservar rutas nombradas, validación, CSRF, límites, escape de texto y sanitización de HTML. Evitar consultas en Blade y cargar relaciones utilizadas por los listados. Las vistas de error no dependen de la base ni de Vite.

Los permisos se comprueban en servidor, también en acciones personalizadas. No registrar secretos ni contenido privado. No cambiar `.env`, claves o servicios desde el panel. Cualquier modificación compartida requiere pruebas de sus consumidores.

Una plantilla GitHub no propaga cambios automáticamente a repositorios derivados. No se ha creado un sistema de plugins ni de actualizaciones entre clientes.
