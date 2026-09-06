<p align="center">
    <h1 align="center">AS-NegocioOS</h1>
    <p align="center">Sistema de gestión comercial (mini-ERP) construido con Laravel 13.</p>
</p>

AS-NegocioOS es un sistema privado (no público) de gestión comercial: clientes, productos,
ventas (facturas + PDF/Excel), inventario, reportes, configuración, auditoría y una API con
Sanctum. El acceso se controla por roles (`admin`, `vendedor`, `encargado`).

> **Registro público:** este proyecto **no** incluye auto-registro de usuarios. El primer
> administrador se crea con el seeder y los usuarios se gestionan desde el panel (módulo
> **Usuarios**, solo admin). Existe la rama `with-registration` que conserva el primer-usuario
> se registra como administrador, si se prefiere un arranque sin seeder.

## Requisitos

- PHP 8.2+ (probado con 8.5)
- Composer 2
- Node 20+ (probado con 24)
- SQLite (por defecto) u otro driver soportado por Laravel

## Instalación

```powershell
# 1. Clonar e instalar dependencias
git clone <repo> AS-NegocioOS
cd AS-NegocioOS
composer install
npm install

# 2. Configurar entorno
Copy-Item .env.example .env
# (opcional) ajusta APP_NAME, DB_*, MAIL_* en .env

# 3. Generar clave y base de datos
php artisan key:generate
New-Item database\database.sqlite -ItemType File -Force

# 4. Migrar + sembrar DB (crea el admin y datos de ejemplo)
php artisan migrate:fresh --seed

# 5. Compilar assets frontend
npm run build
```

> Para login OAuth/redes sociales no es necesario configurar credenciales. Los correos de
> restablecimiento de contraseña requieren configurar `MAIL_*` (por defecto se registran en log).

## Usuarios de ejemplo (seeder)

| Rol        | Correo               | Contraseña |
|------------|----------------------|------------|
| Admin      | `admin@as-negocios.com` | `password` |
| Vendedora  | `ventas@as-negocios.com`| `password` |
| Encargado  | `bodega@as-negocios.com`| `password` |

## Ejecución (desarrollo)

Dos terminales:

```powershell
# Terminal 1 — servidor web
php artisan serve

# Terminal 2 — Vite dev (hot reload)
npm run dev
```

Abre `http://127.0.0.1:8000` (o `npm run dev` + la URL que indican los logs).

## Tests

La base de tests es SQLite en memoria (ver `phpunit.xml`).

```powershell
composer test        # php artisan config:clear + php artisan test
vendor\bin\pint      # lint/format (preset Laravel)
npm run build        # compila assets (se usan en producción)
```

Suite completa: **173 tests / 700 assertions** (auth, dashboard, roles, clientes, productos,
ventas, inventario, reportes, configuración, API + audit, usuarios).

## Estructura de roles

| Capacidad | admin | vendedor | encargado |
|-----------|:-----:|:--------:|:---------:|
| Clientes (CRUD) | ✔ | ✔ | ✔ |
| Productos (leer) | ✔ | ✔ | ✔ |
| Productos (crear/editar/borrar) | ✔ | ✘ | ✔ |
| Ventas (crear) | ✔ | ✔ | ✘ |
| Ventas (editar/anular) | ✔ | ✘ | ✘ |
| Inventario | ✔ | ✘ | ✔ |
| Reportes | ✔ | ✘ | ✔ |
| Configuración | ✔ | ✘ | ✘ |
| Auditoría | ✔ | ✘ | ✘ |
| Usuarios (CRUD) | ✔ | ✘ | ✘ |

## Funcionalidades principales

- **Dashboard**: KPIs, gráficos (Chart.js), alertas de inventario, cliente top, últimos ingresos.
- **Clientes**: CRUD con historial de compras y saldo pendiente; búsqueda y paginación; PDF/Excel.
- **Productos**: CRUD con SKU, stock, control de caducidad; búsqueda/filtros; movimientos.
- **Ventas**: facturación con ítems dinámicos, IVA configurable, control de stock; factura PDF,
  lista PDF/Excel; conciliación de stock al editar/anular.
- **Inventario**: entradas/salidas con ajuste automático de stock; PDF/Excel.
- **Reportes**: ventas/clientes/productos/inventario con filtros; PDF/Excel.
- **Configuración**: datos de empresa, logotipo, moneda, IVA, idioma por defecto.
- **Auditoría**: bitácora de altas/bajas/cambios con diff (solo admin).
- **API REST** (Sanctum): auth token, dashboard, clientes, productos, ventas, inventario,
  reportes, configuración.

## Idiomas

Soporta español e inglés. El idioma se puede cambiar con el selector del encabezado o desde
la configuración de la empresa (idioma por defecto).

## Licencia

Este proyecto es de uso interno.
