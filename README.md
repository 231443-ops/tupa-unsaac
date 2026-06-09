# Optimización del Sistema TUPA UNSAAC

Propuesta de **optimización del sistema de trámite documentario** de la Universidad Nacional de
San Antonio Abad del Cusco (UNSAAC). No partimos de cero: la universidad ya cuenta con el sistema
**Pladdes v2.0.1** ([tramite.unsaac.edu.pe](https://tramite.unsaac.edu.pe)), y este proyecto
identifica sus deficiencias reales y las resuelve en una plataforma mejorada.

## El problema: deficiencias del sistema actual (Pladdes v2.0.1)

Tras analizar el sistema en producción, se identificaron las siguientes deficiencias:

| # | Deficiencia | Impacto |
|---|-------------|---------|
| 1 | El usuario aparece como **"Invitado"**, sin un login real | No hay identidad ni historial del solicitante |
| 2 | **Bug**: los campos Facultad y Carrera muestran `undefined` | Datos corruptos en la solicitud y desconfianza del usuario |
| 3 | **3 plataformas desconectadas** (trámites, pagos y reclamos) | El usuario debe navegar entre sistemas distintos para un solo procedimiento |
| 4 | **Sin buscador de texto libre**: solo un dropdown de procedimientos | Encontrar un trámite exige conocer su nombre exacto |
| 5 | **Sin seguimiento visual** del trámite | El solicitante no sabe en qué etapa está su expediente |
| 6 | **Sin notificaciones automáticas** | El usuario debe consultar manualmente si hubo avances |
| 7 | Campo de petición **limitado a 180 caracteres** | Imposible describir adecuadamente la solicitud |
| 8 | Acepta archivos **RAR/ZIP** (inseguro) | Riesgo de carga de contenido malicioso sin inspección |
| 9 | **Diseño móvil deficiente** | Mala experiencia en celulares, el dispositivo más usado por estudiantes |

## La solución: nuestra plataforma

Cada deficiencia detectada tiene una solución directa en esta plataforma:

| Deficiencia de Pladdes | Solución implementada |
|------------------------|----------------------|
| Usuario "Invitado" sin login | **Autenticación real** con registro, sesiones PHP y roles (usuario, operador, jefe, administrador) |
| Facultad/Carrera `undefined` | **Validación de datos** en frontend y backend; el perfil del usuario mantiene sus datos consistentes |
| 3 plataformas desconectadas | **Plataforma unificada**: catálogo TUPA, inicio de trámite, adjuntos y seguimiento en un solo sistema |
| Solo dropdown de procedimientos | **Buscador de texto libre** sobre el catálogo TUPA, con filtros por dependencia |
| Sin seguimiento visual | **Línea de tiempo** del expediente con historial de estados (pendiente → en proceso → observado → aprobado/rechazado) |
| Sin notificaciones | **Notificaciones automáticas** ante cada cambio de estado del trámite |
| Petición limitada a 180 caracteres | Campo de descripción **sin límite restrictivo**, adecuado para detallar la solicitud |
| Acepta RAR/ZIP | Solo se aceptan **PDF/JPG/PNG**, con validación de tipo y tamaño en el servidor |
| Diseño móvil deficiente | **Diseño responsive** (mobile-first) con sistema de diseño propio |

Además, incluye **consulta pública** del expediente (número `EXP-AÑO-NNNNN` + DNI, sin iniciar
sesión) y un **panel de administración** con bandeja de entrada, procesamiento de trámites y
reportes estadísticos.

## Stack tecnológico

| Capa | Tecnología |
|------|------------|
| Backend | PHP (PDO, sesiones, API REST con respuestas JSON) |
| Base de datos | MariaDB / MySQL |
| Frontend | HTML5, CSS3 (sistema de diseño propio), JavaScript (vanilla, sin frameworks) |
| Gráficos | Chart.js (vía CDN) |
| Entorno | XAMPP (Apache + MariaDB) |

## Estructura del proyecto

```
tupa-unsaac/
├── backend/
│   ├── admin/                  # Bandeja, procesar y reportes (roles admin/jefe/operador)
│   ├── auth/                   # login, logout, register, session
│   ├── config/                 # Conexión a base de datos (PDO)
│   ├── helpers/                # cors, response, auth, validator
│   ├── tramites/               # crear, listar, detalle, subir-archivo, consulta-publica
│   ├── tupa/                   # categorias (dependencias), procedimientos
│   ├── usuarios/               # perfil, actualizar
│   └── uploads/                # Archivos adjuntos subidos (no versionado)
├── database/
│   └── tupa_unsaac.sql         # Esquema completo + datos de prueba
├── frontend/
│   ├── admin/                  # bandeja.html, procesar.html, reportes.html
│   ├── css/                    # styles.css (sistema de diseño)
│   ├── js/                     # api.js, main.js, admin.js
│   ├── index.html              # Landing
│   ├── login.html              # Inicio de sesión
│   ├── register.html           # Registro
│   ├── consulta.html           # Consulta pública de expedientes
│   ├── dashboard.html          # Panel del usuario
│   ├── mis-tramites.html       # Lista de trámites del usuario
│   ├── detalle.html            # Detalle de un trámite
│   ├── tramite-nuevo.html      # Asistente para iniciar un trámite
│   └── perfil.html             # Datos del usuario
├── README.md
├── INSTALACION.md              # Guía de instalación detallada con XAMPP
├── ESTRUCTURA_BD.md            # Descripción de las tablas y relaciones
├── .gitignore
└── .htaccess                   # Redirección a frontend/index.html
```

## Instalación rápida

1. Instala [XAMPP](https://www.apachefriends.org/) e inicia **Apache** y **MySQL**.
2. Copia la carpeta `tupa-unsaac/` dentro de `C:/xampp/htdocs/`.
3. En phpMyAdmin (`http://localhost/phpmyadmin`) crea la base de datos `tupa_unsaac` e importa
   `database/tupa_unsaac.sql`.
4. Abre `http://localhost/tupa-unsaac/frontend/` en el navegador.

Credenciales de prueba (incluidas en el script SQL):

| Rol | Email | Contraseña |
|-----|-------|------------|
| Administrador | `admin@unsaac.edu.pe` | `Admin123!` |
| Estudiante | `231443@unsaac.edu.pe` | `Student123!` |

> La guía paso a paso está en **[INSTALACION.md](INSTALACION.md)**.

## Integrantes

| Nombre | Código |
|--------|--------|
| Gerald Benjamín Huanto Ayma | 231443 |
| Andree Achahuanco Valenza | 204792 |
| Jose Ramiro Palomino Auquitayasi | 154636 |

---

Universidad Nacional de San Antonio Abad del Cusco (UNSAAC)
