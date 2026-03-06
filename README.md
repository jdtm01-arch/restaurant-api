# Sistema de Gestión para Restaurantes

Fullstack restaurant management system built with **Laravel (REST API)** and **React (SPA)**.

The platform manages the full operational cycle of a restaurant:
orders → sales → expenses → cash register → financial reporting.

Designed with a decoupled architecture so the backend API can also power future mobile applications.

Plataforma SaaS multi-tenant para la gestión operativa y financiera de restaurantes.

---

## Tabla de Contenidos

- [Características](#-características)
- [Stack Tecnológico](#-stack-tecnológico)
- [Requisitos Previos](#-requisitos-previos)
- [Instalación](#-instalación)
- [Variables de Entorno](#-variables-de-entorno)
- [Uso](#-uso)
- [Estructura del Proyecto](#-estructura-del-proyecto)
- [Testing](#-testing)
- [Roles de Usuario](#-roles-de-usuario)
- [Documentación Técnica](#-documentación-técnica)
- [Licencia](#-licencia)

---

## Características

### Operaciones
- **Gestión de pedidos** — Crear, agregar ítems, cerrar, reabrir, cancelar, cambio de mesa
- **Cobro flexible** — Pagos con múltiples métodos (efectivo, tarjeta, etc.) y cuentas financieras
- **Mapa visual de mesas** — Disposición configurable con drag & drop

### Finanzas
- **Caja registradora** — Apertura/cierre diario con validación de montos
- **Cierre contable** — Bloquea la fecha y genera resumen financiero del día
- **Cuentas financieras** — Múltiples cuentas (efectivo, digital, banco) con saldo dinámico calculado
- **Transferencias** — Movimientos entre cuentas con trazabilidad completa
- **Gastos** — CRUD con pagos parciales/totales, adjuntos, auditoría campo-a-campo

### Reportes
- Ventas por categoría, por hora, por mesero
- Productos más vendidos
- Flujo de caja diario
- Cancelaciones y descuentos
- Cuentas por pagar et resumen ejecutivo

### Seguridad
- Autenticación por token Bearer (Sanctum)
- Cabeceras de seguridad (X-Content-Type-Options, HSTS, X-Frame-Options)
- Sistema de roles y permisos (5 roles)
- Aislamiento multi-tenant por restaurante
- Auditoría inmutable de operaciones financieras

---

## Stack Tecnológico

| Capa | Tecnología | Versión |
|------|-----------|---------|
| Backend | PHP / Laravel | 8.2 / 12.x |
| Frontend | React / Vite | 18.3 / 6.0 |
| Base de datos | MySQL | 8.0 |
| Autenticación | Laravel Sanctum | 4.3 |
| Estilos | TailwindCSS | 3.4 |
| Gráficos | Recharts | 3.7 |
| Testing | PHPUnit | 11.5 |

---

## Requisitos Previos

- **PHP** >= 8.2 con extensiones: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`
- **Composer** >= 2.x
- **Node.js** >= 18.x
- **npm** >= 9.x
- **MySQL** >= 8.0

---

## Instalación

### 1. Crear carpeta raíz y clonar repositorios

```bash
mkdir restaurant-app && cd restaurant-app

# Backend (API REST)
git clone https://github.com/jdtm01-arch/restaurant-api.git backend

# Frontend (SPA React)
git clone https://github.com/jdtm01-arch/restaurant-web.git frontend
```

### 2. Configurar el Backend

```bash
cd backend

# Instalar dependencias PHP
composer install

# Copiar archivo de configuración
cp .env.example .env

# Generar clave de aplicación
php artisan key:generate

# Configurar variables de entorno (ver sección siguiente)
nano .env

# Ejecutar migraciones y seeders
php artisan migrate --seed

# Crear enlace simbólico de storage
php artisan storage:link
```

### 3. Configurar el Frontend

```bash
cd ../frontend

# Instalar dependencias Node
npm install
```

### 4. Iniciar los Servidores

```bash
# Terminal 1: Backend
cd backend
php artisan serve
# → http://localhost:8000

# Terminal 2: Frontend
cd frontend
npm run dev
# → http://localhost:5173
```

---

## Variables de Entorno

### Backend (`backend/.env`)

```env
# Aplicación
APP_NAME="Tu Restaurante"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Base de Datos
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=restaurante_saas
DB_USERNAME=root
DB_PASSWORD=tu_contraseña_segura

# Seguridad
BCRYPT_ROUNDS=12

# CORS
CORS_ALLOWED_ORIGINS=http://localhost:5173

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost:5173

# Sesión
SESSION_LIFETIME=120
```

### Frontend (`frontend/.env`)

```env
VITE_API_URL=http://localhost:8000/api
```

---

## Uso

### Usuario por Defecto (Seeder)

Tras ejecutar `php artisan migrate --seed`, se crea un usuario administrador:

| Campo | Valor |
|-------|-------|
| Email | `super-admin@turestaurante.com` |
| Password | `admin1234` |
| Rol | `admin_general` |

> ⚠️ **Importante**: Cambiar la contraseña en producción.

### Flujo Operativo Típico

1. **Iniciar sesión** con credenciales de admin
2. **Inicializar el módulo financiero** (primera vez — asignar saldos iniciales a cuentas)
3. **Abrir caja** para el día (con monto de apertura)
4. **Crear pedidos** → agregar ítems → cerrar pedidos
5. **Cobrar pedidos** (genera venta + movimiento financiero)
6. **Registrar gastos** del día (con pagos parciales/totales)
7. **Cerrar caja** (comparar monto real vs esperado)
8. **Cierre contable** (bloquea el día — no más modificaciones)

---

## Estructura del Proyecto

```
restaurant-app/
├── backend/                    # API REST — Laravel 12
│   ├── app/
│   │   ├── Exceptions/         # 28 excepciones tipadas por dominio
│   │   ├── Http/
│   │   │   ├── Controllers/    # 20 controllers
│   │   │   ├── Middleware/     # 5 middleware custom
│   │   │   └── Requests/      # 20 Form Requests
│   │   ├── Models/             # 26 modelos Eloquent
│   │   ├── Policies/           # 17 policies de autorización
│   │   └── Services/           # 14 servicios de lógica de negocio
│   ├── database/
│   │   ├── migrations/         # 27 migraciones
│   │   └── seeders/            # 7 seeders (incluye flujo completo)
│   ├── routes/
│   │   └── api.php             # ~60 endpoints REST
│   └── tests/
│       └── Feature/            # 292 tests / 564 assertions
│
├── frontend/                   # SPA — React 18 + Vite
│   ├── src/
│   │   ├── api/                # 24 módulos API (Axios)
│   │   ├── components/         # Reutilizables + UI kit
│   │   ├── context/            # AuthContext
│   │   ├── hooks/              # useCrud (CRUD genérico)
│   │   ├── layouts/            # DashboardLayout
│   │   └── pages/              # 20+ páginas por módulo
│   └── vite.config.js
│
└── docs/                       # Documentación técnica
    └── 04_canvas_fuente_de_verdad_proyecto.md
```

---

## Testing

### Ejecutar la Suite Completa

```bash
cd backend
php artisan test
```

### Resultado Esperado

```
Tests:    292 passed (564 assertions)
Duration: ~15s
```

### Ejecutar un Test Específico

```bash
# Por archivo
php artisan test --filter=OrderTest

# Por método
php artisan test --filter=OrderTest::test_mozo_cannot_update_other_users_order
```

### Tests Destacados

| Archivo | Propósito |
|---------|-----------|
| `FinancialAuditTest` | 42 escenarios de reglas financieras |
| `FullDayFlowTest` | Flujo E2E de un día operativo completo |
| `MultiTenantIsolationTest` | Aislamiento de datos entre restaurantes |
| `RolesPermissionsTest` | Matriz completa de permisos por rol |

---

## Roles de Usuario

| Rol | Operaciones | Finanzas | Administración | Auditoría | Reportes |
|-----|------------|----------|----------------|----------|----------|
| **Admin General** | ✅ Todo | ✅ Todo | ✅ Todo | ✅ Todo | ✅ Todo |
| **Admin Restaurante** | ✅ Todo | ✅ Todo | ✅ Todo | ❌ | ✅ Todo |
| **Cajero** | ✅ Pedidos + Cobros | ✅ Caja + Pagos + Transfers | ❌ | ❌ | ❌ |
| **Mozo** | ✅ Solo sus pedidos | ❌ | ❌ | ❌ | ❌ |
| **Cocina** | Ver pedidos | ❌ | ❌ | ❌ | ❌ |

---

## Documentación Técnica

La documentación exhaustiva del proyecto se encuentra en la carpeta `/docs/`:

---
