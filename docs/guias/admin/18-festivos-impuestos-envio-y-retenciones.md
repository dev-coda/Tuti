# Admin: días festivo, impuestos, envío y reglas de retención

Guía de módulos de *datos maestros* y fiscos menores. Rutas: `Tuti/routes/admin.php` (recursos `holidays`, `taxes`, nombres `shipping-methods.*`, `retentions`).

## 1. Días festivo y sábados laborables (`holidays`)

- **Controlador:** `HolidayController`.  
- **Uso en negocio:** ajustar días *no laborable*s (y, si aplica, marcar sábados con tratamiento distinto) para el cálculo de *días hábiles* de *entrega* o *corte* de *transmisión* al *ERP*.  
- **Carga masiva:** `holidays.export` (GET) y *importación* por *CSV* vía `holidays.import` (GET/POST) según validaciones del controlador.  
- **Solo entornos no productivos (según *deploy*):** `holidays.debug` — no usarse en producción para *parchear* *datos* *sin* *control* de *cambios*.  
- **Efecto en el comprador:** se combina con *calendarios de entrega* y *ciclos de ruta*; ver [13](13-calendarios-entrega-y-envio.md) y [vista comprador 05](../b2b-tienda/05-plazos-entrega-vista-comprador.md).

*Consejo:* al dar de alta un *festivo* nacional, en *stage* probar `GET /api/delivery-date/{método}` con una *zona* fija y comparar *antes* / *después*.

## 2. Impuestos (`taxes`)

- **Controlador:** `TaxController` — *catálogo* de *tasas*.  
- **Vínculo con producto:** en el *form* de *product* se asocia *impuesto*; el *checkout* aplica a la *línea* según regla implementada.  
- **Cambio de tasa en cierre contable:** coordinar con *finanzas*; anunciar en *release* si afecta carritos abiertos o redondeo (revisar la implementación actual en código).

## 3. Métodos de envío (`shipping-methods`)

- **Rutas:** por lo general *index*, *edit*, *update* y *toggle* (revisar `admin.php`).  
- **Controlador:** `ShippingMethodController`.  
- **Contenido:** texto, descripción, orden, **activo**; lo que ve el *comprador* al elegir *envío*. Los *nombres* deben ser coherentes con *Order* y con el *endpoint* `GET /api/shipping-quote/{método}`. Desactivar un método en la lista no reemplaza por sí sola toda la lógica de cotización en *API*; verificar con [api-referencia-completa.md](../../tecnica/api-referencia-completa.md) y *pruebas*.

## 4. Retenciones (`retentions` — estilo RTE / IVA)

- **Controlador:** `RetentionRuleController`.  
- **Uso:** porcentaje o base de retención por grupo impositivo y tipo de línea (artículo *vs.* flete) según el *modelo*.  
- **Unicidad:** la *UI* suele impedir duplicados (grupo + tipo); tras migraciones, releer el *Form* *Request* correspondiente.  
- **Cambios en horas pico de pedidos:** coordinar con *Contabilidad*; puede afectar totales y carga a facturación.

## 5. Enlaces técnicos adicionales

| Tema | Documento |
|------|------------|
| Calendario de entrega | [13](13-calendarios-entrega-y-envio.md) |
| Zonas, 48h, Coordinadora | [12](12-zonas-rutas-y-48-horas.md) |
| Sistema *festivo* (documento técnico adicional, en inglés) | [HOLIDAYS_SYSTEM.md](../../HOLIDAYS_SYSTEM.md) |
| Modelo *BD* | [DATABASE.md](../../DATABASE.md) |

---

*Revisado: abril 2026.*
