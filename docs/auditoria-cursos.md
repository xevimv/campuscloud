# Nomaddi Panel Central — Auditoría Automática de Cursos Moodle

## Qué revisa

Por cada curso activo registrado en el panel, el sistema verifica 7 criterios
contra la API REST de Moodle:

| # | Criterio | Impacto si falla |
|---|----------|-----------------|
| 1 | **Título correcto** | 🔴 REPROBADO — título en Moodle no coincide con el esperado |
| 2 | **Bloque Completion Progress** | 🟡 ADVERTENCIA — estudiantes no ven su progreso |
| 3 | **Módulos completos** | 🔴 REPROBADO — tiene menos secciones con contenido que las esperadas |
| 4 | **Exámenes completos** | 🔴 REPROBADO — tiene menos cuestionarios (quiz) que los esperados |
| 5 | **Certificado configurado** | 🟡 ADVERTENCIA — no tiene actividad de certificado (customcert) |
| 6 | **Sin enlaces rotos** | 🟡 ADVERTENCIA — algún recurso URL devuelve error HTTP 4xx/5xx |
| 7 | **Calificación configurada** | 🟡 ADVERTENCIA — no tiene ítems de calificación definidos |

**Resultado global:**
- 🟢 **APROBADO** — todos los checks en verde
- 🟡 **ADVERTENCIA** — algún check importante falló (no bloquea el aprendizaje)
- 🔴 **REPROBADO** — algún check crítico falló (título, módulos o exámenes)

---

## Archivos de este módulo

```
database/migrations/
  011_add_auditoria_to_cursos.php     → 3 columnas en cursos + tabla auditorias_cursos

app/Models/
  AuditoriaCurso.php                  → con atributo semaforos

app/Services/
  AuditoriaService.php                → los 7 checks + resultado global
  MoodleService_additions_auditoria   → obtenerContenidoCurso, obtenerBloques, obtenerItemsCalificacion

app/Http/Controllers/Api/
  AuditoriaController.php

app/auditoria/page.tsx                → Dashboard Next.js con semáforos por curso

routes/api.php                        → 4 rutas bajo /auditoria

PARCHE_Curso_model.php                → relaciones ultimaAuditoria y auditorias
```

---

## Instalación

### 1. Ejecutar la migración
```bash
php artisan migrate
```

### 2. Aplicar el parche al modelo Curso
Ver `PARCHE_Curso_model.php` — agregar dos métodos y tres campos al $fillable.

### 3. Agregar funciones al token de Moodle
Administración → Plugins → Servicios externos → tu token:
- `core_course_get_contents`
- `core_block_get_course_blocks`
- `core_grades_get_gradeitems`

### 4. Agregar al Sidebar de Next.js
```tsx
import { ShieldCheck } from 'lucide-react'
{ href: '/auditoria', label: 'Auditoría', icon: ShieldCheck },
```

---

## Cómo usar

### Primera vez
1. Ve a la página /auditoria del panel
2. Configura los criterios de cada curso (rueda dentada ⚙️):
   - Cuántos módulos esperas
   - Cuántos exámenes esperas
3. Clic en "Auditar todos los cursos" — tarda ~1 minuto por cada 10 cursos
4. Revisa los resultados con semáforos

### Mantenimiento
- Audita un solo curso después de hacer cambios en Moodle (botón 🔄 por fila)
- Expande el detalle (▼) para ver exactamente qué falló y por qué
- El historial queda guardado en `auditorias_cursos`

---

## Flujo de datos

```
Panel → GET /api/auditoria
  → para cada curso activo: última entrada en auditorias_cursos
  → resumen global: aprobados / advertencias / reprobados

Panel → POST /api/auditoria/curso/{id}
  → MoodleService.obtenerCurso()          → verifica título
  → MoodleService.obtenerContenidoCurso() → cuenta módulos y exámenes,
                                            detecta certificado,
                                            verifica enlaces URL
  → MoodleService.obtenerBloquesCurso()   → detecta Completion Progress
  → MoodleService.obtenerItemsCalificacion() → verifica calificación
  → Guarda AuditoriaCurso en BD
  → Devuelve semáforos al dashboard
```
