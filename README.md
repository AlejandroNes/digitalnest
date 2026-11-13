# DigitalNest

PWA en español de **cupones, ofertas y rankings de las mejores apps** de todos los sectores (IA, vídeo, audio, imágenes, hosting, VPN, productividad, etc.), con diseño de app móvil premium. El producto y la reestructuración están en [12 · Plan de reestructuración](docs/12-PLAN-REESTRUCTURACION-OFERTAS.md), que sustituye la dirección de producto de [11 · Plan DigitalNest](docs/11-PLAN-DIGITALNEST.md).

## Secciones públicas

| Sección | Ruta | Contenido |
| --- | --- | --- |
| Inicio | `/` | Buscador, ofertas que caducan pronto, sectores, ofertas destacadas, ranking destacado y apps nuevas |
| Explorar | `/explorar`, `/sector/{slug}` | Sectores y subcategorías con sus ofertas, rankings y apps |
| Ficha de app | `/app/{slug}` | Nota, precio, plataformas, cupones, pros/contras, opinión y alternativas |
| Ofertas | `/ofertas`, `/oferta/{slug}` | Feed con filtros (cupones, descuentos, pago único, prueba gratis, caducan pronto) y detalle |
| Salida de afiliado | `/go/{slug}` | Redirección que cuenta clics sin datos del visitante |
| Rankings | `/top`, `/top/{slug}` | Listas ordenadas por sector |
| Guardados | `/guardados` | Ofertas guardadas en el navegador, sin cuenta |
| Guías y búsqueda | `/blog`, `/buscar` | Tutoriales y búsqueda de ofertas, apps, sectores, rankings y guías |
| Páginas fijas | `/como-probamos`, `/afiliados`, `/sobre-digitalnest`, `/contacto`, `/legal/*` | Método, transparencia, contacto y legales |

Las URLs del sitio anterior (`/herramientas*`, `/flujos*`, `/plantillas*`) redirigen con 301. Flujos y plantillas siguen en el panel pero ya no son públicos. La app es instalable (manifest, iconos, service worker con página `/offline`). En local, `/_diseno` muestra el sistema de diseño. Sectores iniciales: `php artisan db:seed --class=SectorSeeder` (idempotente, apto para producción).

Sistema de diseño: tokens en `resources/css/nest.css`, layout en `resources/views/nest/layout.blade.php` y componentes en `resources/views/components/nest/`. [DESIGN.md](DESIGN.md) describe la identidad anterior. Contexto de producto: [PRODUCT.md](PRODUCT.md). Los módulos corporativos del kit base (servicios, portafolio, testimonios, FAQ) están ocultos con `CORPORATE_MODULES_ENABLED=false`. Datos de ejemplo locales: `php artisan db:seed --class=DigitalNestDemoSeeder` (no se ejecuta en producción).

Se construye sobre una base Laravel con administración Filament; lo que sigue describe esa base.

## Stack y entorno

| Componente | Estado comprobado |
| --- | --- |
| PHP / Laravel | 8.4.24 / 13.35.0 |
| Filament / Livewire | 5.10.1 / 4.4.7 |
| Multimedia | Spatie Media Library 11.23.9 e integración Filament 5.10.1 |
| Frontend | Blade, Tailwind CSS 4.3.3, Vite 8.3.4; Alpine provisto por Filament/Livewire en el panel |
| Pruebas | PHPUnit 12.5.33 y Pest 4.7.8; suite habitual en SQLite aislado |
| MySQL | Objetivo documentado: 8.4; servidor local observado: 9.7.1 |

Las versiones de referencia no reemplazan `composer.lock`, `package-lock.json` ni la comprobación del entorno destino. El usuario instala y configura tecnologías; los agentes no instalan, actualizan ni reconfiguran servicios sin autorización.

## Funcionalidad disponible

