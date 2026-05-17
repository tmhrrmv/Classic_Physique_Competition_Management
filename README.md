Classic Physique Competition Management
Sistema de gestión de competiciones de Classic Physique. Permite gestionar atletas, competiciones, inscripciones, puntuaciones y resultados a través de un backoffice web protegido por autenticación.

Tecnologías
CapaTecnologíaServidorRailway + FrankenPHPBackendPHP 8.4Base de datosMySQL 8.0 (Railway)FrontendPHP + HTML + CSS + JavaScriptAutenticaciónJWT + Sesiones PHP en MySQL

Estructura del proyecto
/
├── index.php                  ← Router principal
├── Caddyfile                  ← Configuración FrankenPHP
├── php.ini
│
├── backend/
│   ├── config.php             ← Variables de entorno y constantes
│   ├── helpers.php            ← Funciones compartidas
│   ├── clases/
│   │   ├── ConexionDB.php     ← Patrón Singleton para PDO
│   │   └── DbSessionHandler.php ← Sesiones PHP en MySQL
│   ├── config/
│   │   └── db.php             ← Conexión a la BD
│   ├── middleware/
│   │   ├── auth.php           ← Generación y verificación JWT
│   │   └── roles.php          ← Control de roles
│   └── api/
│       ├── auth.php           ← Login y gestión de sesión
│       ├── atletas.php        ← CRUD atletas e inscripciones
│       ├── competiciones.php  ← CRUD competiciones
│       ├── inscripciones.php  ← Listado de inscripciones
│       ├── puntuaciones.php   ← Registro y anulación de puntuaciones
│       ├── resultados.php     ← Cálculo y consulta de resultados
│       └── categorias.php     ← Listado de categorías
│
├── frontend/
│   ├── index.php              ← Redirección inicial
│   ├── includes/
│   │   ├── auth_check.php     ← Verificación de sesión PHP
│   │   └── set_session.php    ← Guardado de sesión tras login
│   ├── pages/
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── dashboard.php
│   │   ├── competiciones.php
│   │   ├── atletas.php
│   │   ├── inscripciones.php
│   │   ├── puntuaciones.php
│   │   └── resultados.php
│   ├── css/
│   │   └── theme.css
│   └── js/
│       ├── api.js
│       └── auth.js
│
└── database/
    ├── schema/
    │   ├── 01_create_tables.sql
    │   ├── 02_triggers.sql
    │   ├── 03_functions.sql
    │   ├── 04_procedures.sql
    │   └── 05_users_roles.sql
    ├── data/
    │   └── 06_insert_data.sql
    └── schema_completo.sql

Base de datos
Tablas principales
TablaDescripcióncategoriaCadete, Juvenil, Senior con rangos de edad, altura y pesocompeticionEventos con nombre, fecha y lugaratletaCompetidores con datos personalesjuezJueces con licenciainscripcionRelación atleta-competición con datos físicos del momentopuntuacionRankings otorgados por cada juez a cada atletaresultado_finalPodio calculado por competición y categoríausuariosCuentas de acceso al backofficesesionesSesiones PHP almacenadas en BD (multi-worker)log_procedimientosAuditoría de operaciones críticas
Procedimientos almacenados
ProcedimientoDescripciónsp_inscribir_atletaCrea o reutiliza atleta y genera inscripción con validacionessp_registrar_puntuacionRegistra el ranking de un juez para un atletasp_calcular_resultadosCalcula el podio aplicando descarte de extremossp_anular_puntuacionElimina una puntuación y recalcula resultados
Funciones
FunciónDescripciónfn_estado_competicionCalcula el estado (abierta/en_curso/cerrada) según la fechafn_edad_atletaCalcula la edad del atleta en la fecha del eventofn_categoria_valida_para_edadValida que la edad corresponde a la categoría

Roles de usuario
RolAccesoadminAcceso completo a todo el backofficeorganizadorGestión de competiciones e inscripcionesjuezSolo puede ver y registrar sus propias puntuacionesconsulta_publicaSolo lectura de resultados

Instalación y despliegue
Requisitos

PHP 8.2+
MySQL 8.0+
Railway (o servidor con FrankenPHP)

Variables de entorno
Crea un archivo .env en la raíz con:
envDB_HOST=mysql.railway.internal
DB_PORT=3306
DB_NAME=railway
DB_USER=root
DB_PASS=tu_password
JWT_SECRET=tu_secreto_aleatorio_de_64_caracteres
APP_ENV=production
APP_URL=https://tu-dominio.up.railway.app
ALLOWED_ORIGIN=*
TRUSTED_PROXIES=127.0.0.1,::1
Inicializar la base de datos
Ejecuta en Workbench o desde terminal:
bashmysql -h HOST -P PORT -u root -p < database/schema_completo.sql
Despliegue en Railway

Conecta el repositorio en Railway → New Service → GitHub
Configura las variables de entorno en la pestaña Variables
Railway despliega automáticamente al hacer push a main

Endpoints principales de la API
POST   /api/auth.php                         Login
GET    /api/competiciones.php                Lista de competiciones
POST   /api/competiciones.php                Crear competición
PATCH  /api/competiciones.php?id=X           Editar competición
DELETE /api/competiciones.php?id=X           Eliminar competición
GET    /api/atletas.php                      Lista de atletas
POST   /api/atletas.php                      Inscribir atleta
GET    /api/inscripciones.php                Lista de inscripciones
GET    /api/puntuaciones.php?id_competicion=X  Puntuaciones de un evento
POST   /api/puntuaciones.php                 Registrar puntuación
DELETE /api/puntuaciones.php?id=X            Anular puntuación
GET    /api/resultados.php?id_competicion=X  Resultados de un evento
POST   /api/resultados.php                   Calcular resultados
GET    /api/categorias.php                   Lista de categorías

Seguridad

Contraseñas almacenadas con password_hash (bcrypt)
JWT firmado con HMAC-SHA256 y Base64 URL-safe
Bloqueo de cuenta tras 5 intentos fallidos (15 minutos)
Sesiones PHP almacenadas en MySQL para entornos multi-worker
Validación de Content-Type, longitudes y formatos en todos los endpoints
Roles verificados en cada endpoint via middleware
Logs de auditoría en log_procedimientos
Limpieza automática de logs antiguos cada 90 días
