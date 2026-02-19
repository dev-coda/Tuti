# HU Actualización Automática de Precios de Productos Hijos

Yo como administrador del sistema quiero que cuando se actualicen automáticamente los precios de productos desde Dynamics mediante el proceso de sincronización, también se actualicen automáticamente los precios de las variaciones (productos hijos) asociadas a esos productos, para mantener la consistencia de precios entre el producto padre y sus variaciones sin intervención manual. Esta funcionalidad será usada para:
- Mantener sincronizados los precios de productos con variaciones cuando cambian en el sistema externo
- Evitar discrepancias de precios entre el producto padre y sus variaciones después de actualizaciones automáticas
- Reducir el trabajo manual de actualizar precios de variaciones uno por uno
- Asegurar que los clientes vean precios consistentes independientemente de la variación seleccionada
- Mantener la integridad de datos de precios cuando los productos tienen múltiples opciones (tamaños, colores, etc.)

## Criterios de aceptación

- La funcionalidad debe estar controlada por un campo de configuración a nivel de producto (`sync_variations_with_dynamics`)
- Solo los productos que tengan el campo `sync_variations_with_dynamics` habilitado deben actualizar sus variaciones automáticamente
- La actualización de precios de variaciones solo debe ocurrir cuando el producto padre tiene un `variation_id` asignado (es decir, es un producto con variaciones)
- Cuando se actualiza el precio del producto padre mediante el proceso automático de sincronización desde Dynamics, se debe verificar si `sync_variations_with_dynamics` está habilitado
- Si está habilitado, se deben actualizar todos los registros en la tabla `product_item_variation` asociados a ese producto padre con el mismo precio calculado del producto padre
- El precio aplicado a las variaciones debe ser el mismo precio efectivo calculado para el producto padre (considerando `calculate_package_price` y `package_quantity`)
- La actualización de variaciones debe ocurrir dentro de la misma transacción de base de datos que actualiza el producto padre para garantizar consistencia
- El proceso debe registrar en logs cuántas variaciones fueron actualizadas para cada producto padre
- El proceso debe ser eficiente y no causar impacto significativo en el rendimiento durante la sincronización masiva de precios
- Si un producto padre no tiene variaciones asociadas, el proceso no debe fallar ni generar errores
- La funcionalidad debe respetar la estructura existente de variaciones y no modificar otros campos de las variaciones (solo el precio)
- El campo `sync_variations_with_dynamics` debe ser configurable desde el panel de administración en la edición de productos
- La actualización de variaciones debe ocurrir solo cuando el precio del producto padre realmente cambia (no en cada ejecución del proceso si el precio es el mismo)
- El proceso debe manejar errores de manera robusta: si la actualización de variaciones falla, debe registrar el error pero no debe impedir que se actualicen otros productos
- La funcionalidad debe funcionar correctamente con productos que tienen múltiples variaciones (múltiples items en `product_item_variation`)
- El precio actualizado en las variaciones debe ser exactamente el mismo que el precio del producto padre después de aplicar todas las transformaciones (división por package_quantity si aplica)
