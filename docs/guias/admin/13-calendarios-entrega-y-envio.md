# Módulo de Calendarios de Entrega

## 📋 Descripción General

El módulo de Calendarios de Entrega gestiona la configuración de semanas disponibles para entrega, días festivos, y el cálculo de fechas de entrega según el método seleccionado (**Entrega Standard** / Tronex o **Entrega Especial** / express).

## 📅 Calendarios de Entrega

### Concepto

Los calendarios de entrega definen **semanas disponibles** para entrega según el ciclo de ruta (A, B, C). Cada semana tiene una fecha de inicio y fin, y puede estar marcada como disponible o no disponible.

### Estructura

```
Ciclo (A, B, C)
└── Semana 1
    ├── Fecha Inicio: 2025-01-06
    ├── Fecha Fin: 2025-01-12
    └── Disponible: Sí
└── Semana 2
    ├── Fecha Inicio: 2025-01-13
    ├── Fecha Fin: 2025-01-19
    └── Disponible: Sí
```

### Gestión de Calendarios (Administradores)

#### Crear Calendario

1. **Panel Admin → Calendarios de Entrega → Crear**

2. **Datos Requeridos**:
   - **Ciclo**: Ciclo de ruta (A, B, o C)
   - **Fecha Inicio**: Primer día de la semana
   - **Fecha Fin**: Último día de la semana
   - **Disponible**: Marcar si la semana está disponible

3. **Guardar**: Se crea la entrada del calendario

#### Editar Calendario

- Cambiar fechas de inicio/fin
- Activar/desactivar disponibilidad
- Modificar ciclo (con precaución)

#### Eliminar Calendario

- Solo si no hay órdenes asociadas
- Se puede marcar como no disponible en lugar de eliminar

### Importación Masiva desde CSV

#### Preparar Archivo CSV

1. **Descargar Plantilla**:
   - Panel Admin → Calendarios de Entrega → Descargar Plantilla
   - Se descarga un archivo CSV con formato estándar

2. **Formato del CSV**:
   ```csv
   ciclo,fecha_inicio,fecha_fin,disponible
   A,2025-01-06,2025-01-12,1
   A,2025-01-13,2025-01-19,1
   B,2025-01-06,2025-01-12,1
   ```

3. **Campos**:
   - **ciclo**: A, B, o C
   - **fecha_inicio**: Formato YYYY-MM-DD
   - **fecha_fin**: Formato YYYY-MM-DD
   - **disponible**: 1 para disponible, 0 para no disponible

#### Importar CSV

1. **Panel Admin → Calendarios de Entrega → Importar**
2. **Seleccionar Archivo**: Elegir el CSV preparado
3. **Validar**: El sistema valida el formato
4. **Confirmar**: Se importan todas las semanas

#### Ventajas de Importación Masiva

- Carga rápida de múltiples semanas
- Útil para planificación mensual o trimestral
- Reduce errores manuales

## 🚚 Métodos de Entrega

### Método Tronex

El método Tronex calcula la fecha de entrega basándose en la ruta del usuario y el calendario de entrega.

#### Proceso de Cálculo

1. **Obtener Ruta del Usuario**:
   - Se obtiene de la zona seleccionada
   - Cada zona tiene una ruta asignada

2. **Mapear Ruta a Ciclo**:
   - Se busca en la tabla `route_cycles`
   - Se obtiene el ciclo (A, B, o C) de la ruta

3. **Buscar Próxima Semana Disponible**:
   - Se busca en `delivery_calendars` la próxima semana disponible para ese ciclo
   - Debe ser al menos mañana (no puede ser hoy)

4. **Encontrar Día de Visita del Vendedor**:
   - Se obtiene el día de la semana del campo "day" de la zona (ej: "5-Viernes")
   - Se busca ese día de la semana dentro de la semana disponible encontrada

5. **Calcular Fecha de Entrega**:
   - Fecha de entrega = Día de visita del vendedor + 1 día hábil
   - Se considera días hábiles (excluye domingos, festivos, y sábados no hábiles)

#### Ejemplo Completo

- **Usuario**: Zona con ruta "RUTA-01" y día "5-Viernes"
- **Ciclo**: Ruta "RUTA-01" pertenece al ciclo "A"
- **Semana Disponible**: Ciclo A, semana del 6 al 12 de enero
- **Día de Visita**: Viernes 10 de enero (día 5 dentro de la semana)
- **Fecha de Entrega**: Lunes 13 de enero (viernes + 1 día hábil)

