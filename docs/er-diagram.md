# Diagrama ER — Base de datos

El siguiente diagrama ER está en formato Mermaid. Puedes visualizarlo en cualquier editor que soporte Mermaid o convertirlo a PNG/SVG usando herramientas como `mmdc` (Mermaid CLI) o la extensión Mermaid Live Preview.

```mermaid
erDiagram
    RESTAURANTS ||--o{ RESTAURANT_USER : has
    USERS ||--o{ RESTAURANT_USER : belongs_to
    ROLES ||--o{ RESTAURANT_USER : has

    RESTAURANTS ||--o{ FINANCIAL_ACCOUNTS : has
    FINANCIAL_ACCOUNTS ||--o{ FINANCIAL_MOVEMENTS : has
    FINANCIAL_ACCOUNTS ||--o{ CASH_REGISTERS : has
    FINANCIAL_ACCOUNTS ||--o{ ACCOUNT_TRANSFERS : from_account
    FINANCIAL_ACCOUNTS ||--o{ ACCOUNT_TRANSFERS_TO : to_account

    FINANCIAL_MOVEMENTS }o--|| RESTAURANTS : belongs_to
    FINANCIAL_MOVEMENTS }o--|| USERS : created_by

    CASH_REGISTERS }o--|| RESTAURANTS : belongs_to
    CASH_REGISTERS }o--|| USERS : opened_by
    CASH_REGISTERS }o--|| USERS : closed_by
    CASH_REGISTERS ||--o{ SALES : has

    SALES }o--|| ORDERS : references
    SALES }o--|| CASH_REGISTERS : cash_register
    SALES ||--o{ SALE_PAYMENTS : has
    SALE_PAYMENTS }o--|| PAYMENT_METHODS : payment_method
    SALE_PAYMENTS }o--|| FINANCIAL_ACCOUNTS : financial_account

    ORDERS ||--o{ ORDER_ITEMS : has
    ORDER_ITEMS }o--|| PRODUCTS : product
    ORDERS }o--|| TABLES : table

    EXPENSE_CATEGORIES ||--o{ EXPENSES : has
    EXPENSES }o--|| RESTAURANTS : belongs_to
    EXPENSES }o--|| USERS : user
    EXPENSES ||--o{ EXPENSE_PAYMENTS : has
    EXPENSE_PAYMENTS }o--|| PAYMENT_METHODS : payment_method
    EXPENSE_PAYMENTS }o--|| FINANCIAL_ACCOUNTS : financial_account
    EXPENSES ||--o{ EXPENSE_ATTACHMENTS : has
    EXPENSES ||--o{ EXPENSE_AUDITS : has

    PRODUCT_CATEGORIES ||--o{ PRODUCTS : has
    SUPPLIERS ||--o{ EXPENSES : supplies

    ACCOUNT_TRANSFERS }o--|| RESTAURANTS : belongs_to
    ACCOUNT_TRANSFERS }o--|| USERS : created_by

    AUDIT_LOGS }o--|| RESTAURANTS : restaurant
    AUDIT_LOGS }o--|| USERS : user

    USERS ||--o{ PERSONAL_ACCESS_TOKENS : owns

    SESSIONS }o--|| USERS : user

    -- Notes --
    MIGRATIONS : tracks_schema_version
    FAILED_JOBS : background_errors
    JOBS : queue_entries
    CACHE : key_value_store

    %% PK/FK highlights (selected):
    %% restaurants.id PK
    %% users.id PK
    %% financial_accounts.id PK, restaurant_id FK -> restaurants.id
    %% financial_movements.financial_account_id FK -> financial_accounts.id
    %% cash_registers.financial_account_id FK -> financial_accounts.id
    %% sales.cash_register_id FK -> cash_registers.id
    %% sale_payments.financial_account_id FK -> financial_accounts.id
    %% expense_payments.financial_account_id FK -> financial_accounts.id
    %% account_transfers.from_account_id / to_account_id -> financial_accounts.id
```
