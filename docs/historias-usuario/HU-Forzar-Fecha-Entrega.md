# HU Forzar Fecha de Entrega

Yo como administrador del sistema quiero poder forzar la fecha de entrega de todos los pedidos para manejar situaciones de emergencia donde se requiere procesar pedidos inmediatamente o cuando hay retrasos en el procesamiento que requieren acelerar todos los pedidos pendientes. Esta funcionalidad será usada para:
- Situaciones de emergencia que requieren cumplimiento inmediato de pedidos
- Cuando hay retrasos en el procesamiento y todos los pedidos pendientes necesitan ser acelerados
- Cuando el calendario de entrega normal necesita ser temporalmente omitido
- Cuando hay problemas con el sistema que han causado que pedidos queden en espera

## Criterios de aceptación

- Debe existir un toggle en la sección de Configuraciones del panel de administración para activar/desactivar la funcionalidad
- Cuando el toggle está desactivado (estado por defecto), los pedidos usan sus fechas de entrega programadas normales y pueden quedar en estado `WAITING` si fueron creados antes del día de visita del vendedor
- Cuando el toggle está activado:
  - Todos los nuevos pedidos omiten el mecanismo de espera/retraso y se procesan inmediatamente
  - Todos los pedidos transmitidos al SOAP API tienen su fecha de entrega sobrescrita al próximo día hábil disponible
  - Los pedidos existentes en estado `WAITING` se procesan inmediatamente cuando el toggle está activo
- La fecha de entrega forzada debe ser el próximo día hábil (excluyendo fines de semana y días festivos)
- Las fechas de entrega originales deben preservarse en la base de datos; solo el payload del SOAP debe verse afectado
- El sistema debe registrar en logs todos los casos donde se sobrescribe la fecha de entrega, incluyendo detalles del pedido y la fecha original vs la fecha forzada
- Debe mostrarse un banner de advertencia en el panel de administración cuando esta configuración está activa, indicando claramente que los pedidos se enviarán con fecha de entrega forzada
- El toggle debe tener un tema visual rojo para indicar su naturaleza de emergencia
- Todos los cambios en esta configuración deben ser registrados en logs con información del usuario y timestamp
- La funcionalidad debe funcionar tanto para pedidos nuevos como para pedidos existentes que están en estado `WAITING`
- La sobrescritura de fecha debe ocurrir en el nivel de transmisión SOAP en `OrderRepository::sendData()`
- La funcionalidad debe afectar a TODOS los pedidos siendo procesados mientras la configuración está activa
