# Project Charter

Acta de constitución del proyecto. Fija la visión, los objetivos, el alcance, las restricciones y los criterios de éxito. Es el documento de referencia para decidir si una funcionalidad entra o no en el proyecto; los detalles de cada tema están en los documentos enlazados.

## Visión

Construir un sistema web de gestión de depósitos (WMS) que administre las operaciones de una empresa con uno o varios depósitos, desde la orden de compra hasta el despacho, con una garantía central: **el inventario registrado coincide siempre con la historia de movimientos que lo produjo**, aun cuando varios operadores trabajan al mismo tiempo sobre los mismos productos y ubicaciones.

## Problema

Un depósito necesita responder en todo momento cinco preguntas:

1. Qué mercadería existe.
2. Dónde está físicamente cada producto.
3. Cómo llegó a esa ubicación y qué movimientos tuvo.
4. Quién hizo cada operación y con qué autorización.
5. Cuánto está disponible, una vez descontado lo reservado y lo bloqueado.

Los sistemas de inventario simples fallan en estas respuestas cuando hay concurrencia o errores operativos: stock negativo por dos retiros simultáneos, operaciones duplicadas por reintentos, transferencias que descuentan en el origen sin acreditar en el destino, historial alterado y correcciones que se hacen editando datos en lugar de registrar una operación compensatoria.

## Objetivos

### Objetivos de negocio

| ID | Objetivo | Cómo se verifica |
| - | - | - |
| OB-01 | Registrar todo el ciclo logístico: abastecimiento, recepción, almacenamiento, transferencia, reserva, picking, packing, despacho e inventario físico | Casos de uso de `use-cases.md` ejecutables de punta a punta |
| OB-02 | Conocer el stock por producto y ubicación, con su disponibilidad real | Consulta de disponibilidad igual a físico menos reservado menos bloqueado |
| OB-03 | Trazar cada unidad hasta el documento y el usuario que la movió | Todo cambio de stock tiene movimiento, operación, documento de origen y usuario |
| OB-04 | Corregir errores sin alterar la historia | Toda operación se revierte desde su documento con un movimiento compensatorio (REG-012) |
| OB-05 | Separar quien ejecuta de quien aprueba en las operaciones sensibles | Aprobación de órdenes de compra, ajustes sobre umbral e inventarios físicos con permisos distintos |

### Objetivos técnicos

| ID | Objetivo | Mecanismo principal |
| - | - | - |
| OT-01 | Ningún stock negativo, ni siquiera bajo concurrencia | Restricciones CHECK y bloqueo pesimista con orden determinístico (ADR-003) |
| OT-02 | Ninguna operación duplicada ante reintentos | Cabecera `Idempotency-Key` y unicidad en base de datos (ADR-007, INV-007) |
| OT-03 | Operaciones atómicas | Una transacción ACID por operación de negocio (INV-004) |
| OT-04 | Historial inmutable | Libro de movimientos de solo inserción garantizado por triggers (ADR-006) |
| OT-05 | Stock conciliable con su historial | Movimientos de delta firmado y verificación automatizada de conciliación (ADR-002) |
| OT-06 | Autorización aplicada en el backend | Permisos por rol y alcance por depósito (ADR-009, REG-005) |
| OT-07 | API documentada y errores predecibles | OpenAPI (ADR-012) y Problem Details con códigos de dominio (ADR-010) |
| OT-08 | Calidad verificable | Pruebas automatizadas de reglas de negocio, integración y concurrencia real en CI |

### Objetivo de portfolio

El repositorio es también evidencia profesional. Cada competencia debe tener un artefacto concreto que la demuestre:

| Competencia | Evidencia |
| - | - |
| Análisis de procesos | Casos de uso, máquinas de estado y reglas de negocio basadas en prácticas de SAP |
| Modelado de datos | Diseño físico con claves, restricciones, índices y su justificación |
| Backend | Monolito modular con una única puerta de escritura al inventario |
| Concurrencia y transacciones | Pruebas con procesos paralelos sobre PostgreSQL real |
| Seguridad | Autenticación, autorización por depósito y auditoría transaccional |
| Frontend | Interfaz operativa que cubre los estados de error, vacío y conflicto |
| DevOps | Docker, CI y una instancia de demostración pública |
| Documentación | ADR, wiki de usuario y documentación de API |

## Alcance

### Incluido

