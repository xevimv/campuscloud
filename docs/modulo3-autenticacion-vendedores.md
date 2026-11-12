# Nomaddi Panel Central — Módulo 3: Auth + Vendedores + IETDH como Distribuidor

## Archivos de este módulo

```
database/migrations/
  007_create_usuarios_table.php         → usuarios del sistema + tokens Sanctum
  008_create_vendedores_and_alter.php   → vendedores + vendedor_id en matriculas
                                          + distribuidor_id en ietdhs

app/Models/
  Usuario.php          → Authenticatable con Sanctum + helpers de rol
  Vendedor.php         → con calcularComision() y resumenComisiones()

app/Services/
  AuthService.php      → login, logout, crear usuarios por rol
  VendedorService.php  → CRUD de vendedores + resolución de código referido

app/Http/Middleware/
  CheckRol.php         → verifica rol + scope de distribuidor

app/Http/Controllers/Api/
  AuthController.php
  VendedorController.php

routes/api.php
PARCHE_EpaycoService_MatriculaService.php
```

---

## Instalación

### 1. Instalar Sanctum
```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

### 2. Ejecutar migraciones
```bash
php artisan migrate
```

### 3. Registrar el middleware CheckRol
En `bootstrap/app.php` (Laravel 11) o `app/Http/Kernel.php` (Laravel 10):

```php
// Laravel 11 — bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'rol' => \App\Http\Middleware\CheckRol::class,
    ]);
})

// Laravel 10 — app/Http/Kernel.php → $routeMiddleware
'rol' => \App\Http\Middleware\CheckRol::class,
```

### 4. Configurar Sanctum en config/auth.php
```php
'guards' => [
    'api' => [
        'driver'   => 'sanctum',
        'provider' => 'usuarios',
    ],
],
'providers' => [
    'usuarios' => [
        'driver' => 'eloquent',
        'model'  => App\Models\Usuario::class,
    ],
],
```

### 5. Crear el primer NOMADDI_ADMIN
```bash
php artisan tinker
```
```php
App\Models\Usuario::create([
    'nombre'   => 'Javier',
    'email'    => 'javier@nomaddi.co',
    'password' => 'tu_password_seguro',
    'rol'      => 'NOMADDI_ADMIN',
]);
```

### 6. Aplicar parches de módulos anteriores
Ver archivo `PARCHE_EpaycoService_MatriculaService.php`

---

## Roles y permisos

| Rol | Acceso |
|-----|--------|
| NOMADDI_ADMIN | Todo el sistema |
| DIST_ADMIN | Solo su distribuidor: vendedores, matrículas, reportes |
| VENDEDOR | Solo sus propias matrículas y comisiones |

---

## Flujo completo del código de referido

```
1. Vendedor Carlos tiene código: CARL-X7K2M9
2. Comparte enlace: escuelahseq.com/?ref=CARL-X7K2M9
3. WooCommerce guarda el ref en cookie/sesión del comprador
4. Al pagar, plugin ePayco envía p_attr_6=CARL-X7K2M9
5. Webhook llega al panel con codigo_referido=CARL-X7K2M9
6. VendedorService.resolverPorCodigo() encuentra al vendedor
7. Matrícula se crea con vendedor_id=id_de_carlos
8. Reporte del distribuidor muestra: Carlos → 1 matrícula nueva
```

## IETDH como distribuidor

Cuando una IETDH quiere vender directamente:
1. Crear el distribuidor normalmente (POST /api/distribuidores)
2. Vincular la IETDH al distribuidor:

```bash
# Actualizar directamente en BD o agregar endpoint en próximo módulo
UPDATE ietdhs SET distribuidor_id = 'uuid-del-distribuidor' WHERE id = 'uuid-ietdh';
```

Al generar certificados, el sistema detecta que ietdh.distribuidor_id
coincide con el distribuidor de la matrícula y omite el cobro del
fee de IETDH (lógica a implementar en Módulo 5 — Certificados).

---

## Endpoints nuevos

| Método | URL | Rol requerido |
|--------|-----|---------------|
| POST | /api/auth/login | Público |
| POST | /api/auth/logout | Autenticado |
| GET  | /api/auth/me | Autenticado |
| POST | /api/auth/password | Autenticado |
| POST | /api/auth/usuarios/admin | NOMADDI_ADMIN |
| POST | /api/auth/usuarios/dist-admin | NOMADDI_ADMIN |
| GET  | /api/distribuidores/{id}/vendedores | NOMADDI_ADMIN, DIST_ADMIN |
| POST | /api/distribuidores/{id}/vendedores | NOMADDI_ADMIN, DIST_ADMIN |
| GET  | /api/distribuidores/{id}/vendedores/reporte | NOMADDI_ADMIN, DIST_ADMIN |
| GET  | /api/distribuidores/{id}/vendedores/{vid} | NOMADDI_ADMIN, DIST_ADMIN |
| PUT  | /api/distribuidores/{id}/vendedores/{vid}/comision | NOMADDI_ADMIN, DIST_ADMIN |
| POST | /api/distribuidores/{id}/vendedores/{vid}/desactivar | NOMADDI_ADMIN, DIST_ADMIN |

---

## Próximo módulo

**Módulo 4 — Formulario SIET post-curso 1**
Endpoint que recibe los 19 campos SIET del estudiante,
los valida, los guarda y desbloquea el curso 2 en Moodle vía API.
