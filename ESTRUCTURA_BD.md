# Estructura de la base de datos — TUPA UNSAAC

Base de datos: **`tupa_unsaac`** · Motor: **InnoDB** · Cotejamiento: **`utf8mb4_unicode_ci`**

El esquema (`database/tupa_unsaac.sql`) define **9 tablas** relacionadas que modelan el ciclo de vida
de un trámite administrativo.

| # | Tabla | Propósito |
|---|-------|-----------|
| 1 | `usuarios` | Personas que acceden al sistema |
| 2 | `dependencias` | Oficinas/unidades que atienden procedimientos |
| 3 | `procedimientos` | Catálogo TUPA |
| 4 | `tramites` | Solicitudes iniciadas por usuarios |
| 5 | `historial_estados` | Bitácora de cambios de estado |
| 6 | `archivos_tramite` | Documentos adjuntos |
| 7 | `pagos` | Pagos asociados a un trámite |
| 8 | `notificaciones` | Avisos a los usuarios |
| 9 | `comentarios` | Mensajes sobre un trámite |

---

## Diagrama de relaciones (resumen)

```
dependencias 1───* procedimientos 1───* tramites *───1 usuarios
     │                                      │
     └──────* usuarios (operador/jefe)      ├───* historial_estados *───1 usuarios
                                            ├───* archivos_tramite
                                            ├───* pagos
                                            ├───* comentarios *───1 usuarios
                                            └───* notificaciones *───1 usuarios
```

---

## 1. `usuarios`

Personas que acceden al sistema: público general, comunidad UNSAAC y personal administrativo.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT PK AI | Identificador único |
| `dni` | VARCHAR(15) UNIQUE | Documento de identidad (opcional) |
| `nombre` | VARCHAR(100) NOT NULL | Nombre completo |
| `email` | VARCHAR(150) UNIQUE NOT NULL | Correo, usado para iniciar sesión |
| `password` | VARCHAR(255) NOT NULL | Hash de la contraseña (`password_hash`) |
| `rol` | ENUM | `admin`, `jefe`, `operador`, `usuario` (def. `usuario`) |
| `dependencia_id` | INT NULL FK | Oficina asignada (para operador/jefe) → `dependencias.id` |
| `activo` | TINYINT(1) | 1 = activo, 0 = inhabilitado |
| `created_at` | TIMESTAMP | Fecha de creación |
| `updated_at` | TIMESTAMP | Última actualización |

**Relaciones:** `dependencia_id` → `dependencias.id` (`ON DELETE SET NULL`).

---

## 2. `dependencias`

Oficinas o unidades de la universidad que atienden procedimientos.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT PK AI | Identificador único |
| `nombre` | VARCHAR(200) NOT NULL | Nombre de la dependencia |
| `codigo` | VARCHAR(20) UNIQUE | Código corto |
| `descripcion` | TEXT | Descripción |
| `activo` | TINYINT(1) | Estado |
| `created_at` | TIMESTAMP | Fecha de creación |

**Relaciones:** referenciada por `procedimientos.dependencia_id` y `usuarios.dependencia_id`.

---

## 3. `procedimientos`

Catálogo TUPA: cada trámite que la universidad ofrece, con sus requisitos, costo y plazo.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT PK AI | Identificador único |
| `codigo` | VARCHAR(20) UNIQUE NOT NULL | Código del procedimiento |
| `nombre` | VARCHAR(300) NOT NULL | Nombre del trámite |
| `descripcion` | TEXT | Descripción |
| `requisitos` | TEXT | Requisitos (texto multilínea) |
| `dependencia_id` | INT FK | Oficina responsable → `dependencias.id` |
| `costo` | DECIMAL(10,2) | Costo en soles (def. 0.00) |
| `plazo_dias` | INT | Plazo de atención en días (def. 30) |
| `base_legal` | TEXT | Sustento normativo |
| `activo` | TINYINT(1) | Estado |
| `created_at` / `updated_at` | TIMESTAMP | Auditoría |

**Relaciones:** `dependencia_id` → `dependencias.id` (`ON DELETE SET NULL`).

---

## 4. `tramites`

Solicitud concreta iniciada por un usuario sobre un procedimiento.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT PK AI | Identificador único |
| `numero_expediente` | VARCHAR(30) UNIQUE | Código `EXP-AÑO-NNNNN` |
| `usuario_id` | INT NOT NULL FK | Solicitante → `usuarios.id` |
| `procedimiento_id` | INT NOT NULL FK | Procedimiento → `procedimientos.id` |
| `estado` | ENUM | `pendiente`, `en_proceso`, `observado`, `aprobado`, `rechazado` |
| `observaciones` | TEXT | Comentarios acumulados |
| `fecha_inicio` | TIMESTAMP | Inicio del trámite |
| `fecha_fin` | TIMESTAMP NULL | Cierre (al aprobar/rechazar) |

**Relaciones:**
- `usuario_id` → `usuarios.id` (`ON DELETE CASCADE`).
- `procedimiento_id` → `procedimientos.id` (`ON DELETE CASCADE`).

---

## 5. `historial_estados`

