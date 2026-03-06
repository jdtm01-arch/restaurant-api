# INFORME INICIAL DE PROYECTO
## Sistema de Gestión Interna – Restaurante "Tu Restaurante"

---

# 1. Información General

**Nombre del Proyecto:** Sistema de Gestión Interna para Restaurante  
**Cliente:** Restaurante "Tu Restaurante"  
**Tipo de Implementación:** Sistema web interno en red local  
**Modalidad:** Producto único (no SaaS)  

---

# 2. Objetivo del Proyecto

Desarrollar un sistema digital interno que permita al restaurante optimizar la gestión de pedidos, ventas y gastos, reemplazando el uso actual de comandas en papel y mejorando el control diario de caja.

El sistema debe ser:

- Funcional
- Rápido
- Intuitivo
- Operativo sin conexión a internet
- Escalable a futuro si se requiere

---

# 3. Problemática Actual

Actualmente el restaurante:

- Registra pedidos en papel.
- No cuenta con historial digital de ventas.
- No posee control estructurado de gastos.
- Realiza cierre de caja manual.
- No dispone de reportes automáticos.

Esto genera:

- Riesgo de errores humanos.
- Pérdida de información histórica.
- Dificultad para análisis financiero.
- Falta de trazabilidad.

---

# 4. Alcance Funcional

El sistema incluirá los siguientes módulos:

## 4.1 Autenticación y Roles
- Inicio de sesión seguro.
- Gestión de usuarios.
- Roles: Administración, Mozo, Cocina.
- Control de acceso por módulo.

## 4.2 Gestión de Mesas
- Visualización gráfica de mesas.
- Estado: Libre / Ocupada / Cerrada.
- Asignación de pedido por mesa.

## 4.3 Gestión de Pedidos (Comandas)
- Creación de pedidos.
- Agregar, editar y eliminar productos.
- Estados de pedido: Pendiente / En preparación / Listo / Cancelado.
- Envío de pedidos a cocina.

## 4.4 Panel de Cocina
- Visualización de pedidos pendientes.
- Cambio de estado a "En preparación" y "Listo".

## 4.5 Gestión de Pagos
- Registro de pagos en efectivo, Yape y Plin.
- Cierre automático de mesa tras pago.

## 4.6 Registro de Gastos
- Registro manual de gastos.
- Clasificación por categoría.
- Consulta por fecha.

## 4.7 Cierre de Caja
- Resumen de ventas del día.
- Total por método de pago.
- Total de gastos.
- Resultado final.
- Registro de responsable.

## 4.8 Reportes
- Ventas por fecha.
- Resumen mensual.
- Productos más vendidos.

---

# 5. Reglas de Negocio Principales

- No se puede cerrar una mesa sin registrar pago.
- Una mesa solo puede tener un pedido activo.
- Solo administración puede cerrar caja.
- No se pueden registrar ventas después del cierre diario.
- Cancelaciones de pedidos preparados deben registrarse como pérdida.

---

# 6. Infraestructura Técnica

- Instalación en computadora local del restaurante.
- Acceso mediante red interna.
- Base de datos local.
- Funcionamiento sin dependencia de internet.
- Respaldo manual periódico.

---

# 7. Beneficios Esperados

- Reducción de errores en comandas.
- Mayor control financiero diario.
- Registro histórico de ventas y gastos.
- Mejora en organización interna.
- Base tecnológica para futuras mejoras.

---

# 8. Exclusiones Iniciales

El sistema no incluirá en esta fase:

- Facturación electrónica.
- Integración con delivery.
- Control automático de inventario.
- Acceso remoto.

Estas funcionalidades podrán evaluarse en versiones futuras.

---

# 9. Próximos Pasos

1. Validación del alcance con el cliente.
2. Diseño del modelo de base de datos.
3. Planificación técnica detallada.
4. Inicio del desarrollo.

---

**Documento versión 1.0**  
Fecha: 27-02-2026