#### Órdenes con Fecha Diferida

Si el día de visita del vendedor **no es hoy**:

- La orden se crea con estado **EN ESPERA**
- Se guarda `scheduled_transmission_date` = día de visita del vendedor
- El job de procesamiento espera hasta esa fecha
- Cuando llega la fecha, se procesa automáticamente

### Método Entrega Especial (express / 48h)

En UI del carrito se muestra como **Entrega Especial**. Promete entrega en **~2 días hábiles** desde la fecha de la orden (según festivos y configuración).

#### Proceso de Cálculo

1. **Fecha Base**: Mañana (las órdenes de hoy no se entregan hoy)
2. **Contar Días Hábiles**: Se cuentan exactamente 2 días hábiles desde mañana
3. **Resultado**: Fecha de entrega = mañana + 2 días hábiles

Disponibilidad adicional: toggle global Express 48h, toggles por zona y por ciudad (ver [18](./18-festivos-impuestos-envio-y-retenciones.md) y [05 plazos comprador](../b2b-tienda/05-plazos-entrega-vista-comprador.md)).

#### Ejemplo

- **Orden Lunes**: Entrega Miércoles (Martes + Miércoles = 2 días hábiles)
- **Orden Viernes**: Entrega Martes siguiente (Lunes + Martes = 2 días hábiles, saltando fin de semana)
- **Orden con Festivo**: Se saltan festivos y domingos

## 📆 Días Festivos

### Concepto

Los días festivos son días no laborables que afectan el cálculo de fechas de entrega.

### Tipos de Festivos

1. **Festivo Nacional** (`HOLIDAY`):
   - Días no laborables oficiales
   - No se cuentan como días hábiles
   - Ejemplo: Navidad, Año Nuevo

2. **Sábado Laboral** (`SATURDAY`):
   - Sábados que SÍ son hábiles
   - Se cuentan como días hábiles
   - Útil para semanas especiales

### Gestión de Festivos (Administradores)

#### Crear Festivo

1. **Panel Admin → Días Festivos → Crear**

2. **Datos Requeridos**:
   - **Fecha**: Fecha del festivo
   - **Tipo**: Festivo Nacional o Sábado Laboral
   - **Nombre**: Nombre descriptivo (opcional)

3. **Guardar**: Se crea el festivo

#### Importar Festivos desde CSV

1. **Panel Admin → Días Festivos → Importar**
2. **Descargar Plantilla**: Formato CSV estándar
3. **Completar**: Fecha, tipo, nombre
4. **Importar**: Se cargan todos los festivos

#### Eliminar Festivo

- Se puede eliminar si no hay órdenes afectadas
- Útil para correcciones

## 🕐 Hora de Cierre

### Concepto

La **hora de cierre** determina si una orden se cuenta desde hoy o desde mañana para el cálculo de fechas.

### Configuración

1. **Panel Admin → Configuración → Hora de Cierre**
2. **Valor**: Hora en formato 24 horas (ej: 14 para 2 PM)
3. **Guardar**: Se aplica inmediatamente

### Funcionamiento

#### Antes de la Hora de Cierre

- Si ordenas antes de la hora de cierre:
  - Se cuenta desde **hoy** para cálculo de fechas
  - Ejemplo: Orden a las 13:00 con cierre a las 14:00 → Se cuenta desde hoy

#### Después de la Hora de Cierre

- Si ordenas después de la hora de cierre:
  - Se cuenta desde **mañana** para cálculo de fechas
  - Ejemplo: Orden a las 15:00 con cierre a las 14:00 → Se cuenta desde mañana

### Propósito

- Permite procesar órdenes del mismo día si se ordenan temprano
- Evita promesas de entrega imposibles
- Mejora la planificación logística

## 📊 Cálculo de Días Hábiles

### Definición de Día Hábil

Un día hábil es un día que:
- ✅ Es lunes, martes, miércoles, jueves o viernes
- ✅ NO es domingo
- ✅ NO es un festivo nacional
- ✅ Puede ser sábado si está marcado como sábado laboral

### Proceso de Cálculo