| Módulo | Contenido | Etapa |
| - | - | - |
| Infraestructura | Depósitos, zonas, ubicaciones y ubicaciones de sistema | E2 |
| Catálogo | Productos, categorías, unidades base y proveedores | E2 |
| Usuarios y permisos | Roles, permisos granulares y asignación de depósitos | E1 y E2 |
| Inventario | Existencias por ubicación, reservas, bloqueos, transferencias, ajustes y reversiones | E3 |
| Abastecimiento y recepción | Órdenes de compra con aprobación, recepciones parciales, diferencias, sobrerrecepción con autorización y putaway | E4 |
| Pedidos y despacho | Pedidos, reservas, picking con escaneo, faltantes, packing y despacho | E5 |
| Inventario físico | Conteo por ubicación, reconteo, aprobación y ajustes | E6 |
| Reportes y alertas | Stock mínimo, productos inmovilizados, tablero operativo y exportaciones asíncronas | E7 |
| Auditoría | Registro de operaciones críticas dentro de la misma transacción | E3 a E6 |
| Wiki de usuario | Una página por funcionalidad, enlazada desde la interfaz con claves de ayuda | E2 a E8 |

El **MVP 1.0** es el circuito completo al cierre de E5: crear depósito y ubicaciones, registrar productos y proveedores, emitir una orden de compra, recibir parcial o totalmente, ubicar, transferir, crear un pedido, reservar, preparar, despachar y consultar el historial completo.

### Fuera de alcance

| Funcionalidad | Motivo |
| - | - |
| Multiempresa (SaaS multi-tenant) | Multiplica el alcance sin aportar al problema central; el sistema es para una empresa con múltiples depósitos |
| Lotes, vencimientos y números de serie | Se incorporan en una versión posterior; el modelo ya reserva `lot_id` para hacerlo sin migrar datos |
| Microservicios | Complejidad operativa innecesaria para el tamaño del dominio (ADR-001) |
| Aplicación móvil nativa | La interfaz web responsiva cubre la operación con lectores de código de barras |
| Integración con ERP y transportistas | Requiere sistemas externos y no aporta al núcleo transaccional |
| Optimización de rutas de picking, slotting automático y aprendizaje automático | Complejidad algorítmica ajena al objetivo |
| RFID | Requiere hardware específico |
| Facturación, precios y costeo de inventario | Pertenecen al ERP, no al WMS |

## Decisiones de alcance

| Decisión | Valor adoptado | Consecuencia |
| - | - | - |
| Modelo empresarial | Una empresa, múltiples depósitos | No hay `tenant_id`; la seguridad se acota por depósito |
| Trazabilidad | Básica (producto, cantidad, ubicación), preparada para lotes | `stocks.lot_id` nulo desde el inicio con unicidad `NULLS NOT DISTINCT` |
| Alcance operativo | WMS completo construido de forma incremental | MVP 1.0 al cierre de E5, luego E6 a E8 |
| Referencia funcional | SAP (MM-IM, WM/EWM y SD) para movimientos, reversiones y estados | Códigos de movimiento SAP donde hay equivalente y rango 9xx para los internos (DM-09) |
| Corrección de errores | Toda operación es reversible desde su documento, en orden inverso de la cadena | Endpoints de reversión en E3 a E6 (DM-04, REG-012) |
| Cantidades | Enteras, en la unidad base del producto | Sin errores de redondeo en el libro (ADR-005) |

## Partes interesadas y roles

| Rol | Responsabilidad | Ejemplos de permisos |
| - | - | - |
| Administrador | Configuración general, usuarios y datos maestros | `users.manage`, `locations.manage` |
| Supervisor | Control, aprobación y reversión de operaciones | `inventory.adjust.approve`, `stocktake.approve`, `dispatch.reverse` |
| Operador | Ejecución de las operaciones del depósito | `putaway.execute`, `picking.execute`, `stocktake.count` |
| Compras | Proveedores y órdenes de compra | `procurement.create` |
| Auditor | Consulta del historial y la auditoría, sin modificar datos | `audit.view` |

La asignación definitiva de permisos a roles está en el catálogo de permisos de `domain-model.md` y se implementa en el seeder de E2.

## Restricciones

