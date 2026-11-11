# Requisitos funcionales y aceptación — v1

Actualizado: 14 de octubre de 2026. Describe el alcance funcional vigente. La implementación y las pruebas disponibles no equivalen a aceptación de producción; consultar [05 · Estado](05-AUDITORIA-FINAL.md).

**Responsabilidad de instalación:** el desarrollador instala y configura el stack y Laravel Boost; los agentes Codex y Antigravity implementan únicamente el código solicitado sobre el entorno verificado, sin instalar ni actualizar dependencias sin permiso.

Cada apartado es alcance del Starter Kit; la presentación pública se implementa con Blade específico del cliente.

## 1. Administración
- Dashboard ligero con accesos y contadores útiles, sin gráficos artificiales.
- Inicio/cierre de sesión, restablecimiento, perfil y cambio de contraseña.
- Usuarios con roles superadministrador, administrador (`admin` y alias `administrator`), editor y usuario de panel opcional; permisos efectivos por recurso/acción e individuales. `/panel` desactivado por defecto. La cuenta superadministradora tiene identidad fija documentada y se aprovisiona explícitamente.
- Primer administrador creado por comando seguro; sin credenciales por defecto.

**Aceptar:** editor no accede a ajustes críticos ni a acciones prohibidas mediante URL directa.

## 2. Configuración
- Identidad: nombre, descripción, logos, favicon.
- Contacto: email público, teléfono, WhatsApp, dirección, redes.
- Idioma/zona horaria y modo mantenimiento controlado.
- Valores por defecto razonables, validación y almacenamiento persistente.

**Aceptar:** cambios autorizados se reflejan en los helpers de frontend sin editar código.

## 3. Biblioteca multimedia
- Subida, listado, búsqueda, selección/reutilización, ALT, formatos y tamaños limitados.
- Optimización/conversión de imágenes cuando sea compatible; evitar procesar archivos peligrosos.
- Borrado seguro, respetando referencias; placeholders cuando falten archivos.

**Aceptar:** un medio puede utilizarse en varios recursos sin duplicarlo ni romper otras referencias.

## 4. Blog
- Artículos con título, slug único, extracto, contenido, destacada, categoría(s), estado borrador/publicado, fecha.
- Editor enriquecido con HTML sanitizado; listado público solo de publicados; SEO por artículo.
- Guardado atómico con categorías, revalidación de permisos y detección de ediciones simultáneas, incluyendo cambios de relaciones dentro del mismo segundo.
- Conservar slug personalizado, avisar de cambios sin guardar y crear redirección 301 al cambiar la URL de un artículo público desde el panel; conflictos sin pérdida del formulario.
- No hay autoguardado, historial completo ni programación de publicaciones. Imagen, extracto y categorías siguen siendo opcionales.

**Aceptar:** borradores nunca se indexan ni aparecen públicamente; slugs y paginación funcionan; no hay guardado parcial ni sobrescritura silenciosa. Fases 1–2 de mejora entregadas; listados/UX y cierre de fases 3–4 pendientes en [10 · Blog](10-PLAN-MEJORA-BLOG.md).

## 5. Servicios, testimonios y FAQ
- CRUD, activo/inactivo, orden manual y campos definidos en arquitectura.
- Servicios con slug y detalle opcional; testimonios sin puntuaciones inventadas; FAQ con respuesta enriquecida segura si se necesita.

**Aceptar:** el frontend puede consultar solo elementos activos y ordenados.

## 6. Legales y cookies
- Edición/versionado básico de términos y privacidad, publicación y fecha de última actualización.
- Banner de cookies: aceptar opcionales, rechazarlas, cambiar elección y categorías necesarias/estadísticas/marketing/preferencias, según la configuración habilitada.
- Sin carga de GA4 antes del consentimiento donde sea necesario; al revocar, detener nuevas cargas/eventos y gestionar cookies según implementación.

