# Panel Central — CampusCloud / Nomaddi

> Este archivo es leído automáticamente por Claude Code al arrancar. Contiene todo el contexto del proyecto.

## Descripción general

Sistema de administración de campus virtual que complementa Moodle 4.5. Nomaddi opera `campusvirtual.edu.co` (Hostinger) y permite que distribuidores externos vendan cursos técnicos y de bachillerato bajo sus propias marcas.

## Modelo de negocio

- **Piso 1**: fee fijo de Nomaddi por matrícula
- **Piso 2**: precio mínimo público por curso (evita canibalismo entre distribuidores)
- **Distribuidores**: WordPress + WooCommerce + ePayco. ePayco Pagos Divididos divide automáticamente el pago (fee a Nomaddi + diferencial al distribuidor)
- **IETDHs**: avalan y firman certificados → reciben fee fijo por certificado emitido, liquidado el día 1 de cada mes
- Una IETDH puede ser también distribuidora → no se le cobra fee de certificación

## Stack tecnológico

| Capa | Tecnología |
|------|-----------|
| Backend | Laravel + PostgreSQL (mismo VPS Hostinger que Moodle) |
| Frontend panel | Next.js 14 con PM2 |
| Autenticación | Sanctum — roles: `NOMADDI_ADMIN`, `DIST_ADMIN`, `VENDEDOR` |
| PDF | DomPDF + endroid/qr-code |
| Exportación SIET | PhpSpreadsheet (XLS Excel 97-2003) |
| IDs | UUID en todas las tablas |

## Fuentes de verdad

- **Panel Central** → fuente de verdad del **negocio**
- **Moodle** → fuente de verdad del **aprendizaje**

## Módulos desarrollados

Ver carpeta `docs/` para detalle de cada módulo.

| Módulo | Descripción |
|--------|-------------|
| [Módulo 1](docs/modulo1-webhook-matricula.md) | Webhook ePayco + matrícula automática en Moodle vía API REST |
| [Módulo 2](docs/modulo2-distribuidores-ietdhs-cursos.md) | CRUD distribuidores, IETDHs, cursos + sincronización cohortes/grupos Moodle |
| [Módulo 3](docs/modulo3-autenticacion-vendedores.md) | Autenticación Sanctum, vendedores con código referido (`?ref=CARL-X7K2M9`), IETDH como distribuidora |
| [Módulo 4](docs/modulo4-siet-exportacion.md) | Formulario SIET 19 campos post-curso 1, exportación XLS formato SIET, Job de sincronización |
| [Módulo 5](docs/modulo5-certificados-actas.md) | Certificados con actas (T/B + año 2d + mes 2d, ej: T2604), folios consecutivos con bloqueo pesimista, UUID verificación |
| [Módulo 5B](docs/modulo5b-pdf-correo.md) | PDF del certificado con DomPDF, QR embebido, envío automático por correo |
| [Módulo 6](docs/modulo6-liquidaciones-ietdh.md) | Liquidaciones mensuales IETDHs — cierre día 1 a las 6am, PDF adjunto, estados: GENERADA → ENVIADA → FACTURADA → PAGADA |
| [Módulo 7](docs/modulo7-reportes-distribuidores.md) | Reportes automáticos a distribuidores — semanal (lunes 8am) y mensual (día 1, 8:30am) |
| [Módulo 8](docs/modulo8-dashboard.md) | Dashboard Next.js — métricas en tiempo real, Recharts, alertas, páginas distribuidores/liquidaciones/certificados/webhooks |
| [Módulo 9](docs/modulo9-verificacion-certificados.md) | Validación pública en `/verificar/{uuid}` + widget HTML embebible para WordPress |
| [Auditoría](docs/auditoria-cursos.md) | Auditor de calidad de cursos con 7 criterios y semáforo |
| [Sincronizador](docs/sincronizador-cursos.md) | Importador de cursos existentes desde Moodle |
| [Portal Distribuidor](docs/portal-distribuidor.md) | Panel de acceso para distribuidores — dashboard, certificados, estudiantes, vendedores |
| [Inventario Cursos](docs/inventario-cursos.md) | Gestión de inventario de cursos con vista detallada por curso |

## Convenciones de código críticas

1. **Todos los IDs son UUID** — nunca autoincrement
2. **Precios e históricos de fees** usan `vigente_desde` para preservar historial
3. **Jobs críticos** usan `lockForUpdate()` (bloqueo pesimista) para evitar duplicados
4. **Webhook ePayco**:
   - Errores **definitivos** → HTTP 200 (no reintento)
   - Errores **transitorios** → HTTP 500 (forzar reintento de ePayco)
5. **Actas**: código = tipo (T/B) + año 2 dígitos + mes 2 dígitos → ej: `T2604`
6. **Folios**: consecutivos por acta con bloqueo pesimista

## Estructura del repositorio

```
campuscloud/
├── backend/              ← Laravel (PHP)
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/Api/
│   │   │   ├── Middleware/
│   │   │   └── Requests/
│   │   ├── Jobs/
│   │   ├── Mail/
│   │   ├── Models/
│   │   └── Services/
│   ├── config/
│   ├── database/migrations/
│   ├── resources/views/
│   └── routes/
├── frontend/             ← Next.js 14
│   ├── app/
│   ├── components/
│   └── public/
├── docs/                 ← Documentación por módulo
└── CLAUDE.md             ← Este archivo
```

## Tablas principales de la BD

**Configuración:** `distribuidores`, `cursos`, `precios` (histórico con `vigente_desde`), `ietdhs`, `ietdh_fees`, `distribuidor_ietdh`, `distribuidor_grupos_cursos`, `vendedores`, `usuarios`

**Operaciones:** `estudiantes`, `estudiantes_siet`, `matriculas`, `actas`, `certificados`, `liquidaciones`

**Logs:** `logs_webhook`, `logs_desbloqueo_siet`, `logs_validacion_certificado`, `municipios_dane`

## Pendientes antes de producción

- [ ] Conectar resolvedores de certificador y exoneración de fee con módulos 2, 5, 5B y 6
- [ ] Instalar `doctrine/dbal` y verificar extensión `zip` de PHP
- [ ] Configurar token Moodle con 12+ funciones habilitadas
- [ ] Crear campos personalizados de perfil Moodle para SIET y certificados
- [ ] Configurar servidor: crontab, worker de colas, storage link, Nginx, PM2, Sanctum
- [ ] Activar ePayco Pagos Divididos (depende del proveedor)
- [ ] ⚠️ Verificar con institución: campo municipio de residencia SIMAT — ¿2 o 3 caracteres?
- [ ] Seeder de municipios DANE (1.122 municipios)
- [ ] Logos de IETDHs en storage para PDFs de certificados
- [ ] Plantilla SIMAT de bachillerato (campos bachillerato en `estudiantes_siet` reservados pero sin definir)

## Variables de entorno requeridas

Ver `backend/.env.example`. Las claves secretas (tokens Moodle, credenciales ePayco, DB) se pasan por fuera del repositorio de forma segura — **nunca commitear el `.env`**.

## Próximas funcionalidades

- Módulo de emisión de diplomas desde el Panel (base de datos por institución, consulta pública, históricos)
- Matrícula con lectura de cédula por IA
- Importador de notas desde Excel con escala configurable
- Homologaciones multi-semestre
- Agentes de IA: **Rosalía** (deserción), **Ángela** (tutora), **Dina** (ventas)
