# Nomaddi Panel Central — Módulo 8: Dashboard Next.js

## Archivos de este módulo

```
app/
  layout.tsx                          → Layout con Sidebar
  globals.css
  login/page.tsx                      → Página de login
  dashboard/page.tsx                  → Dashboard principal
  distribuidores/page.tsx             → Lista de distribuidores
  liquidaciones/page.tsx              → Gestión de liquidaciones
  certificados/page.tsx               → Registro de certificados
  webhooks/page.tsx                   → Log de webhooks

components/
  layout/Sidebar.tsx                  → Sidebar de navegación
  ui/index.tsx                        → MetricCard, Badge, Alert, formatters

lib/
  api.ts                              → Cliente HTTP hacia Laravel API

DashboardController_laravel.php       → COPIAR al proyecto Laravel
```

---

## Instalación

### 1. Instalar dependencias
```bash
npm install
```

### 2. Configurar variables de entorno
```bash
# .env.local
NEXT_PUBLIC_API_URL=https://panel.campusvirtual.edu.co/api
```

### 3. Agregar DashboardController al proyecto Laravel
- Copiar `DashboardController_laravel.php` a `app/Http/Controllers/Api/`
- Agregar la ruta en `routes/api.php`:
```php
Route::middleware(['auth:sanctum', 'rol:NOMADDI_ADMIN'])
    ->get('/dashboard', [DashboardController::class, 'index']);

Route::middleware(['auth:sanctum', 'rol:NOMADDI_ADMIN'])
    ->get('/logs/webhook', fn() => LogWebhook::latest()->paginate(50));
```

### 4. Correr en desarrollo
```bash
npm run dev
# Disponible en http://localhost:3000
```

### 5. Build para producción
```bash
npm run build
npm start
```

### 6. Configurar en el VPS de Hostinger
El panel Next.js corre en el mismo VPS que Moodle y Laravel.
Laravel corre en Apache/Nginx en el puerto 80/443.
Next.js corre con PM2 en el puerto 3000 y se expone en un subdominio:

```bash
# Instalar PM2
npm install -g pm2

# Iniciar el panel
pm2 start npm --name "nomaddi-panel" -- start
pm2 save
pm2 startup

# Configurar Nginx como proxy reverso
# En el bloque del servidor para panel.campusvirtual.edu.co:
# location / { proxy_pass http://localhost:3000; }
```

---

## Páginas disponibles

| Ruta | Descripción |
|------|-------------|
| `/login` | Autenticación |
| `/dashboard` | Vista general con métricas, gráficas y alertas |
| `/distribuidores` | Lista de distribuidores con estado e IETDHs |
| `/distribuidores/{id}` | Detalle: vendedores, matrículas, configuración |
| `/liquidaciones` | Lista con acciones: descargar PDF, marcar pagada |
| `/certificados` | Registro con descarga de PDF y anulación |
| `/webhooks` | Log en tiempo real de webhooks de ePayco |

---

## Funcionalidades del dashboard

- **Métricas de hoy**: matrículas, certificados, webhooks OK/error
- **Métricas del mes**: acumulados + liquidaciones por cobrar en COP
- **Alertas automáticas**: webhooks con error, liquidaciones viejas, distribuidores sin IETDH
- **Gráfica de matrículas**: área, últimos 30 días
- **Top distribuidores**: barras horizontales del mes actual
- **Liquidaciones pendientes**: lista rápida con valor y estado
- **Webhooks recientes**: últimas 10 transacciones con estado

El dashboard se auto-refresca cada 60 segundos con SWR.
Los webhooks se refrescan cada 30 segundos.
Los datos del dashboard se cachean 5 minutos en Laravel para no saturar la BD.

---

## Próximo y último módulo

**Módulo 9 — Validación pública de certificados**
Página web pública (`/verificar/{uuid}`) accesible sin autenticación
desde cualquier dominio de la red de distribuidores,
con diseño adaptable a la marca del dominio consultante.