**Aceptar:** navegación sin aceptar analítica y preferencias persistentes y revisables.

## 7. SEO, redirecciones y analítica
- Valores globales y sobrescritura en contenidos que la exponen, especialmente artículos: title, description, OG, imagen, canonical, robots. En servicios el panel actual usa nombre/resumen/imagen para metadatos y no expone campos SEO separados; conserva los datos legados. El catálogo modal de proyectos utiliza metadatos de la página global.
- Sitemap solo de URLs públicas indexables; robots.txt; redirecciones 301/302 internas con validación y prevención de bucles.
- GA4 mediante `G-...` y Tag Manager mediante `GTM-...` validados; evitar instalación duplicada. Search Console con token/meta y sitemap. Integraciones personalizadas separadas, con permisos específicos, código cifrado, validación de marcado, finalidad y activación explícita; no son HTML libre para cualquier editor.

**Aceptar:** metadatos correctos, sitemap válido, redirecciones probadas y GA4 condicionado al consentimiento.

## 8. Contacto y correo
- Formulario validado con CSRF, rate limiting, honeypot y/o antispam configurable.
- Persistir mensajes con estado pendiente/leído; notificación por correo si está configurada.
- SMTP seguro, remitente/destinatario, prueba de envío restringida, plantilla HTML y alternativa texto.
- Si falla SMTP, el mensaje persistido no se pierde; mostrar resultado honesto.

**Aceptar:** entradas maliciosas rechazadas, correo de prueba verificable y errores registrados sin secretos.

## 9. Caché, rendimiento y seguridad
- Caché de lecturas repetidas y ajustes cuando tenga sentido; invalidación por cambios; acción administrativa limitada para caché de aplicación.
- Imágenes responsivas/lazy loading en frontend donde aplique; assets compilados con Vite.
- Autorización, validación, CSRF, rate limiting, errores 403/404/500 personalizados desde vistas Blade reemplazables.

**Aceptar:** contenido editado no queda obsoleto, usuarios no autorizados no ejecutan operaciones, errores no filtran datos.

## 10. Portafolio
- Proyectos con título, imagen y categoría obligatorios en el formulario; descripción de texto plano, cliente, fecha, enlace externo y estado.
- Catálogo público Livewire con filtro por categoría, nueve elementos por página y detalle modal; borradores excluidos y enlaces históricos redirigidos.
- Datos legados de galería, destacados y SEO se conservan aunque el formulario simplificado no los exponga.

**Aceptar:** un proyecto publicado puede abrirse sin exponer borradores; relaciones e imágenes se reutilizan; el detalle por query se valida. Ver [09 · Portafolio](09-MODULO-PORTAFOLIO.md).

## 11. Asistente de contacto
- Activación explícita y WhatsApp de destino válido; preguntas configurables con tipos y límites, consentimiento, resumen y foto opcional desde biblioteca.
- Registro idempotente de consulta e instantánea inmutable de preguntas/respuestas; enlace preparado de WhatsApp, sin envío automático.
- Simulación administrativa sin crear consultas ni abrir WhatsApp; validación y permisos de servidor.

**Aceptar:** configuración y simulación conservan datos; errores y reintentos no duplican consultas; WhatsApp no abre automáticamente sin enlace disponible. El envío efectivo y dispositivos reales requieren aceptación aparte. Ver [09 · Asistente](09-ASISTENTE-DE-CONTACTO.md).

## Fuera de alcance
Backups integrados; constructor visual; tiendas/pagos; CRM; reservas; newsletters; multi-tenant; multiidioma avanzado; dashboard de analítica propio; registro de actividad avanzado; programación de publicaciones como función obligatoria; sistema universal de páginas.

## Nota
Las obligaciones legales concretas de privacidad/cookies dependen del país, visitantes y servicios de cada cliente: la plantilla aporta controles técnicos, no garantiza cumplimiento jurídico por sí sola.