- Panel administrativo con dashboard, usuarios, permisos por rol e individuales, perfil y recuperación de contraseña. Roles `super_admin`, `admin`/`administrator`, `editor` y `user`.
- Panel de clientes `/panel`, desactivado por defecto. No hay multi-tenancy ni una funcionalidad comercial de clientes incluida.
- Identidad, contacto, redes, idioma, zona horaria y mantenimiento público.
- Biblioteca compartida: imágenes, ALT, carga por lotes, deduplicación de nuevas subidas, selección visual, referencias y protección de borrado.
- Blog, categorías, servicios, testimonios, FAQ y legales. El blog tiene guardado atómico, detección de edición simultánea, aviso de salida y redirecciones al cambiar URLs públicas desde el panel.
- Portafolio con categorías, catálogo Livewire, filtros, paginación y detalle modal.
- SEO global e individual donde lo expone cada módulo, sitemap, robots, redirecciones, Search Console, GA4, Tag Manager e integraciones personalizadas restringidas por permisos y finalidad.
- Consentimiento para estadísticas, marketing y preferencias; rechazo y cambio posterior disponibles.
- Contacto, bandeja, SMTP cifrado/restringido y prueba de envío; consulta guardada independientemente del resultado del correo.
- Asistente de contacto con preguntas configurables, simulación administrativa, teléfono internacional y enlace de WhatsApp. No envía mensajes automáticamente.
- Caché limitada a claves propias, imágenes optimizadas, validación, autorización y vistas de error sustituibles.

## Estado de trabajo

La última suite completa (13 de noviembre de 2026) aprobó **849 pruebas y omitió 2**. Formato, build y comprobación del diff pasaron. No se ha calculado un porcentaje de cobertura. Los resultados no certifican correo real, despliegues, MySQL 8.4 ni accesibilidad completa.

Las fases **1 y 2 de mejora del blog están terminadas**; las fases **3 y 4 siguen pendientes**. Las verificaciones externas de producción fueron descartadas por el usuario como tarea de esta sesión; permanecen como aceptación requerida de cada instalación, sin ejecutarse automáticamente.

## Instalación y colaboración

Seguir [Despliegue](docs/04-DESPLIEGUE.md) para una instalación nueva o una actualización. No ejecutar `composer run setup` como comprobación inocua: instala dependencias, genera clave, migra y compila. No volver a generar `APP_KEY` en una instalación existente.

Codex, Antigravity y otros agentes deben leer [AGENTS.md](AGENTS.md), revisar Git y respetar el trabajo local de otros colaboradores. Laravel Boost aporta contexto si está accesible, pero no reemplaza código ni pruebas.

## Documentación

| Documento | Propósito |
| --- | --- |
| [01 · Arquitectura](docs/01-ARQUITECTURA.md) | Estructura, contratos y extensión |
| [02 · Requisitos](docs/02-REQUISITOS-FUNCIONALES.md) | Alcance y aceptación |
| [03 · Plan general](docs/03-PLAN-DE-IMPLEMENTACION.md) | Estado de fases y siguientes trabajos |
| [04 · Despliegue](docs/04-DESPLIEGUE.md) | Instalación, configuración y aceptación por destino |
| [05 · Estado y auditoría](docs/05-AUDITORIA-FINAL.md) | Evidencia y límites de las comprobaciones |
| [06 · Multimedia](docs/06-PLAN-BIBLIOTECA-MULTIMEDIA.md) | Contratos del selector, carga, referencias e historial |
| [07 · Comunicación](docs/07-PLAN-COMUNICACION.md) | Bandeja, SMTP, resultados y límites |
| [08 · Roles y paneles](docs/08-ARQUITECTURA-ROLES-Y-PANELES.md) | Permisos, superadministrador y panel opcional |
| [09 · Asistente](docs/09-ASISTENTE-DE-CONTACTO.md) | Integración, preguntas, privacidad y simulación |
| [09 · Portafolio](docs/09-MODULO-PORTAFOLIO.md) | Administración y catálogo público actual |
| [10 · Mejora del blog](docs/10-PLAN-MEJORA-BLOG.md) | Fases 1–2 entregadas y 3–4 pendientes |
| [11 · Plan DigitalNest](docs/11-PLAN-DIGITALNEST.md) | Plan anterior (directorio de herramientas), sustituido por el 12 |
| [12 · Plan de reestructuración](docs/12-PLAN-REESTRUCTURACION-OFERTAS.md) | Producto actual: PWA de ofertas, cupones y rankings |

## Límites del producto

Sin backups integrados, ecommerce, CRM, reservas, newsletter, constructor de páginas, multiidioma avanzado ni multi-tenancy. No requiere Redis, workers ni cron para los flujos síncronos actuales. Una integración de terceros o un frontend concreto puede introducir requisitos adicionales que deberán revisarse aparte.
