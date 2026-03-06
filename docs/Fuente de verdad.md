# CANVAS — Fuente de Verdad del Proyecto

## Sistema de Gestión Integral para Restaurantes — Tu Restaurante

> **Versión:** 3.1 · **Fecha:** 6 de marzo de 2026 · **Clasificación:** Documento técnico interno

---

## 📑 ÍNDICE

1. [Visión General](#1--visión-general)
2. [Arquitectura del Sistema](#2--arquitectura-del-sistema)
3. [Estructura de Carpetas](#3--estructura-de-carpetas)
4. [Flujo de Datos](#4--flujo-de-datos)
5. [Modelo de Datos](#5--modelo-de-datos)
6. [Endpoints y Rutas Críticas](#6--endpoints-y-rutas-críticas)
7. [Capa de Servicios — Lógica de Negocio](#7--capa-de-servicios--lógica-de-negocio)
8. [Sistema de Roles y Autorización](#8--sistema-de-roles-y-autorización)
9. [Seguridad](#9--seguridad)
10. [Testing](#10--testing)
11. [Frontend — SPA React](#11--frontend--spa-react)
12. [Mejoras Futuras](#12--mejoras-futuras)

---

## 1. VISIÓN GENERAL

| Aspecto | Detalle |
|---------|---------|
| **Producto** | Plataforma SaaS multi-tenant para la gestión operativa y financiera de restaurantes |
| **Dominio** | Pedidos → Ventas → Gastos → Caja → Cierre contable diario |
| **Audiencia** | Administradores, cajeros, mozos y personal de cocina |
| **Estado** | Producción con 292 tests / 564 assertions — suite verde |

### Stack Tecnológico

| Capa | Tecnología | Versión |
|------|-----------|---------|
| Backend | PHP — Laravel | 12.x |
| Base de datos | MySQL | 8.0 |
| Autenticación | Laravel Sanctum | 4.3 |
| Frontend | React + Vite | 18.3 / 6.0 |
| Estilos | TailwindCSS | 3.4 |
| Gráficos | Recharts | 3.7 |
| PDF | jsPDF + html2canvas | 4.2 / 1.4 |
| Notificaciones | react-hot-toast | 2.4 |
| Routing | react-router-dom | 6.28 |
| Testing | PHPUnit | 11.5 |

---

## 2. ARQUITECTURA DEL SISTEMA

### 2.1 Patrón de Diseño

Arquitectura en **capas** con separación estricta de responsabilidades:

```
┌──────────────────────────────────────────────────────────────┐
│                     FRONTEND (SPA React)                     │
│   React 18 + Vite · Axios · react-router-dom · TailwindCSS  │
└──────────────────────┬───────────────────────────────────────┘
                       │ HTTP REST + JSON
                       │ Bearer Token + X-Restaurant-Id header
                       ▼
┌──────────────────────────────────────────────────────────────┐
│                   MIDDLEWARE PIPELINE                         │
│                                                              │
│  NormalizeApiResponse → SecurityHeaders → auth:sanctum       │
│  → set.restaurant → financial.initialized (condicional)      │
└──────────────────────┬───────────────────────────────────────┘
                       ▼
┌──────────────────────────────────────────────────────────────┐
│                     CONTROLLERS                              │
│  Validan request (FormRequest) · Llaman $this->authorize()   │
│  (Policy gate) · Delegan al Service · Retornan JSON          │
└──────────────────────┬───────────────────────────────────────┘
                       ▼
┌──────────────────────────────────────────────────────────────┐
│                      SERVICES                                │
│  Toda la lógica de negocio · Validaciones complejas          │
│  DB::transaction() · Generación de movimientos financieros   │
│  Auditoría · Exceptions tipadas                              │
└──────────────────────┬───────────────────────────────────────┘
                       ▼
┌──────────────────────────────────────────────────────────────┐
│                   ELOQUENT MODELS                            │
│  Global Scope RestaurantScope (multi-tenant auto-filter)     │
│  Trait BelongsToRestaurant · Relaciones · SoftDeletes        │
└──────────────────────┬───────────────────────────────────────┘
                       ▼
┌──────────────────────────────────────────────────────────────┐
│                      MySQL 8.0                               │
│  26 tablas de negocio · UNIQUE constraints · FK CASCADE      │
│  Índices compuestos · SoftDeletes (deleted_at)               │
└──────────────────────────────────────────────────────────────┘
```

### 2.2 Multi-Tenancy

El aislamiento de datos entre restaurantes se implementa en **tres niveles**:

| Nivel | Mecanismo | Descripción |
|-------|-----------|-------------|
| **HTTP** | Middleware `SetRestaurantContext` | Lee `X-Restaurant-Id` del header, verifica pertenencia del usuario al restaurante |
| **ORM** | Global Scope `RestaurantScope` | Auto-filtra TODAS las queries por `restaurant_id` activo |
| **DB** | Foreign Keys + UNIQUE compuestos | `UNIQUE(restaurant_id, field)` impide colisiones a nivel de datos |

### 2.3 Diagrama de Componentes

```
                        ┌─────────────┐
                        │   Browser   │
                        └──────┬──────┘
                               │
                    ┌──────────▼──────────┐
                    │  React SPA (:5173)  │
                    │                      │
                    │  Context ─► Pages    │
                    │  API Layer (Axios)   │
                    └──────────┬──────────┘
                               │ REST API
                    ┌──────────▼──────────┐
                    │  Laravel API (:8000) │
                    │                      │
                    │  Middleware Pipeline  │
                    │  Controllers         │
                    │  Services            │
                    │  Policies            │
                    │  Models + Scopes     │
                    └──────────┬──────────┘
                               │
                    ┌──────────▼──────────┐
                    │     MySQL 8.0       │
                    │  restaurante_saas   │
                    └─────────────────────┘
```

---

## 3. ESTRUCTURA DE CARPETAS

### 3.1 Backend (`/backend`)

```
backend/
├── app/
│   ├── Exceptions/                  # Excepciones tipadas por dominio
│   │   ├── BusinessException.php     # Base class — render() → JSON
│   │   ├── CashClosing/             # 3 excepciones de cierre contable
│   │   ├── CashRegister/            # 4 excepciones de caja registradora
│   │   ├── Common/                  # ResourceLockedException
│   │   ├── Expense/                 # 5 excepciones de gastos
│   │   ├── Order/                   # 6 excepciones de pedidos
│   │   └── Sale/                    # 3 excepciones de ventas
│   │
│   ├── Http/
│   │   ├── Controllers/             # 20 controllers (REST + acciones custom)
│   │   │   ├── Api/                 # AuthController, ExpenseController, SupplierController
│   │   │   ├── AccountTransferController.php
│   │   │   ├── AuditController.php
│   │   │   ├── CashClosingController.php
│   │   │   ├── CashRegisterController.php
│   │   │   ├── CatalogController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── ExpenseCategoryController.php
│   │   │   ├── FinancialAccountController.php
│   │   │   ├── FinancialInitializationController.php
│   │   │   ├── FinancialMovementController.php
│   │   │   ├── OrderController.php        # El más complejo: 14 actions
│   │   │   ├── PaymentMethodController.php
│   │   │   ├── ProductCategoryController.php
│   │   │   ├── ProductController.php
│   │   │   ├── ReportController.php       # 10 reportes analíticos
│   │   │   ├── SaleController.php
│   │   │   ├── TableController.php
│   │   │   ├── UserController.php
│   │   │   └── WasteLogController.php
│   │   │
│   │   ├── Middleware/               # 5 middleware custom
│   │   │   ├── EnsureFinancialInitialized.php
│   │   │   ├── NormalizeApiResponse.php
│   │   │   ├── SecurityHeaders.php
│   │   │   ├── SetRestaurantContext.php
│   │   │   └── VerifyCsrfToken.php
│   │   │
│   │   ├── Requests/                 # 20 Form Requests validados
│   │   └── Traits/
│   │       └── ApiResponse.php       # Trait de respuesta estandarizada
│   │
│   ├── Models/                       # 24 modelos Eloquent
│   │   ├── Scopes/
│   │   │   └── RestaurantScope.php   # Auto-filter por restaurant_id
│   │   ├── Traits/
│   │   │   └── BelongsToRestaurant.php
│   │   └── [24 modelos...]
│   │
│   ├── Policies/                     # 17 policies de autorización
│   │
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   └── AuthServiceProvider.php   # Registro de policies
│   │
│   └── Services/                     # 14 servicios de lógica de negocio
│       ├── AccountTransferService.php
│       ├── AuditService.php
│       ├── CashClosingService.php
│       ├── CashRegisterService.php
│       ├── CashValidationService.php       # Árbitro central de validaciones
│       ├── ExpenseAuditService.php
│       ├── ExpensePaymentService.php
│       ├── ExpenseService.php
│       ├── FinancialAccountService.php
│       ├── FinancialInitializationService.php
│       ├── FinancialMovementService.php
│       ├── KitchenTicketService.php
│       ├── OrderService.php
│       ├── ReceiptService.php
│       ├── ReportService.php
│       └── SaleService.php
│
├── bootstrap/
│   └── app.php                  # Middleware aliases, exception rendering
│
├── config/                      # CORS, Sanctum, DB, etc.
│
├── database/
│   ├── migrations/              # 27 migraciones (2026-02-27 a 2026-03-05)
│   ├── seeders/                 # 6 seeders + DatabaseSeeder (flujo completo)
│   └── schema/
│       └── mysql-schema.sql     # DDL actualizado
│
├── routes/
│   └── api.php                  # ~60 rutas REST agrupadas por módulo
│
└── tests/
    └── Feature/                 # 26 archivos de test · 292 tests
        ├── Traits/
        │   └── SetUpRestaurant.php   # Trait reutilizable de setup
        ├── AuthTest.php
        ├── FinancialAuditTest.php    # 42 escenarios financieros
        ├── FullDayFlowTest.php       # Flujo completo de un día
        ├── MultiTenantIsolationTest.php
        └── [22 más...]
```

### 3.2 Frontend (`/frontend`)

```
frontend/
├── public/                           # Assets estáticos
├── src/
│   ├── api/                          # 24 módulos API (Axios wrappers)
│   │   ├── axios.js                  # Interceptors: token, restaurant, errors
│   │   ├── orders.js
│   │   ├── sales.js
│   │   ├── expenses.js
│   │   ├── financialAccounts.js
│   │   └── [20 más...]
│   │
│   ├── components/                   # Componentes reutilizables
│   │   ├── Header.jsx
│   │   ├── Sidebar.jsx               # Navegación per-role
│   │   ├── ProtectedRoute.jsx        # Guard de ruta + role check
│   │   ├── TableMap.jsx              # Mapa visual de mesas
│   │   └── ui/                       # UI kit
│   │       ├── Alert.jsx
│   │       ├── ConfirmDialog.jsx
│   │       ├── DataTable.jsx          # Tabla reutilizable con columnas
│   │       ├── FinancialNotInitializedBanner.jsx
│   │       ├── Modal.jsx
│   │       ├── Pagination.jsx
│   │       ├── ProductSearch.jsx      # Autocompletado de productos
│   │       └── Spinner.jsx
│   │
│   ├── context/
│   │   └── AuthContext.jsx            # Auth state + role derivation
│   │
│   ├── hooks/
│   │   └── useCrud.js                 # Hook genérico CRUD con toast + errors
│   │
│   ├── layouts/
│   │   └── DashboardLayout.jsx        # Sidebar + Header + Outlet
│   │
│   ├── pages/
│   │   ├── Dashboard.jsx              # Dashboard principal (admin)
│   │   ├── Login.jsx
│   │   ├── NotFound.jsx
│   │   ├── admin/                     # 11 páginas administrativas
│   │   ├── finance/                   # 4 páginas financieras
│   │   ├── kitchen/
│   │   │   └── KitchenDisplay.jsx     # Pantalla de cocina
│   │   └── operations/                # 4 páginas operativas
│   │       ├── CashRegisters.jsx
│   │       ├── CashClosings.jsx
│   │       ├── Orders.jsx             # Página más compleja (~1000 LOC)
│   │       └── Sales.jsx
│   │
│   ├── App.jsx                        # Router con ProtectedRoute per-role
│   ├── main.jsx                       # Entry point + Providers
│   └── index.css                      # Tailwind + custom CSS
│
├── package.json
├── tailwind.config.js
├── postcss.config.js
└── vite.config.js
```

---

## 4. FLUJO DE DATOS

### 4.1 Flujo HTTP Completo (Request → Response)

```
Browser                    Frontend                          Backend
  │                          │                                 │
  │   Click/Submit ──────►   │                                 │
  │                          │  api.post('/api/orders', data)  │
  │                          │  + Bearer Token header          │
  │                          │  + X-Restaurant-Id header       │
  │                          │ ──────────────────────────────► │
  │                          │                                 │  NormalizeApiResponse
  │                          │                                 │  SecurityHeaders  
  │                          │                                 │  auth:sanctum (Sanctum)
  │                          │                                 │  set.restaurant (verify belongs)
  │                          │                                 │  [financial.initialized] (optional)
  │                          │                                 │
  │                          │                                 │  Controller:
  │                          │                                 │    FormRequest validates
  │                          │                                 │    $this->authorize(Policy)
  │                          │                                 │    $service->method()
  │                          │                                 │
  │                          │                                 │  Service:
  │                          │                                 │    Business rule validation
  │                          │                                 │    DB::transaction {
  │                          │                                 │      Model::create/update
  │                          │                                 │      FinancialMovement::create
  │                          │                                 │      AuditService::log
  │                          │                                 │    }
  │                          │                                 │
  │                          │  ◄──────── JSON Response ────── │
  │                          │                                 │
  │                          │  useCrud / setState / toast      │
  │  ◄── Re-render UI ────   │                                 │
```

### 4.2 Flujo Operativo del Día (Business Flow)

```
 ╔═══════════════════════════════════════════════════════════╗
 ║              FLUJO OPERATIVO DIARIO                       ║
 ╠═══════════════════════════════════════════════════════════╣
 ║                                                           ║
 ║  [1] APERTURA DE CAJA                                     ║
 ║   │  → Requiere: no existe caja hoy, no hay cierre        ║
 ║   │    contable hoy, monto ≥ cierre anterior,             ║
 ║   │    monto ≤ saldo cuenta cash                          ║
 ║   │                                                       ║
 ║   ▼                                                       ║
 ║  [2] OPERACIONES DEL DÍA                                  ║
 ║   │                                                       ║
 ║   ├── Pedidos ───► Crear → Agregar ítems → Cerrar         ║
 ║   │                                                       ║
 ║   ├── Ventas ────► Cobrar pedido cerrado                  ║
 ║   │                → Genera FinancialMovement(income)      ║
 ║   │                → Genera receipt_number                 ║
 ║   │                                                       ║
 ║   ├── Gastos ────► Registrar egreso                       ║
 ║   │                → Pagar (parcial o total)               ║
 ║   │                → Genera FinancialMovement(expense)     ║
 ║   │                                                       ║
 ║   └── Transferencias → Entre cuentas del restaurante      ║
 ║                → Genera 2 FinancialMovements               ║
 ║                  (transfer_out + transfer_in)              ║
 ║   │                                                       ║
 ║   ▼                                                       ║
 ║  [3] CIERRE DE CAJA                                       ║
 ║   │  → Requiere: no hay órdenes abiertas/cerradas         ║
 ║   │  → Calcula diferencia (real − esperado)               ║
 ║   │                                                       ║
 ║   ▼                                                       ║
 ║  [4] CIERRE CONTABLE                                      ║
 ║      → Requiere: caja cerrada, no hay órdenes open        ║
 ║      → Calcula: total_sales, total_expenses, net_total     ║
 ║      → ⚠️ CONGELA la fecha — ninguna operación            ║
 ║        retroactiva posible                                ║
 ║                                                           ║
 ╚═══════════════════════════════════════════════════════════╝
```

### 4.3 Flujo de Saldo Financiero

```
                    ┌──────────────────────────────┐
                    │    financial_movements        │
                    │                              │
   Sale Payment ──► │  type: income       (+)      │
                    │  type: initial_balance (+)    │
   Transfer In ──►  │  type: transfer_in  (+)      │
                    │ ─────────────────────────── │
   Expense Pay ──►  │  type: expense      (−)      │
   Transfer Out ──► │  type: transfer_out (−)      │
                    └──────────────┬───────────────┘
                                   │
                    Balance = Σ(+) − Σ(−)
                                   │
                    ┌──────────────▼───────────────┐
                    │  Cálculo dinámico en runtime  │
                    │  (NO se almacena campo)       │
                    │  FinancialAccountService::    │
                    │    getAccountBalance()        │
                    └──────────────────────────────┘
```

---

## 5. MODELO DE DATOS

### 5.1 Mapa de Entidades (26 tablas de negocio)

```
┌─── Núcleo ────────────────────────────────────────────────┐
│  restaurants ←─┬── restaurant_user (pivot) ──► users      │
│                │        └── role_id ──► roles             │
│                │                                          │
│                ├── tables                                 │
│                ├── product_categories ── products         │
│                ├── suppliers                              │
│                ├── expense_categories                     │
│                └── payment_methods                        │
└───────────────────────────────────────────────────────────┘

┌─── Operaciones ───────────────────────────────────────────┐
│  orders ──┬── order_items ──► products (snapshot)         │
│           └── table_id ──► tables                         │
│                                                           │
│  sales ──┬── order_id (1:1 UNIQUE)                       │
│          ├── cash_register_id                             │
│          └── sale_payments ──► payment_methods            │
│                           └──► financial_accounts         │
│                                                           │
│  cash_registers (1 por restaurante por día)               │
│  cash_closings  (sella la fecha inmutablemente)           │
└───────────────────────────────────────────────────────────┘

┌─── Finanzas ──────────────────────────────────────────────┐
│  financial_accounts (cash | digital | bank)               │
│       └── financial_movements (ledger inmutable)          │
│                                                           │
│  account_transfers ──► from_account + to_account          │
│                    └── created_by ──► users               │
└───────────────────────────────────────────────────────────┘

┌─── Gastos ────────────────────────────────────────────────┐
│  expenses ──┬── expense_payments                         │
│             ├── expense_audits (trazabilidad campo)       │
│             ├── expense_attachments                       │
│             ├── expense_status_id ──► expense_statuses    │
│             └── supplier_id ──► suppliers (nullable)      │
└───────────────────────────────────────────────────────────┘

┌─── Auditoría ─────────────────────────────────────────────┐
│  audit_logs (acciones sobre entidades críticas)           │
│  waste_logs (mermas de productos)                         │
└───────────────────────────────────────────────────────────┘
```

### 5.2 Restricciones UNIQUE Relevantes

| Tabla | Constraint | Propósito |
|-------|-----------|-----------|
| `restaurants` | `UNIQUE(ruc)` | Un RUC fiscal por restaurante |
| `users` | `UNIQUE(email)` | Un email por usuario global |
| `roles` | `UNIQUE(slug)` | Catálogo inmutable de roles |
| `restaurant_user` | `UNIQUE(restaurant_id, user_id)` | Un rol por usuario por restaurante |
| `products` | `UNIQUE(restaurant_id, name)` | Nombre único por restaurante |
| `product_categories` | `UNIQUE(restaurant_id, name)` | Nombre único por restaurante |
| `tables` | `UNIQUE(restaurant_id, number)` | Número de mesa único por restaurante |
| `financial_accounts` | `UNIQUE(restaurant_id, name)` | Nombre de cuenta único por restaurante |
| `sales` | `UNIQUE(order_id)` | Una venta por pedido |
| `sales` | `UNIQUE(receipt_number)` | Comprobante único global |
| `cash_registers` | `UNIQUE(restaurant_id, date)` | Una caja por restaurante por día |
| `cash_closings` | `UNIQUE(restaurant_id, date)` | Un cierre por restaurante por día |
| `expense_categories` | `UNIQUE(restaurant_id, name)` | Nombre único por restaurante |
| `expense_statuses` | `UNIQUE(slug)` | Catálogo: pending, paid, cancelled |

---

## 6. ENDPOINTS Y RUTAS CRÍTICAS

### 6.1 Mapa Completo de Endpoints (~60 rutas)

> Todas las rutas usan prefijo `/api/`. Middleware base: `auth:sanctum` + `set.restaurant`.

#### 🔑 Autenticación (sin restaurant context)

| Método | Ruta | Acción | Throttle |
|--------|------|--------|----------|
| `POST` | `/login` | Login con email/password → token Bearer | `throttle:login` |
| `POST` | `/logout` | Revocar token | — |
| `GET` | `/me` | Usuario autenticado + restaurantes + roles | — |

#### 📦 Catálogos (sin restaurant context)

| Método | Ruta | Acción |
|--------|------|--------|
| `GET` | `/catalogs/roles` | Listado de roles |
| `GET` | `/catalogs/payment-methods` | Métodos de pago |
| `GET` | `/catalogs/expense-statuses` | Estados de gasto |

#### 🍽️ Pedidos (14 actions)

| Método | Ruta | Acción | Guard |
|--------|------|--------|-------|
| `GET` | `/orders` | Listado filtrable (status, channel, table) | viewAny |
| `POST` | `/orders` | Crear pedido + ítems iniciales | create |
| `GET` | `/orders/{id}` | Detalle con ítems, mesa, usuario | view |
| `POST` | `/orders/{id}/items` | Agregar ítem (solo status=open) | update |
| `DELETE` | `/orders/{id}/items/{item}` | Quitar ítem (solo status=open) | update |
| `PATCH` | `/orders/{id}/items/{item}/quantity` | Cambiar cantidad | update |
| `POST` | `/orders/{id}/discount` | Aplicar descuento % (solo closed) | applyDiscount |
| `POST` | `/orders/{id}/close` | Cerrar pedido (listo para cobrar) | close |
| `POST` | `/orders/{id}/reopen` | Reabrir pedido cerrado | close |
| `POST` | `/orders/{id}/cancel` | Cancelar + motivo (5+ chars) | cancel |
| `GET` | `/orders/{id}/kitchen-ticket` | Ticket de cocina (texto) | kitchenTicket |
| `GET` | `/orders/{id}/bill` | Pre-cuenta para cliente | view |
| `PATCH` | `/orders/{id}/change-table` | Cambiar mesa | update |
| `POST` | `/orders/{id}/pay` | **Cobrar → genera Sale + Movements** | pay + `financial.initialized` |

#### 💰 Ventas

| Método | Ruta | Acción |
|--------|------|--------|
| `GET` | `/sales` | Listado paginado |
| `GET` | `/sales/summary` | Resumen de ventas del día |
| `GET` | `/sales/{id}` | Detalle con pagos |
| `GET` | `/sales/{id}/receipt` | Boleta/recibo en texto |

#### 💸 Gastos

| Método | Ruta | Acción | Notas |
|--------|------|--------|-------|
| `GET/POST` | `/expenses` | CRUD estándar | Requiere caja abierta en expense_date |
| `GET/PUT/DELETE` | `/expenses/{id}` | CRUD | Bloqueo por cierre contable |
| `POST` | `/expenses/{id}/payments` | Registrar pago | `financial.initialized` |
| `GET/POST/DELETE` | `/expenses/{id}/attachments` | Adjuntos | — |

#### 🏦 Módulo Financiero

| Método | Ruta | Acción |
|--------|------|--------|
| `GET/POST` | `/financial-accounts` | CRUD cuentas financieras |
| `GET` | `/financial-accounts/balances` | Saldos consolidados |
| `GET/PUT/DELETE` | `/financial-accounts/{id}` | CRUD + desactivación con validación de saldo |
| `GET` | `/financial-movements` | Listado de todos los movimientos |
| `GET/POST` | `/account-transfers` | CRUD transferencias |
| `PUT` | `/account-transfers/{id}` | Editar (máx 5 días) · `financial.initialized` |
| `DELETE` | `/account-transfers/{id}` | Eliminar (solo admin_general) |
| `GET` | `/financial/status` | Estado de inicialización |
| `POST` | `/financial/initialize` | Inicializar cuentas (one-time) |

#### 🏪 Caja y Cierre

| Método | Ruta | Acción |
|--------|------|--------|
| `GET` | `/cash-registers` | Historial de cajas |
| `GET` | `/cash-registers/current` | Caja actual |
| `POST` | `/cash-registers` | Abrir caja · `financial.initialized` |
| `POST` | `/cash-registers/{id}/close` | Cerrar caja · `financial.initialized` |
| `GET` | `/cash-registers/{id}/x-report` | Reporte X (parcial) |
| `GET` | `/cash-closings` | Historial de cierres |
| `GET` | `/cash-closings/preview` | Vista previa |
| `POST` | `/cash-closings` | Ejecutar cierre contable |
| `GET` | `/cash-closings/{id}` | Detalle |

#### 📊 Reportes (10 análisis)

| Ruta | Reporte |
|------|---------|
| `/reports/sales-by-category` | Ventas agrupadas por categoría |
| `/reports/sales-by-hour` | Distribución horaria |
| `/reports/cancellations-discounts` | Cancelaciones y descuentos |
| `/reports/sales-by-waiter` | Rendimiento por mesero |
| `/reports/food-cost` | Costo de alimentos |
| `/reports/waste` | Mermas |
| `/reports/accounts-payable` | Cuentas por pagar |
| `/reports/daily-cash-flow` | Flujo de caja diario |
| `/reports/top-products` | Productos más vendidos |
| `/reports/daily-summary` | Resumen ejecutivo del día |

Middleware: `throttle:reports` para prevención de abuso.

#### 🛠️ Administración (CRUD estándar)

| Prefijo | Recurso | Acciones |
|---------|---------|----------|
| `/product-categories` | Categorías de producto | CRUD + soft delete |
| `/products` | Productos | CRUD + restore + toggle-active |
| `/tables` | Mesas | CRUD + restore + update positions |
| `/suppliers` | Proveedores | CRUD |
| `/expense-categories` | Categorías de gasto | CRUD |
| `/payment-methods` | Métodos de pago | CRUD |
| `/users` | Usuarios del restaurante | CRUD + reset-password |
| `/waste-logs` | Registro de mermas | CRUD |
| `/audit-logs` | Logs de auditoría | Solo lectura (GET) |
| `/dashboard` | Dashboard admin | Resumen general |
| `/dashboard/waiter` | Dashboard mozo | Vista del mesero |

---

## 7. CAPA DE SERVICIOS — LÓGICA DE NEGOCIO

### 7.1 Inventario de Servicios (14)

| Servicio | Responsabilidad | Complejidad |
|----------|----------------|-------------|
| `OrderService` | Ciclo de vida completo del pedido (create, addItem, removeItem, close, reopen, cancel, discount, changeTable) | 🔴 Alta |
| `SaleService` | Cobro de pedido, generación de receipt, movimientos financieros | 🔴 Alta |
| `CashRegisterService` | Apertura/cierre de caja, cálculo de diferencia | 🟡 Media |
| `CashClosingService` | Ejecución del cierre contable, cálculos de totales | 🟡 Media |
| `CashValidationService` | **Árbitro central** — hasClosing, isBeforeOrOnLastClosing, canMarkAsPaid, canRegisterPaymentOnDate | 🔴 Alta |
| `ExpenseService` | CRUD de gastos con auditoría campo-a-campo | 🟡 Media |
| `ExpensePaymentService` | Pagos de gastos con validaciones de fecha/saldo | 🟡 Media |
| `ExpenseAuditService` | Logging de cambios en campos de gastos | 🟢 Baja |
| `AccountTransferService` | Transferencias inter-cuenta con par de movimientos | 🟡 Media |
| `FinancialAccountService` | CRUD de cuentas + cálculo dinámico de saldo | 🟡 Media |
| `FinancialMovementService` | Creación programática de movimientos (income, expense, transfer) | 🟡 Media |
| `FinancialInitializationService` | Inicialización one-time de saldos | 🟢 Baja |
| `ReportService` | 10 queries analíticas complejas | 🟡 Media |
| `KitchenTicketService` / `ReceiptService` | Generación de texto formateado | 🟢 Baja |
| `AuditService` | Registro en audit_logs | 🟢 Baja |

### 7.2 Servicio Central: CashValidationService

Este servicio es invocado por múltiples módulos como **validador transversal**:

```
CashValidationService
    │
    ├── hasClosing(restaurantId, date)
    │     Invocado por: SaleService, CashRegisterService,
    │                   AccountTransferService
    │
    ├── isBeforeOrOnLastClosing(restaurantId, date)
    │     Invocado por: ExpenseService, ExpensePaymentService
    │
    ├── isExpenseLocked(expense)
    │     Invocado por: ExpenseService, ExpensePaymentService
    │
    ├── canMarkAsPaid(expense)
    │     Invocado por: ExpenseService (update → status=paid)
    │
    ├── canRegisterPaymentOnDate(restaurantId, date)
    │     Invocado por: ExpenseService (create),
    │                   ExpensePaymentService (registerPayment)
    │
    └── getLastClosingDate(restaurantId)
          Invocado por: ExpenseService, ExpensePaymentService
```

---

## 8. SISTEMA DE ROLES Y AUTORIZACIÓN

### 8.1 Roles Disponibles

| Slug | Nombre | Alcance |
|------|--------|---------|
| `admin_general` | Superadministrador | Acceso total, gestión multi-restaurante, eliminación de transferencias |
| `admin_restaurante` | Admin de restaurante | Gestión completa de su restaurante |
| `caja` | Cajero | Caja, cobros, pagos de gastos, transferencias (crear, no eliminar) |
| `mozo` | Mesero | Solo pedidos propios, sin acceso financiero |
| `cocina` | Cocina | Visualización de pedidos, registro de mermas |

### 8.2 Implementación

Las policies están registradas en `AuthServiceProvider` y se ejecutan via `$this->authorize()` en cada controller.

**Restricción especial del mozo:** Desde la última actualización, el mozo solo puede interactuar con **sus propios pedidos** (`order.user_id === user.id`). Los administradores pueden interactuar con cualquier pedido.

---

## 9. SEGURIDAD

### 9.1 Autenticación

| Mecanismo | Implementación |
|-----------|---------------|
| Token Bearer | Laravel Sanctum — `personal_access_tokens` |
| Hash de passwords | Bcrypt con 12 rounds |
| Throttling de login | `throttle:login` (rate limiting) |
| Expiración de sesión | 120 minutos (configurable) |

### 9.2 Cabeceras de Seguridad (Middleware `SecurityHeaders`)

| Header | Valor | Protección |
|--------|-------|------------|
| `X-Content-Type-Options` | `nosniff` | MIME sniffing |
| `X-Frame-Options` | `DENY` | Clickjacking |
| `X-XSS-Protection` | `1; mode=block` | XSS reflejado |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Information leakage |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | Force HTTPS |

### 9.3 CORS

```php
'allowed_origins' => env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,...')
'allowed_methods' => ['*']
'allowed_headers' => ['*']
'supports_credentials' => false  // Bearer token, no cookies
```

### 9.4 Controles Anti-Manipulación

| Control | Descripción |
|---------|-------------|
| **Cierre contable congela fecha** | Una vez ejecutado el `CashClosing`, ninguna venta, gasto, transferencia o apertura de caja puede registrarse para esa fecha |
| **Fechas bloqueadas antes del último cierre** | Gastos y pagos de gastos no pueden registrarse con fecha anterior o igual al último cierre contable |
| **Caja registradora obligatoria** | No se puede operar sin registro físico de caja abierta para la fecha |
| **Edición temporal de transferencias** | Solo editables hasta 5 días después de creación |
| **Eliminación restringida de transferencias** | Solo `admin_general` |
| **Saldo dinámico** | No existe campo `balance` — se calcula sumando `financial_movements`. Imposible de alterar directamente |
| **Desactivación protegida de cuentas** | No se puede desactivar una cuenta con saldo > 0 |
| **Auditoría inmutable** | `financial_movements` no tiene endpoints de escritura |

### 9.5 Validación de Datos

- **20 Form Requests** con reglas de validación Laravel
- **21+ BusinessExceptions** tipadas por dominio con HTTP status codes semánticos
- **DB-level constraints** (UNIQUE, FK CASCADE, NOT NULL) como respaldo

---

## 10. TESTING

### 10.1 Cobertura

| Métrica | Valor |
|---------|-------|
| Total de tests | **292** |
| Total de assertions | **564** |
| Archivos de test | **26** |
| Estado | ✅ **Suite verde** |

### 10.2 Tests Destacados

| Test File | Tests | Categoría |
|-----------|-------|-----------|
| `FinancialAuditTest.php` | 42 | Reglas financieras exhaustivas |
| `FullDayFlowTest.php` | 1 (E2E) | Flujo completo de un día operativo |
| `MultiTenantIsolationTest.php` | N | Aislamiento de datos entre tenants |
| `OrderTest.php` | N | Ciclo de vida de pedidos |
| `SaleTest.php` | N | Cobros y pagos múltiples |
| `CashClosingTest.php` | N | Cierre contable y bloqueos |
| `RolesPermissionsTest.php` | N | Matriz de permisos por rol |

### 10.3 Trait Reutilizable

`SetUpRestaurant` — crea restaurante, 3 usuarios (admin, caja, mozo), roles, método de pago, cuentas financieras, inicialización — todo listo para testear.

---

## 11. FRONTEND — SPA REACT

### 11.1 Arquitectura Frontend

| Concepto | Implementación |
|----------|---------------|
| State management | React Context (AuthContext) — no Redux |
| Routing | react-router-dom v6 con `ProtectedRoute` per-role |
| API layer | Axios instance con interceptors (token, restaurant_id, errores) |
| CRUD genérico | Hook `useCrud` — elimina boilerplate en páginas admin |
| Notifications | react-hot-toast |
| UI Kit | Componentes propios: DataTable, Modal, ConfirmDialog, Pagination |
| Estilos | TailwindCSS 3.4 + CSS custom (variables, badges) |

### 11.2 Mapa de Rutas del Frontend

| Ruta | Componente | Roles |
|------|-----------|-------|
| `/login` | Login | Pública |
| `/dashboard` | Dashboard | Todos |
| `/orders` | Orders | admin, caja, mozo |
| `/cash-registers` | CashRegisters | admin, caja |
| `/sales` | Sales | admin, caja |
| `/cash-closings` | CashClosings | admin, caja |
| `/products` | Products | admin |
| `/product-categories` | ProductCategories | admin |
| `/tables` | Tables | admin |
| `/suppliers` | Suppliers | admin |
| `/expenses` | Expenses | admin |
| `/expense-categories` | ExpenseCategories | admin |
| `/payment-methods` | PaymentMethods | admin |
| `/users` | Users | admin |
| `/waste-logs` | WasteLogs | admin, cocina |
| `/reports` | Reports | admin |
| `/audit-logs` | AuditLogs | admin |
| `/kitchen` | KitchenDisplay | admin, cocina |
| `/financial-accounts` | FinancialAccounts | admin |
| `/account-transfers` | AccountTransfers | admin, caja |
| `/financial-dashboard` | FinancialDashboard | admin |
| `/financial-initialization` | FinancialInitialization | admin |

---

## 12. MEJORAS FUTURAS

### 🔴 Prioridad Alta

| # | Mejora | Justificación |
|---|--------|--------------|
| 1 | **Rate limiting granular por endpoint** | Actualmente solo `throttle:login` y `throttle:reports`. Endpoints de escritura financiera deberían tener rate limiting propio. |
| 2 | **Logging estructurado con correlación** | Implementar request IDs para rastrear un flujo completo en logs. Actualmente `LOG_CHANNEL=stack` con configuración por defecto. |
| 3 | **Variables de entorno sensibles** | La contraseña de BD (`DB_PASSWORD=1QAZ2XSW3EDC`) está en `.env` versionado. Mover a vault o secrets manager. El `.env` no debe estar en el repositorio. |
| 4 | **`.env.example` actualizado** | El `.env.example` tiene `DB_CONNECTION=sqlite` mientras producción usa MySQL. Alinear con la configuración real. |

### 🟡 Prioridad Media

| # | Mejora | Justificación |
|---|--------|--------------|
| 5 | **Validación de saldo en pagos de gastos** | Al registrar un pago de gasto con `financial_account_id`, no se verifica que la cuenta tenga saldo suficiente (a diferencia de transferencias que sí lo validan). |
| 6 | **API Resources / Transformers** | Los controllers retornan modelos Eloquent directamente. Implementar `JsonResource` / `ResourceCollection` para control explícito de la serialización y evitar exposición accidental de campos. |
| 7 | **Websockets para cocina** | `KitchenDisplay` usa polling. Migrar a Laravel Echo + Pusher/Reverb para notificaciones en tiempo real. |
| 8 | **Paginación en endpoints de listado** | Algunos endpoints (`financial-accounts`, `financial-movements`) no implementan paginación, pudiendo resultar en payloads grandes. |
| 9 | **Auditoría de accesos fallidos** | Registrar intentos de acceso 403 en `audit_logs` para detección de abusos. |

### 🟢 Prioridad Baja

| # | Mejora | Justificación |
|---|--------|--------------|
| 10 | **Containerización con Docker** | Simplificar despliegue y onboarding. Actualmente requiere instalación manual de PHP, MySQL, Node. |
| 11 | **CI/CD Pipeline** | Automatizar ejecución de `php artisan test` en push/PR. |
| 12 | **Internacionalización (i18n)** | El sistema está en español hardcoded. Migrar mensajes a archivos de traducción para soporte multiidioma futuro. |
| 13 | **Soft delete en transferencias** | Actualmente el delete es hard delete. Implementar soft deletes para trazabilidad completa. |
| 14 | **Export de reportes a Excel/PDF** | Complementar los reportes de API con generación de archivos descargables. |

---

> **Documento generado:** 6 de marzo de 2026 · v3.1  
> **Cobertura:** Backend completo + Frontend completo  
> **Próxima revisión sugerida:** Tras implementación de mejoras de prioridad alta
