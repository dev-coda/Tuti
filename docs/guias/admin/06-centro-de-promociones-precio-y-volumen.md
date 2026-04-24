# Admin: centro de “Promociones” (hub) y módulos relacionados

Rutas definidas en `Tuti/routes/admin.php`: prefijo con nombre de ruta `promociones.*` y, aparte, los *recursos* `volume-discounts` y `promocion` (nombre de recurso en singular en la ruta).

## Relación con los documentos 07, 08 y 09

- **07** — *Descuentos y promociones (vista englobada)*: jerarquía y reglas generales de descuento.  
- **08** — *Bonificaciones* y **09** — *Cupones*: reglas y objetos de negocio concretos.  
- **06 (este capítulo):** el **centro** de *Promociones* en el menú, la **pantalla de análisis** y dos módulos que no son *cupones por código*: **descuentos por volumen** (`VolumeDiscountController`) y entidades **Promocion** con fechas (`PromocionController`).

## Hub “Promociones” (navegación y análisis)

| Ruta (nombre) | Uso resumido |
|---------------|--------------|
| `promociones.index` | Entrada al hub. |
| `promociones.descuento-directo` | Enlace a herramientas de descuento directo (vistas bajo `resources/views/promociones/`). |
| `promociones.descuento-volumen` | Acceso a la gestión de tramos por volumen (suele reutilizar el mismo layout). |
| `promociones.bonificaciones` | Enlaza hacia el listado de bonificaciones. |
| `promociones.cupones` | Enlaza hacia el listado de cupones. |
| `promociones.promociones` | Entidades *Promocion* (fechas, porcentaje o monto fijo) — no es “cupón” por código. |
| `promociones.analisis` | *Analytics* (uso de descuentos, cupones, bonificaciones) — orientado a operación o marketing, no al ABM diario. |
| `promociones.elements` | JSON para selectores o buscadores en otras pantallas. |

*Implementación:* `PromocionesController`. Si los títulos del menú en tu *deploy* no coinciden, consultá `php artisan route:list` o el archivo de rutas.

## Recurso `volume-discounts` (descuentos por volumen)

- **Controlador:** `VolumeDiscountController` — *CRUD* completo.  
- **Comportamiento:** tramos de cantidad con descuento, con alcance a producto, categoría, marca o proveedor (según modelo y validaciones).  
- **En el carrito web:** se aplican en el cálculo de precio; **no** se deben mezclar con un *cupón* *por código* en el soporte al operador.  
- **Navegación:** el *hub* sirve para *saltar* a los listados; el *alta/edición* sigue las rutas *resource* estándar de Laravel.

## Recurso `promocion` (promociones con ventana de fechas)

- **Controlador:** `PromocionController`.  
- **Comportamiento:** *promo* con fechas inicio/fin, alcance (producto, categoría, marca, proveedor, zona, etc.) y tipo porcentual o fijo, según formulario.  
- **Cuidado con nombres:** en el código y en la *UI* se repite “promociones” para *hub*, para el *recurso* y para *cupones*; en comunicación al equipo, distinguir **entidad *Promocion*** frente a **cupón alfanumérico**.

## Lista de comprobación operativa

1. Revisar **huso horario** del servidor al fijar “cambio a medianoche” de una *Promocion* (desajuste = que no aplique cuando se cree).  
2. Tras cambios de *volumen* o *Promocion*, probar un carrito de **entorno de pruebas** con *zona* y *variación* conocidos.  
3. Si el precio no concuerda con *Dynamics*, corregir primero **origen de verdad** en *ERP* o *job* de precios *antes* de añadir otra capa de *Promocion*.

## Ver también

- [07 – Descuentos (englobado)](./07-descuentos-y-promociones-englobado.md)  
- [08 – Bonificaciones](./08-bonificaciones.md)  
- [09 – Cupones](./09-cupones-gestion-avanzada.md)  
- [10 – Usuarios y zonas](./10-usuarios-vendedores-y-accesos.md)  
- `Tuti/routes/admin.php` (autoritario para nombres de ruta)  

---

*Revisado: abril 2026.*