| Tipo | Restricción |
| - | - |
| Equipo | Desarrollo individual con dedicación parcial |
| Stack | Laravel (PHP), PostgreSQL, React con TypeScript y Docker. Redis solo ante una necesidad concreta (ADR-011) |
| Base de datos | PostgreSQL 15 o superior, requerido por `NULLS NOT DISTINCT`. Las garantías críticas se implementan en la base, no solo en la aplicación |
| Arquitectura | Monolito modular; solo las Actions del módulo `Inventory` escriben en `stocks` y `stock_movements` |
| Calidad | Ninguna tarea que toque reglas de negocio se cierra sin prueba automatizada; CI en verde para cerrar cualquier tarea |
| Documentación | Toda decisión relevante genera un ADR en la etapa en que se toma |
| Idioma | Código y API en inglés; documentación, interfaz y wiki en español |

## Supuestos

- La operación se hace con lectores de código de barras que actúan como teclado sobre la interfaz web.
- Un producto se almacena y se mueve en su unidad base; las conversiones de empaque quedan para una versión posterior.
- Los pedidos y las órdenes de compra se cargan en el WMS; no hay un ERP que los origine.
- La carga inicial de stock se hace con ajustes de motivo `initial_load`, auditados como cualquier otro ajuste.

## Riesgos

| Riesgo | Impacto | Mitigación |
| - | - | - |
| Parálisis por análisis en la especificación | Retraso de todo el proyecto | E0 con límite de tiempo; lo no resuelto se registra como pregunta abierta en el backlog con la etapa en que se resuelve |
| Subestimar el núcleo de inventario (E3) | Retraso del camino crítico E0, E1, E2, E3, E5 | E3 tiene la mayor asignación de tiempo y pruebas de concurrencia desde su inicio |
| Pruebas de concurrencia que pasan por azar | Falsa confianza en las garantías | Pruebas con procesos paralelos reales sobre PostgreSQL, repetidas en CI |
| Crecimiento del alcance hacia un ERP | Producto sin terminar | La sección "Fuera de alcance" de este documento es el criterio de corte |
| Divergencia entre documentación y código | Documentación sin valor | Cada etapa actualiza sus documentos y su página de wiki en el mismo lote que el código |

## Hitos

| Etapa | Hito |
| - | - |
| E0 | Especificación aprobada: charter, glosario, reglas de negocio, casos de uso, modelo de dominio, diseño de base de datos y ADR revisados |
| E1 | Esqueleto desplegable localmente con autenticación y CI |
| E2 | Datos maestros administrables |
| E3 | Motor de inventario probado bajo concurrencia |
| E4 | Circuito de entrada completo |
| E5 | **MVP 1.0** |
| E6 | Ajustes de inventario físico aprobados y auditables |
| E7 | Tablero operativo y alertas |
| E8 | Versión pública demostrable |

## Criterios de éxito

El proyecto se considera terminado cuando se cumplen todos estos criterios, no cuando funcionan todas las pantallas:

- [ ] Se administran depósitos, ubicaciones, productos y proveedores.
- [ ] Se registran recepciones parciales y totales contra órdenes de compra aprobadas.
- [ ] Se realizan transferencias entre ubicaciones y son atómicas.
- [ ] Se reservan y liberan existencias, y se preparan y despachan pedidos.
- [ ] Se realizan inventarios físicos y sus diferencias requieren aprobación.
- [ ] Toda operación es reversible desde su documento sin modificar movimientos existentes.
- [ ] Todo movimiento es trazable hasta su documento y su usuario.
- [ ] No existen stocks negativos ni operaciones duplicadas, verificado con pruebas de concurrencia.
- [ ] Los permisos se aplican en el backend.
- [ ] Las invariantes INV-001 a INV-012 tienen pruebas automatizadas en verde.
- [ ] El proyecto se levanta con Docker y tiene CI.
- [ ] Existe documentación técnica, wiki de usuario y documentación de API.
- [ ] Existe una instancia desplegada y demostrable.

## Documentos relacionados

| Documento | Contenido |
| - | - |
| [glossary.md](glossary.md) | Términos del dominio y su nombre en código |
| [business-rules.md](../02-analysis/business-rules.md) | Invariantes, reglas complementarias y códigos de error |
| [use-cases.md](../02-analysis/use-cases.md) | Casos de uso del MVP |
| [domain-model.md](../03-architecture/domain-model.md) | Entidades, máquinas de estado, reversiones y permisos |
| [database-design.md](../03-architecture/database-design.md) | Esquema físico, restricciones e índices |
| [adr/](../03-architecture/adr/README.md) | Registro de decisiones de arquitectura |
