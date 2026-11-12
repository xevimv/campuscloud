# Nomaddi Panel Central — Sincronizador de Cursos Moodle

## Archivos de este módulo

```
app/Services/
  MoodleSincronizadorService.php          → listar, importar, sincronizar nombres
  MoodleService_additions_sincronizador   → listarCursos(), obtenerCurso()

app/Http/Controllers/Api/
  SincronizadorController.php

app/cursos/sincronizar/page.tsx           → Página Next.js del sincronizador

routes/api.php                            → 3 rutas nuevas bajo /sincronizador
```

---

## Flujo de uso

### Antes de arrancar operaciones

1. Crea todos tus cursos en Moodle normalmente (ya los tienes).
2. Entra al panel → menú Cursos → "Sincronizar desde Moodle".
3. El panel consulta la API de Moodle y muestra todos los cursos.
4. Selecciona los que quieres importar con el checkbox.
5. Expande cada uno para configurar:
   - **Tipo de programa**: TECNICOS o BACHILLERATO
   - **Orden en la carrera**: 1 para el primero, 2 para el segundo, etc.
   - **Es el último**: marcar solo el curso que dispara la emisión del certificado
   - **Fee Nomaddi (Piso 1)**: lo que cobras por matrícula
   - **Precio mínimo público (Piso 2)**: lo que el distribuidor no puede cobrar menos
6. Clic en "Importar X curso(s)".
7. El sistema registra cada curso en la BD y crea automáticamente
   un grupo en Moodle para cada distribuidor activo.

### Después (mantenimiento)

Si renombras un curso en Moodle, usa el endpoint:
```
POST /api/sincronizador/cursos/{curso_id_panel}/nombre
```
Actualiza el nombre en el panel sin tocar precios ni configuración.

---

## Agregar al token de Moodle

En Moodle → Administración → Plugins → Servicios externos → tu token:
- `core_course_get_courses`

---

## Agregar al Sidebar de Next.js

En `components/layout/Sidebar.tsx`, agregar al array `nav`:

```tsx
{ href: '/cursos/sincronizar', label: 'Sincronizar Moodle', icon: RefreshCw },
```

---

## Qué pasa al importar un curso

1. Se crea el registro en la tabla `cursos` del panel
2. Se registra el precio inicial en la tabla `precios`
3. Se crea un grupo en Moodle para cada distribuidor activo
   (llama a `DistribuidorService::crearGrupoEnCurso()`)
4. Queda disponible para que los distribuidores lo vendan

Todo esto ocurre automáticamente — el admin solo selecciona
los cursos y configura tipo + precios.
