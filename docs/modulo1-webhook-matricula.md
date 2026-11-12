# Nomaddi Panel Central — Módulo 1: Webhook + Matrícula

## Archivos incluidos en este paquete

```
database/migrations/
  001_create_distribuidores_table.php
  002_create_cursos_table.php
  003_create_precios_table.php
  004_create_estudiantes_table.php   ← incluye estudiantes_siet
  005_create_matriculas_table.php    ← incluye logs_webhook

app/Models/
  Distribuidor.php
  Curso.php
  Precio.php
  Estudiante.php
  EstudianteSiet.php
  Matricula.php
  LogWebhook.php

app/Services/
  EpaycoService.php    ← valida firma, extrae datos
  MoodleService.php    ← wrapper API REST de Moodle
  MatriculaService.php ← orquesta el flujo completo

app/Http/Controllers/Api/
  WebhookController.php

routes/api.php         ← ruta POST /api/webhook/epayco
config/services.php    ← configuración moodle y epayco
.env.example           ← variables necesarias
```

---

## Instalación en el VPS de Hostinger

### 1. Crear el proyecto Laravel

```bash
composer create-project laravel/laravel nomaddi-panel
cd nomaddi-panel
```

### 2. Copiar los archivos de este paquete

Copiar cada archivo a su ruta correspondiente dentro del proyecto Laravel.

### 3. Configurar el entorno

```bash
cp .env.example .env
php artisan key:generate
```

Editar `.env` con:
- Credenciales de PostgreSQL
- URL y token de Moodle
- Credenciales de ePayco

### 4. Crear la base de datos

```bash
# En PostgreSQL
createdb nomaddi_panel
```

### 5. Ejecutar migraciones

```bash
php artisan migrate
```

### 6. Registrar los servicios en el contenedor de Laravel

En `app/Providers/AppServiceProvider.php` → método `register()`:

```php
$this->app->singleton(\App\Services\MoodleService::class);
$this->app->singleton(\App\Services\EpaycoService::class);
$this->app->singleton(\App\Services\MatriculaService::class);
```

### 7. Configurar Moodle para el webservice

En Moodle (campusvirtual.edu.co):
1. Administración → Plugins → Servicios web → Activar servicios web ✓
2. Administración → Plugins → Protocolos → Activar REST ✓
3. Administración → Plugins → Usuarios externos → Crear token
4. Funciones que debe tener el token:
   - `core_user_create_users`
   - `core_user_get_users`
   - `enrol_manual_enrol_users`
   - `cohort_add_cohort_members`
   - `groups_add_group_member`
   - `core_completion_get_activities_completion_status`
5. Copiar el token generado al `.env` como `MOODLE_TOKEN`

### 8. Configurar el webhook en ePayco

En el panel de ePayco:
- URL de confirmación: `https://panel.campusvirtual.edu.co/api/webhook/epayco`
- Método: POST

### 9. Datos que debe enviar WooCommerce a ePayco

Cada tienda distribuidora debe configurar en su plugin de ePayco los campos extra:

| Campo ePayco | Contenido |
|---|---|
| `p_attr_1` | Tipo de documento (CC, TI, CE...) |
| `p_attr_2` | Número de documento |
| `p_attr_3` | Apellidos del estudiante |
| `p_attr_4` | `moodle_course_id` del curso comprado |
| `p_attr_5` | `id` del distribuidor en el panel Nomaddi |

---

## Flujo del webhook paso a paso

```
POST /api/webhook/epayco
  ↓
LogWebhook (estado: RECIBIDO)
  ↓
¿Firma válida? → NO → LogWebhook (RECHAZADO: firma inválida) → 200 OK
  ↓ SÍ
¿Pago aprobado? → NO → LogWebhook (RECHAZADO: no aprobado) → 200 OK
  ↓ SÍ
Extraer datos (EpaycoService)
  ↓
Buscar Distribuidor + Curso
  ↓
¿Monto >= precio mínimo? → NO → LogWebhook (RECHAZADO: precio bajo) → 200 OK
  ↓ SÍ
[DB Transaction]
  Crear/recuperar Estudiante
  Crear/recuperar usuario Moodle
  Matricular en curso Moodle
  Agregar a cohorte del distribuidor
  Agregar a grupo del distribuidor en el curso
  Crear Matricula en BD
[End Transaction]
  ↓
LogWebhook (estado: MATRICULADO) → 200 OK
```

---

## Próximos módulos a desarrollar

- [ ] Módulo 2: CRUD Distribuidores + IETDHs (con tabla `distribuidor_ietdh` y `distribuidor_grupos_cursos`)
- [ ] Módulo 3: Autenticación del panel (Nomaddi admin)
- [ ] Módulo 4: Formulario SIET post-curso 1
- [ ] Módulo 5: Certificados + actas + folios
- [ ] Módulo 6: Liquidaciones mensuales IETDHs
- [ ] Módulo 7: Reportes semanales/mensuales a distribuidores
- [ ] Módulo 8: Dashboard en tiempo real
- [ ] Módulo 9: Validación pública de certificados