Bitácora de cada cambio de estado de un trámite (alimenta la línea de tiempo).

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT PK AI | Identificador único |
| `tramite_id` | INT NOT NULL FK | Trámite → `tramites.id` |
| `estado_anterior` | ENUM | Estado previo (nulo en el primer registro) |
| `estado_nuevo` | ENUM NOT NULL | Estado resultante |
| `comentario` | TEXT | Comentario del responsable |
| `usuario_id` | INT NULL FK | Quién realizó el cambio → `usuarios.id` |
| `created_at` | TIMESTAMP | Momento del cambio |

**Relaciones:**
- `tramite_id` → `tramites.id` (`ON DELETE CASCADE`).
- `usuario_id` → `usuarios.id` (`ON DELETE SET NULL`).

---

## 6. `archivos_tramite`

Documentos adjuntos (PDF/JPG/PNG, máx. 10 MB) de cada trámite.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT PK AI | Identificador único |
| `tramite_id` | INT NOT NULL FK | Trámite → `tramites.id` |
| `nombre_original` | VARCHAR(255) NOT NULL | Nombre original del archivo |
| `nombre_archivo` | VARCHAR(255) NOT NULL | Nombre con que se guarda en disco |
| `tipo_mime` | VARCHAR(100) NOT NULL | Tipo MIME (`application/pdf`, etc.) |
| `tamanio` | INT NOT NULL | Tamaño en bytes |
| `created_at` | TIMESTAMP | Fecha de subida |

**Relaciones:** `tramite_id` → `tramites.id` (`ON DELETE CASCADE`).

---

## 7. `pagos`

Pagos registrados para un trámite (tasa del procedimiento).

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT PK AI | Identificador único |
| `tramite_id` | INT NOT NULL FK | Trámite → `tramites.id` |
| `monto` | DECIMAL(10,2) NOT NULL | Monto pagado en soles |
| `metodo` | ENUM | `efectivo`, `transferencia`, `tarjeta` (def. `efectivo`) |
| `numero_operacion` | VARCHAR(50) | Número de operación/voucher |
| `estado` | ENUM | `pendiente`, `pagado`, `anulado` (def. `pendiente`) |
| `fecha_pago` | TIMESTAMP NULL | Momento de confirmación del pago |
| `created_at` | TIMESTAMP | Fecha de registro |

**Relaciones:** `tramite_id` → `tramites.id` (`ON DELETE CASCADE`).

---

## 8. `notificaciones`

Avisos dirigidos a un usuario (p. ej. cambio de estado de su trámite).

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT PK AI | Identificador único |
| `usuario_id` | INT NOT NULL FK | Destinatario → `usuarios.id` |
| `tramite_id` | INT NULL FK | Trámite relacionado → `tramites.id` (opcional) |
| `titulo` | VARCHAR(150) NOT NULL | Título del aviso |
| `mensaje` | TEXT | Contenido |
| `leida` | TINYINT(1) | 1 = leída, 0 = no leída (def. 0) |
| `created_at` | TIMESTAMP | Fecha de creación |

**Relaciones:**
- `usuario_id` → `usuarios.id` (`ON DELETE CASCADE`).
- `tramite_id` → `tramites.id` (`ON DELETE CASCADE`).

---

## 9. `comentarios`

Mensajes asociados a un trámite, entre el solicitante y el personal que lo atiende.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT PK AI | Identificador único |
| `tramite_id` | INT NOT NULL FK | Trámite → `tramites.id` |
| `usuario_id` | INT NULL FK | Autor → `usuarios.id` |
| `mensaje` | TEXT NOT NULL | Texto del comentario |
| `interno` | TINYINT(1) | 1 = visible solo para staff, 0 = visible al solicitante (def. 0) |
| `created_at` | TIMESTAMP | Fecha del comentario |

**Relaciones:**
- `tramite_id` → `tramites.id` (`ON DELETE CASCADE`).
- `usuario_id` → `usuarios.id` (`ON DELETE SET NULL`).

---

## Índices definidos

Para optimizar las consultas más frecuentes:

| Índice | Tabla(columna) |
|--------|----------------|
| `idx_usuarios_email` | `usuarios(email)` |
| `idx_usuarios_dni` | `usuarios(dni)` |
| `idx_usuarios_dependencia` | `usuarios(dependencia_id)` |
| `idx_tramites_usuario` | `tramites(usuario_id)` |
| `idx_tramites_estado` | `tramites(estado)` |
| `idx_tramites_expediente` | `tramites(numero_expediente)` |
| `idx_procedimientos_dependencia` | `procedimientos(dependencia_id)` |
| `idx_historial_tramite` | `historial_estados(tramite_id)` |
| `idx_archivos_tramite` | `archivos_tramite(tramite_id)` |
| `idx_pagos_tramite` | `pagos(tramite_id)` |
| `idx_notificaciones_usuario` | `notificaciones(usuario_id)` |
| `idx_comentarios_tramite` | `comentarios(tramite_id)` |

---

## Flujo de estados de un trámite

```
pendiente ──► en_proceso ──► aprobado
    │              │     └───► rechazado
    │              └───► observado ──► en_proceso / aprobado / rechazado
    ├──► observado
    ├──► aprobado
    └──► rechazado
```

Cada transición queda registrada en `historial_estados`. Los estados `aprobado` y `rechazado` son
finales y fijan `tramites.fecha_fin`.
