# Nomaddi — Portal del Distribuidor (DIST_ADMIN)

## Qué hace

Portal exclusivo para administradores de distribuidores.
Accede con el mismo login del panel pero redirige automáticamente
a `/dist/dashboard` en lugar del panel de Nomaddi.

---

## Páginas

| Ruta | Qué muestra |
|------|-------------|
| `/dist/dashboard` | Métricas del mes y semana, gráfica de matrículas, top cursos, ranking de vendedores |
| `/dist/estudiantes` | Lista paginada con filtros por estado y SIET, con semáforo de formulario |
| `/dist/vendedores` | Tarjetas por vendedor: matrículas del mes, comisión calculada, enlace de referido con botón copiar |
| `/dist/certificados` | Certificados emitidos a sus estudiantes con enlace de verificación |

---

## Archivos

```
app/Http/Controllers/Api/
  DistribuidorDashboardController.php    → 4 endpoints con scope por distribuidor_id

components/dist/
  DistLayout.tsx                         → Sidebar limpio con nombre del distribuidor

app/dist/
  dashboard/page.tsx                     → Inicio con métricas y gráficas
  estudiantes/page.tsx                   → Lista con filtros y semáforo SIET
  vendedores/page.tsx                    → Tarjetas con código referido y comisiones
  certificados/page.tsx                  → Tabla con enlace de verificación

routes/api.php                           → 4 rutas bajo /dist con middleware DIST_ADMIN
```

---

## Instalación

No requiere migraciones. Solo:

1. Copiar el controlador al proyecto Laravel.
2. Agregar las rutas al `routes/api.php`.
3. Copiar los archivos Next.js al proyecto del panel.

### Redirigir al login según el rol

En la página de login (`app/login/page.tsx`), después de autenticar,
redirigir según el rol:

```tsx
const res = await api.post('/auth/login', { email, password })
localStorage.setItem('nomaddi_token', res.token)

// Redirigir según el rol
const me = await api.get('/auth/me')
if (me.rol === 'DIST_ADMIN') {
  window.location.href = '/dist/dashboard'
} else if (me.rol === 'NOMADDI_ADMIN') {
  window.location.href = '/dashboard'
} else {
  window.location.href = '/dist/dashboard' // VENDEDOR también va al portal dist
}
```

---

## Seguridad

El middleware `rol:DIST_ADMIN` en Laravel garantiza que:
- Un NOMADDI_ADMIN no puede acceder a `/api/dist/*`
- Un DIST_ADMIN no puede acceder a `/api/distribuidores`, `/api/ietdhs`, etc.
- Todos los datos devueltos por `/api/dist/*` están filtrados por
  `distribuidor_id = $request->user()->distribuidor_id`

El DIST_ADMIN nunca ve datos de otro distribuidor.

---

## Lo que el distribuidor puede hacer

✅ Ver sus matrículas en tiempo real sin esperar el correo del lunes
✅ Identificar estudiantes que completaron el curso 1 pero no llenaron el SIET
✅ Ver el rendimiento de sus vendedores y copiar sus enlaces de referido
✅ Consultar los certificados emitidos y compartir el link de verificación

❌ No puede crear ni editar nada — es solo lectura
❌ No ve datos de otros distribuidores
❌ No ve precios mínimos ni fees de Nomaddi