1. **Empezar desde Fecha Base**: Hoy o mañana según hora de cierre
2. **Contar Días**: Avanzar día por día
3. **Saltar No Hábiles**: Saltar domingos y festivos
4. **Incluir Sábados Laborales**: Si están marcados como hábiles
5. **Resultado**: Fecha después de N días hábiles

### Ejemplo de Cálculo

**Escenario**: Calcular 2 días hábiles desde el viernes 3 de enero

- **Viernes 3**: Día hábil 1
- **Sábado 4**: No hábil (a menos que sea sábado laboral)
- **Domingo 5**: No hábil
- **Lunes 6**: Día hábil 2 ✅
- **Resultado**: Lunes 6 de enero

## 🔄 Sincronización con Rutas

### Actualización de Calendarios

Los calendarios se pueden actualizar cuando cambian las rutas:

1. **Cambio de Ciclo**: Si una ruta cambia de ciclo, se deben actualizar los calendarios
2. **Nuevas Semanas**: Agregar nuevas semanas disponibles
3. **Semanas No Disponibles**: Marcar semanas como no disponibles

### Impacto en Órdenes Existentes

- Las órdenes existentes mantienen su fecha de entrega original
- Los cambios afectan solo nuevas órdenes
- Si una semana se marca como no disponible, las nuevas órdenes buscarán la siguiente semana disponible

## 📝 Reglas de Negocio

### Semanas Disponibles

1. **Deben Ser Futuras**: No se pueden crear semanas en el pasado
2. **Deben Estar Completas**: Fecha inicio y fin deben ser válidas
3. **No Pueden Solaparse**: Semanas del mismo ciclo no pueden solaparse

### Cálculo de Fechas

1. **Mínimo Mañana**: Las fechas de entrega nunca pueden ser hoy
2. **Respetar Días Hábiles**: Se saltan domingos y festivos
3. **Considerar Hora de Cierre**: Afecta desde qué día se cuenta

### Órdenes Tronex

1. **Requieren Zona**: Debe haber una zona seleccionada
2. **Requieren Ruta**: La zona debe tener ruta asignada
3. **Requieren Ciclo**: La ruta debe estar mapeada a un ciclo
4. **Requieren Semana Disponible**: Debe haber una semana disponible para ese ciclo

### Órdenes Entrega Especial (express)

1. **Requieren** que Express 48h esté habilitado y que zona/ciudad permitan el método
2. Fecha ~2 días hábiles (festivos/sábados según configuración)
3. Pueden cotizar flete Coordinadora cuando la zona usa fulfillment 48h

## ⚠️ Consideraciones Importantes

### Planificación de Calendarios

- Planificar con anticipación (mínimo 1 mes)
- Considerar festivos conocidos
- Marcar semanas no disponibles con tiempo

### Cambios en Calendarios

- Los cambios afectan nuevas órdenes
- Las órdenes existentes mantienen su fecha
- Verificar impacto antes de cambiar

### Festivos

- Agregar festivos con anticipación
- Verificar que no haya órdenes afectadas
- Considerar festivos regionales si aplica

## ❓ Preguntas Frecuentes

### ¿Cómo calculo la fecha de entrega para una orden Tronex?

El sistema calcula automáticamente:
1. Obtiene la ruta de la zona del usuario
2. Busca el ciclo de esa ruta
3. Encuentra la próxima semana disponible
4. Encuentra el día de visita dentro de esa semana
5. Suma 1 día hábil

### ¿Puedo cambiar la fecha de entrega de una orden existente?

Solo los administradores pueden cambiar fechas manualmente. Las órdenes nuevas se calculan automáticamente.

### ¿Qué pasa si no hay semanas disponibles para un ciclo?

El sistema no podrá calcular fecha de entrega. Se debe agregar al menos una semana disponible para ese ciclo.

### ¿Los festivos afectan órdenes ya creadas?

No, los festivos solo afectan el cálculo de nuevas órdenes. Las órdenes existentes mantienen su fecha.

### ¿Cómo funciona la hora de cierre?

Si ordenas antes de la hora de cierre, se cuenta desde hoy. Si ordenas después, se cuenta desde mañana.

### ¿Puedo tener diferentes horas de cierre por zona?

No, la hora de cierre es global para todo el sistema.

### ¿Qué pasa si una semana se marca como no disponible después de crear órdenes?

Las órdenes existentes mantienen su fecha. Solo las nuevas órdenes buscarán otra semana disponible.

