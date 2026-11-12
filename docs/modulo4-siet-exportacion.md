# Nomaddi Panel Central — Módulo 4: Formulario SIET + Exportación XLS

## Archivos de este módulo

```
database/migrations/
  009_create_siet_support_tables.php    → municipios_dane + logs_desbloqueo_siet

app/Models/
  MunicipioDane.php
  LogDesbloqueoSiet.php

app/Services/
  SietService.php                       → completar(), exportarParaSiet()
  MoodleService_additions_mod4.php      → activarCampoSiet(), consultarCompletitud()

app/Http/
  Requests/CompletarSietRequest.php     → 19 campos validados
  Controllers/Api/SietController.php    → estado, completar, municipios, exportar

app/Jobs/
  SincronizarCompletitudMoodle.php      → Job horario para detectar cursos completados

routes/
  api.php                               → rutas SIET
  console.php                           → scheduler del Job
```

---

## Instalación

### 1. Instalar PhpSpreadsheet (para el XLS)
```bash
composer require phpoffice/phpspreadsheet
```

### 2. Ejecutar migraciones
```bash
php artisan migrate
```

### 3. Poblar municipios DANE
```bash
# Descargar el CSV de municipios del DANE y correr el seeder:
php artisan db:seed --class=MunicipiosDaneSeeder
```
El seeder lee el archivo `database/seeders/data/municipios_dane.csv`
(1.122 municipios con código DANE, departamento y nombre).
Puedes descargarlo de: https://www.dane.gov.co/files/censo2018/divipola/

### 4. Configurar campo de perfil en Moodle
En Moodle → Administración → Usuarios → Perfil de usuario → Crear campo:
- Tipo: **Casilla de verificación** (checkbox)
- Nombre: `siet_completado`
- Nombre corto: `siet_completado`
- ¿Quién puede ver este campo?: Solo administradores

### 5. Configurar restricción de disponibilidad en el curso 2
En Moodle → curso 2 → Configuración → Restricciones de acceso → Agregar restricción:
- Tipo: **Campo de perfil**
- Campo: `siet_completado`
- Operador: **igual a**
- Valor: `1`

Esto bloquea el acceso al curso 2 hasta que el panel active el campo.

### 6. Agregar core_user_update_users al token de Moodle
Moodle → Administración → Plugins → Servicios externos → tu token → agregar función

### 7. Activar el scheduler de Laravel
En el crontab del VPS:
```bash
crontab -e
# Agregar:
* * * * * cd /ruta/al/panel && php artisan schedule:run >> /dev/null 2>&1
```

---

## Flujo completo del módulo 4

```
1. Estudiante completa el curso 1 en Moodle
2. Job SincronizarCompletitudMoodle (cada hora) detecta la completitud
3. Panel marca la matrícula como COMPLETADA
4. Estudiante entra a su panel y ve el formulario SIET disponible
5. Llena los 19 campos y envía:
   POST /api/siet/estudiante/{id}  { fecha_nacimiento, municipio_dane, ... }
6. Panel valida y guarda en estudiantes_siet
7. Panel llama core_user_update_users → activa campo siet_completado=1
8. Moodle desbloquea automáticamente el curso 2
9. Estudiante puede continuar su carrera
```

## Exportación SIET

```
GET /api/siet/exportar/{ietdh_id}?desde=2026-01-01&hasta=2026-01-31
Authorization: Bearer {token_nomaddi_admin}

→ Descarga: SIET_830123456_2026-01-01_2026-01-31.xls
→ Formato exacto de la plantilla SIET (23 columnas, fila 1=bloques, fila 2=headers)
→ La IETDH lo sube directamente al portal del SIET
```

---

## Próximo módulo

**Módulo 5 — Certificados, actas y folios**
Emisión automática del certificado al completar el último curso,
generación del número de acta (T/B + año + mes),
consecutivo de folio por IETDH y mes,
código UUID de verificación,
integración con Custom Certificate de Moodle.
