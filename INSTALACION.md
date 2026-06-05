# Guía de instalación — TUPA UNSAAC

Esta guía explica cómo poner en marcha el sistema en un entorno local usando **XAMPP**.

## Requisitos previos

- [XAMPP](https://www.apachefriends.org/) (incluye Apache, MariaDB/MySQL y PHP).
- Un navegador web moderno (Chrome, Firefox, Edge).

---

## Paso 1 — Iniciar Apache y MySQL

1. Abre el **Panel de control de XAMPP**.
2. Pulsa **Start** en el módulo **Apache**.
3. Pulsa **Start** en el módulo **MySQL**.
4. Ambos deben quedar en verde. Si Apache no inicia, suele deberse a que el puerto 80 está ocupado
   (Skype, IIS u otro); cámbialo en `Config → httpd.conf` o cierra el programa que lo usa.

---

## Paso 2 — Crear la base de datos `tupa_unsaac`

1. Abre `http://localhost/phpmyadmin` en el navegador.
2. Haz clic en la pestaña **Bases de datos**.
3. En "Crear base de datos" escribe **`tupa_unsaac`**.
4. Selecciona el cotejamiento **`utf8mb4_unicode_ci`**.
5. Pulsa **Crear**.

> El script SQL también crea la base de datos si no existe (`CREATE DATABASE IF NOT EXISTS`), por lo
> que este paso es opcional, pero recomendado para verificar el cotejamiento.

---

## Paso 3 — Importar el esquema `database/tupa_unsaac.sql`

1. En phpMyAdmin, selecciona la base de datos **`tupa_unsaac`** en el panel izquierdo.
2. Haz clic en la pestaña **Importar**.
3. En "Archivo a importar" pulsa **Seleccionar archivo** y elige
   `tupa-unsaac/database/tupa_unsaac.sql`.
4. Deja el formato en **SQL** y pulsa **Continuar**.
5. Deberías ver el mensaje de importación exitosa y, en el panel izquierdo, las **9 tablas**:
   `usuarios`, `dependencias`, `procedimientos`, `tramites`, `historial_estados`,
   `archivos_tramite`, `pagos`, `notificaciones`, `comentarios`.

> El script también carga **datos de prueba**: 2 dependencias, 2 procedimientos del catálogo y
> 2 usuarios (ver Paso 6).

---

## Paso 4 — Copiar el proyecto a `htdocs/`

1. Copia la carpeta completa **`tupa-unsaac/`** dentro de la carpeta `htdocs` de XAMPP.
   - Windows: normalmente `C:\xampp\htdocs\`
2. La ruta final debe quedar así: `C:\xampp\htdocs\tupa-unsaac\`.

### Verificar la conexión a la base de datos

El archivo `backend/config/database.php` usa la configuración por defecto de XAMPP:

| Parámetro | Valor por defecto |
|-----------|-------------------|
| Host | `localhost` |
| Base de datos | `tupa_unsaac` |
| Usuario | `root` |
| Contraseña | *(vacía)* |

Si tu MySQL tiene otra contraseña, edita esos valores en `backend/config/database.php`.

---

## Paso 5 — Abrir la aplicación

Abre en el navegador:

```
http://localhost/tupa-unsaac/frontend/
```

Esto carga la landing (`index.html`). Desde ahí puedes registrarte, iniciar sesión o hacer una
consulta pública.

> La raíz del proyecto incluye un `.htaccess` que redirige `http://localhost/tupa-unsaac/` hacia
> `frontend/index.html`, por lo que ambas direcciones funcionan.

---

## Paso 6 — Credenciales y datos de prueba

El script `tupa_unsaac.sql` carga datos de prueba listos para usar. Inicia sesión en
`http://localhost/tupa-unsaac/frontend/login.html` con:

| Rol | Email | Contraseña |
|-----|-------|------------|
| Administrador | `admin@unsaac.edu.pe` | `Admin123!` |
| Estudiante | `231443@unsaac.edu.pe` | `Student123!` |

El catálogo TUPA ya incluye **2 dependencias** (FIEEIM, Oficina de Servicios Académicos) y
**2 procedimientos** de ejemplo, por lo que puedes iniciar un trámite de inmediato con la cuenta de
estudiante.

### Crear más usuarios

- Desde la app: `http://localhost/tupa-unsaac/frontend/register.html` (se crean con rol `usuario`).
- Para promover un usuario a otro rol, desde phpMyAdmin (pestaña **SQL**):

```sql
-- Roles disponibles: usuario, operador, jefe, admin
UPDATE usuarios SET rol = 'admin' WHERE email = 'tu-correo@ejemplo.com';

-- Para operador/jefe, asigna además una dependencia:
UPDATE usuarios SET rol = 'operador', dependencia_id = 1 WHERE email = 'operador@ejemplo.com';
```

> **Seguridad:** estas credenciales son solo para el entorno de desarrollo local. Cámbialas o
> elimina los usuarios de prueba antes de cualquier despliegue real.

---

## Solución de problemas

| Síntoma | Causa probable / solución |
|---------|---------------------------|
| Página en blanco o error 500 | Revisa que Apache esté activo y que `backend/config/database.php` tenga las credenciales correctas. |
| "Error de conexión a la base de datos" | MySQL detenido o nombre/contraseña de BD incorrectos. |
| Las llamadas a la API devuelven 404 | El proyecto debe estar en `htdocs/tupa-unsaac/` y abrirse desde `http://localhost/tupa-unsaac/frontend/`. |
| No puedo subir archivos | Verifica permisos de escritura en `backend/uploads/` y `upload_max_filesize` en `php.ini` (≥ 10 MB). |
| El panel admin dice "Acceso denegado" | Tu usuario no tiene rol `admin/jefe/operador`. Promuévelo con el `UPDATE` del Paso 6b. |
