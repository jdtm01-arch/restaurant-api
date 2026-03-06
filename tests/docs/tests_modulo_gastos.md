# DOCUMENTACIÓN COMPLETA – TESTS MÓDULO DE GASTOS

## Contexto
Este documento describe TODO lo que se realizó en el módulo de gastos respecto a validaciones financieras, arquitectura aplicada, decisiones tomadas y propósito de los tests.

Se desarrollaron pruebas automatizadas (Feature Tests) para blindar las reglas financieras críticas relacionadas al cambio de estado de un gasto a "paid".

---

# 1. Problema Inicial Detectado

Al ejecutar los tests iniciales:

php artisan test --filter=ExpenseServiceTest

Se detectaron:

- Errores 422 por validaciones faltantes
- Errores 500 por lanzar Exception genérica
- Estado del gasto no cambiaba correctamente

Esto reveló que:
- Faltaban datos requeridos en update
- La lógica financiera no estaba correctamente protegida
- Se estaban lanzando Exception en lugar de ValidationException

---

# 2. Decisión Arquitectónica

Se decidió:

A) Mantener el método update general
B) Enviar todos los campos requeridos en los tests
C) Ajustar la excepción a ValidationException

No se refactorizó aún la arquitectura hacia modelo rico (Expense::canBeMarkedAsPaid()), pero se documentó como mejora futura.

---

# 3. Reglas Financieras Implementadas

Se blindaron las siguientes invariantes:

INVARIANTE PRINCIPAL:

Un gasto solo puede estar en estado "paid" si la suma de sus pagos es exactamente igual a su monto.

---

# 4. Escenarios Testeados

## 4.1 No permite marcar como paid sin pagos

- Se crea gasto en estado pending
- No se registran pagos
- Se intenta cambiar a paid
- Resultado esperado: 400 inicialmente, luego ajustado a 422
- Estado debe permanecer pending

Resultado final: PASS

---

## 4.2 Permite marcar como paid con suma exacta

- Gasto por 100
- Pago por 100
- Se cambia a paid
- Debe responder 200
- Debe actualizar estado en DB

Resultado final: PASS

---

## 4.3 No permite marcar como paid con suma menor

- Gasto por 100
- Pago por 50
- Intento de marcar como paid
- Debe responder 422
- Estado debe permanecer pending

Inicialmente devolvía 500 porque se lanzaba Exception.
Se corrigió usando ValidationException::withMessages().

Resultado final: PASS

---

## 4.4 No permite marcar como paid con suma mayor

- Gasto por 100
- Pago por 150
- Intento de marcar como paid
- Debe responder 422
- Estado debe permanecer pending

Resultado final: PASS

---

## 4.5 Setea paid_at cuando se marca como paid

- Gasto por 200
- Pago exacto por 200
- Cambio a paid
- Debe responder 200
- Campo paid_at no debe ser null

Resultado final: PASS

---

# 5. Corrección Crítica Realizada

ANTES:

throw new Exception("La suma de pagos debe ser exactamente igual al monto del gasto.");

Esto generaba error 500.

DESPUÉS:

throw ValidationException::withMessages([
    'amount' => ['La suma de pagos debe ser exactamente igual al monto del gasto.']
]);

Esto genera correctamente 422 Unprocessable Entity.

---

# 6. Tipo de Tests Realizados

Se realizaron Feature Tests.

Esto significa que se probó el flujo completo:

HTTP Request → Middleware → Controller → Service → Base de Datos → HTTP Response

No son tests unitarios aislados.

---

# 7. Utilidad Real de Estos Tests

Estos tests sirven como:

- Contrato automático del sistema
- Protección ante refactors futuros
- Seguro contra descuadres contables
- Mecanismo de bloqueo en CI/CD antes de deploy
- Documentación viva del comportamiento esperado

---

# 8. Cuándo Se Usan

1) Antes de hacer refactors
2) Antes de hacer deploy
3) Cuando se agregan nuevas funcionalidades
4) En pipelines automáticos

Comando general:

php artisan test

---

# 9. Decisión de No Refactorizar Arquitectura (Por Ahora)

Se identificó oportunidad de mejora:

Mover la regla de validación al modelo Expense como método de dominio:

public function canBeMarkedAsPaid(): bool

Pero se decidió:

- No refactorizar en esta versión
- Documentar como mejora futura
- Priorizar estabilidad funcional

---

# 10. Estado Final del Módulo

El módulo ahora garantiza:

- No se puede pagar sin pagos
- No se puede pagar con suma menor
- No se puede pagar con suma mayor
- Solo se puede pagar con suma exacta
- Se registra paid_at
- Devuelve códigos HTTP correctos
- Está protegido por tests automatizados

---

# 11. Conclusión Técnica

Se logró:

- Blindaje financiero básico consistente
- Correcto manejo de excepciones HTTP
- Protección contra regresiones futuras
- Base sólida para futuras mejoras (partial payments, reversión, estados adicionales)

Este documento deja constancia de:

Qué se hizo
Por qué se hizo
Cómo funciona
Qué se decidió no hacer aún

Fin de documentación.