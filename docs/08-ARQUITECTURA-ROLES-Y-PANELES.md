# Roles, permisos y paneles

Actualizado: 14 de octubre de 2026. Implementación nativa con `UserRole`, `Permission`, `RolePermission`, `UserPolicy` y las Policies de cada módulo. No hay Spatie Permission ni multi-tenancy.

## Roles efectivos

| Rol funcional | Valores almacenados | Acceso y límites |
| --- | --- | --- |
| Superadministrador | `super_admin` | Administración completa con cuenta designada; no modifica su propia identidad protegida desde el CRUD |
| Administrador | `admin`, alias `administrator` | Panel administrativo según plantilla configurable y permisos individuales; no eleva sus propios permisos ni concede permisos que no posee |
| Editor | `editor` | Contenido según catálogo definido en `UserRole::editorPermissions()`; sin ajustes críticos, usuarios o SMTP |
| Usuario | `user` | Solo panel opcional habilitado; denegado en administración |

`User::hasPermission()` combina estado activo, rol y concesiones individuales. La plantilla administrativa puede conceder o denegar permisos explícitamente; no debe sustituirse por `isAdmin()` ni por `UserRole::allows()` para autorizar acciones del panel.

El editor puede crear/editar contenido y medios, pero no eliminar artículos o medios. Portafolio permite al editor también eliminar proyectos/categorías según el catálogo actual. Las restricciones por relaciones siguen aplicándose. No describir a todos los editores como usuarios con idénticas acciones en todos los módulos.

## Cuenta superadministradora

La identidad fija es `User::SUPER_ADMIN_EMAIL`, actualmente `alejandrones.tech@gmail.com`, según la decisión del proyecto. No existe contraseña predeterminada ni se crea automáticamente una cuenta por instalar el código.

`User` protege rol, correo, activación y eliminación de esa cuenta; `UserBuilder` limita modificaciones/borrados masivos de Eloquent, y `UserPolicy` impide administrarla como una cuenta ordinaria. Estas protecciones de aplicación no deben presentarse como garantía frente a SQL directo o acceso irrestricto a la base.

Aprovisionamiento explícito por el operador:

```sh
php artisan app:setup-super-admin
```

Si la cuenta existe, el comando conserva su contraseña y la vincula/activa con el rol designado; si no existe, solicita datos y contraseña segura. Por ello, ejecutarlo sobre una cuenta existente modifica privilegios y requiere la autorización correspondiente. Preferir entrada interactiva oculta; la opción `--password` todavía existe y puede exponer el secreto en historial/procesos. No incluirla en instrucciones de despliegue automatizado.

Para un administrador ordinario existe `php artisan app:create-admin`. No sustituye la cuenta superadministradora requerida para gestionar la plantilla de permisos de administradores.

## Administración de cuentas y permisos

- `UserResource`: búsqueda, rol, estado, creación, edición, contraseña y permisos individuales, sujetos a Policy y validaciones de servidor.
- `RolesAndPermissions`: consulta según acceso a usuarios; guardar plantilla administrativa requiere superadministrador activo y revisión de conflictos.
- Solo se pueden conceder permisos propios y no se permite autoescalamiento. La cuenta superadministradora queda protegida frente al CRUD ordinario.
- La desactivación se aplica mediante `EnsureActiveAccount` en solicitudes protegidas; no se promete cerrar visualmente de inmediato una pestaña que no realiza peticiones.
- El blog vuelve a consultar al actor al guardar/eliminar y protege formularios obsoletos. Las acciones personalizadas de otros módulos conservan sus propias comprobaciones.

La seguridad se verifica también por URL/acción directa, no solo por menús o botones ocultos. Las columnas editables y acciones personalizadas requieren autorización explícita.

## Paneles

`/admin`: acceso de superadministrador, administrador o editor activos. Incluye grupos Blog y Noticias, Portafolio, Sitio Corporativo, Comunicación, SEO y Analítica, Configuración y Sistema.

`/panel`: panel básico para `user`, desactivado por defecto mediante `CLIENT_PANEL_ENABLED=false`. El middleware devuelve 404 también para login y recuperación cuando está desactivado. No incluye CRM, reservas ni funcionalidades de negocio implícitas.

Para una instalación que lo requiera, el operador configura `CLIENT_PANEL_ENABLED=true` y reconstruye la caché de configuración si la utiliza. Verificar login, perfil, recuperación y separación de roles. Habilitar una ruta no concede permisos por sí mismo.

## Instalación y actualización

Usar [04 · Despliegue](04-DESPLIEGUE.md) como procedimiento único de instalación. `DatabaseSeeder` llama a `RolesAndPermissionsSeeder`: sincroniza el catálogo y asignaciones iniciales y conserva una plantilla administrativa ya existente. No crea usuarios con credenciales predeterminadas.

Las migraciones y seeders sobre una base real son acciones del operador. Revisar el diff antes de ejecutarlos; no usar `migrate:fresh`, no regenerar claves ni sobrescribir permisos de una instalación para resolver un problema de acceso.

## Verificación y pendientes

Pruebas de referencia: `FilamentUserAndPermissionManagementTest`, `ClientPanelAndAuthenticationAuditTest`, `AdministrationTest`, `PanelNavigationTest` y las pruebas de revocación del blog. Resultados globales: [05 · Auditoría](05-AUDITORIA-FINAL.md).

La entrega real del correo de recuperación y el despliegue en un hosting específico siguen sin certificarse en esta sesión. La configuración y las pruebas aisladas no demuestran entrega al buzón.
