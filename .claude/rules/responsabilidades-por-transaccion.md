# Regla: una transacción no mezcla responsabilidades de alcances o roles distintos

Una transacción (pantalla, endpoint y Action) agrupa lo que hace un mismo responsable con un mismo alcance. No se mezcla en la misma pantalla lo que corresponde a roles o niveles distintos, porque entonces un permiso habilita más de lo que debe y los roles chocan (decisión del usuario del 26/11/2026).

## Qué no se mezcla

- **Alcance global y alcance de un depósito.** El maestro que alimenta a todos los depósitos (datos generales del catálogo, categorías, unidades, proveedores, estructura de depósitos) no se edita en la misma pantalla que un dato propio de un depósito (stock mínimo, datos operativos del depósito).
- **Operación y control.** Lo que hace quien opera (ajustar, transferir, recibir) no se mezcla con lo que lo controla (umbrales de aprobación, tolerancias, aprobaciones): quien opera no fija ni decide su propio control.
- **Roles distintos.** Si dos datos de una misma pantalla los mantienen roles distintos en la práctica (el área de maestros y el supervisor de un depósito), van en transacciones distintas, cada una con su permiso.

## Qué no se busca

- No se crea un permiso ni una transacción por cada acción. Una transacción de gestión que reúne crear, modificar y visualizar sobre el mismo objeto y con el mismo responsable sigue siendo válida (ADR-016).
- Una acción de otro nivel dentro de una transacción (aprobar, revertir, recibir de más) puede quedar en la misma pantalla si es parte natural del flujo, pero pide su propio permiso explícito.
- El criterio es el responsable y el alcance, no la cantidad de campos o botones.

## Cómo se aplica

- Antes de agregar un campo o una acción a una transacción existente, verificar que lo mantiene el mismo rol y con el mismo alcance (global o por depósito). Si no, va en otra transacción.
- Ante una duda, plantearla al usuario con las opciones antes de implementar.
- El ejemplo que originó la regla: el stock mínimo y los umbrales de ajuste estaban en MM02 junto a los datos generales del artículo; se separan en transacciones propias (stock mínimo para el supervisor del depósito, umbrales como control central).
