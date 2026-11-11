# Plan general y estado de implementación

Actualizado: 14 de octubre de 2026. Implementar una fase/tarea a la vez, sobre el entorno instalado por el usuario y conservando cambios locales. Los planes históricos no autorizan instalaciones, migraciones sobre datos, despliegues ni fases adicionales.

## Estado de las fases base

| Fase | Trabajo disponible | Pendientes de aceptación |
| --- | --- | --- |
| 0 · Entorno | Stack instalado e inspeccionado; Boost accesible; lockfiles y pruebas disponibles | Repetir comprobaciones en cada destino; MySQL local 9.7.1 no certifica objetivo 8.4 |
| 1 · Panel y ajustes | Dashboard, autenticación, perfiles, usuarios, permisos y ajustes | Recuperación de contraseña con correo real y aceptación por instalación |
| 2 · Contenido | Biblioteca, blog/categorías, servicios, testimonios, FAQ y legales | Revisión visual completa y carreras multimedia en MySQL independiente |
| 3 · SEO y consentimiento | Metadatos, sitemap, robots, redirecciones, GA4, GTM, integraciones y finalidades | Validar proveedores y carga real en cada frontend configurado |
| 4 · Comunicación | Contacto, bandeja, SMTP restringido, pruebas simuladas y asistente | Entrega real, DNS, hosting y WhatsApp efectivo no certificados |
| 5 · Rendimiento y cierre | Caché e invalidación, imágenes, errores y guía de despliegue | CI versionado, clon limpio, revisión integral y release no acreditados |

Las fases base tienen implementación, pero no deben marcarse todas como cerradas de producción. [05 · Auditoría](05-AUDITORIA-FINAL.md) centraliza resultados y límites actuales.

## Ampliaciones y mejoras ya integradas

- Biblioteca multimedia: fases de ingestión, navegador visual e integración del selector implementadas; revisión visual integral y concurrencia MySQL pendientes.
- Roles: cuatro roles funcionales, alias `administrator`, permisos individuales y plantilla configurable del administrador; `/panel` opcional.
- Comunicación: mejoras de transporte, diagnóstico seguro y bandeja implementadas.
- Asistente: preguntas configurables, instantáneas, foto, simulación y teléfono internacional.
- Portafolio: administración y catálogo modal Livewire con filtro y paginación.
- Destacados de proyectos: caché de IDs, invalidación para cualquier límite y revalidación de publicación.
- Blog: fases 1 y 2 del [plan específico](10-PLAN-MEJORA-BLOG.md) entregadas y verificadas.

## Siguiente trabajo del blog

1. **Fase 3 pendiente:** claridad de formularios/listados, columnas y filtros útiles, categorías y consultas eficientes. Conservar el diseño local ya integrado.
2. **Fase 4 pendiente:** revisión visual y accesibilidad completa, regresión y cierre del módulo.

No comenzar estas fases por el solo hecho de figurar aquí: requieren una tarea del usuario. Las verificaciones externas MySQL/correo/despliegue fueron descartadas como trabajo de esta sesión; se conservan como requisitos por destino, no como una tarea activa ni como resultados comprobados.

## Flujo de cada tarea

1. Leer README, AGENTS y el documento del módulo; revisar reglas aplicables si existe `.ai/rules`.
2. Comprobar Git, stack y cambios compartidos. Proponer archivos y alcance antes de editar.
3. Consultar Boost cuando esté disponible y sea pertinente; implementar en pasos pequeños.
4. Ejecutar pruebas específicas y suite completa al cerrar una fase; comprobar formato y build cuando proceda.
5. Entregar diff, archivos, migraciones, comandos, resultados y límites. No hacer commits/pushes o despliegues por iniciativa propia.

## Criterio de release

Instalación nueva y actualización verificadas, permisos efectivos, contenido y archivos protegidos, consentimiento e integraciones comprobados, suite aprobada, CI y build reproducibles, aceptación visual y de producción documentada. La existencia de una guía no prueba que se haya desplegado ni publicado `v1.0.0`.
