# Nomaddi Panel Central — Inventario de Cursos Moodle

## Qué hace

Genera una tabla de contenido completa de cada curso directamente desde
la API REST de Moodle, sin plugins adicionales.

### Vista resumen (tabla comparativa)
Muestra todos los cursos en una tabla con columnas por tipo de contenido:
videos, audios, PDFs, páginas, exámenes, H5P, enlaces, tareas, certificado.
Incluye fila de totales al pie.

### Vista detalle (por curso)
- **Metadatos**: título largo, título corto, descripción, imagen de portada,
  categoría en Moodle, idioma, formato, fechas de inicio/fin
- **Resumen visual**: chips coloreados por tipo con cantidad
- **Grid de métricas**: 6 métricas clave con ícono y número
- **Secciones expandibles**: cada sección muestra sus módulos con tipo,
  nombre, archivos adjuntos, tamaño y enlace directo a Moodle

---

## Tipos de contenido detectados

| Tipo | Cómo lo detecta |
|------|----------------|
| 🎬 VIDEO | `modname=resource` + mimetype `video/*` o extensión mp4/webm/avi |
| 🎵 AUDIO | `modname=resource` + mimetype `audio/*` o extensión mp3/wav |
| 📄 PDF | `modname=resource` + mimetype `application/pdf` |
| 🖼️ IMAGEN | `modname=resource` + mimetype `image/*` |
| 📝 PÁGINA | `modname=page` |
| 🏷️ ETIQUETA | `modname=label` |
| 📚 LIBRO | `modname=book` |
| 📋 EXAMEN | `modname=quiz` |
| ✏️ TAREA | `modname=assign` |
| 💬 FORO | `modname=forum` |
| 🔗 ENLACE | `modname=url` |
| 🎮 H5P | `modname=h5pactivity` o `modname=hvp` |
| 📦 SCORM | `modname=scorm` |
| 📖 GLOSARIO | `modname=glossary` |
| 🎯 LECCIÓN | `modname=lesson` |
| 📁 CARPETA | `modname=folder` |
| 📊 ENCUESTA | `modname=survey` / `feedback` / `choice` |
| 🏆 CERTIFICADO | `modname=customcert` / `certificate` / `simplecertificate` |
| 📊 PRESENTACIÓN | `modname=resource` + mimetype PowerPoint |
| 📃 DOCUMENTO | `modname=resource` + mimetype Word |

---

## Archivos de este módulo

```
app/Services/
  InventarioCursoService.php            → clasificación completa + metadatos

app/Http/Controllers/Api/
  InventarioController.php              → 3 endpoints con caché

app/inventario/
  page.tsx                              → tabla comparativa
  [id]/page.tsx                         → detalle por curso con secciones

routes/api.php                          → 3 rutas bajo /inventario
```

---

## Instalación

### 1. No requiere migraciones
El inventario lee todo de Moodle en tiempo real. No persiste en BD.

### 2. No requiere funciones nuevas en el token de Moodle
Las funciones `core_course_get_courses` y `core_course_get_contents`
ya fueron agregadas al token en el sincronizador y la auditoría.

### 3. Agregar al Sidebar de Next.js
```tsx
import { Package } from 'lucide-react'
{ href: '/inventario', label: 'Inventario', icon: Package },
```

---

## Caché

El resumen de todos los cursos se cachea **30 minutos** en Laravel.
El detalle de cada curso se cachea **15 minutos**.
Para forzar regeneración: botón "Refrescar" en el detalle,
o endpoint `POST /api/inventario/{curso}/refrescar`.

---

## Diferencia con la Auditoría

| | Auditoría | Inventario |
|--|-----------|-----------|
| **Propósito** | Verificar si el curso cumple criterios | Conocer qué tiene cada curso |
| **Resultado** | Semáforo: aprobado/advertencia/reprobado | Tabla de contenido descriptiva |
| **Se guarda en BD** | Sí (historial) | No (caché temporal) |
| **Acción requerida** | El admin audita y corrige | Solo lectura y análisis |
| **Cuándo usarlo** | Antes de lanzar un curso | Para planear, comparar, reportar |
