<?php

namespace App\Exports;

use App\Models\Coupon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CouponsExport implements FromQuery, WithMapping, WithHeadings
{
    protected $onlyMassCreated;

    public function __construct(bool $onlyMassCreated = false)
    {
        $this->onlyMassCreated = $onlyMassCreated;
    }

    public function query()
    {
        $query = Coupon::query()->orderBy('created_at', 'desc');
        
        if ($this->onlyMassCreated) {
            $query->where('is_mass_created', true);
        }
        
        return $query;
    }

    public function map($coupon): array
    {
        $appliesToLabels = [
            Coupon::APPLIES_TO_CART => 'Carrito',
            Coupon::APPLIES_TO_PRODUCT => 'Producto',
            Coupon::APPLIES_TO_CATEGORY => 'Categoría',
            Coupon::APPLIES_TO_BRAND => 'Marca',
            Coupon::APPLIES_TO_VENDOR => 'Vendedor',
            Coupon::APPLIES_TO_CUSTOMER => 'Cliente',
            Coupon::APPLIES_TO_CUSTOMER_TYPE => 'Tipo de Cliente',
        ];

        return [
            $coupon->id,
            $coupon->code,
            $coupon->name,
            $coupon->description ?? '',
            $coupon->type === Coupon::TYPE_FIXED_AMOUNT ? 'Monto Fijo' : 'Porcentaje',
            $coupon->value,
            $coupon->valid_from ? $coupon->valid_from->format('Y-m-d H:i:s') : '',
            $coupon->valid_to ? $coupon->valid_to->format('Y-m-d H:i:s') : '',
            $coupon->usage_limit_per_customer ?? 'Ilimitado',
            $coupon->usage_limit_per_vendor ?? 'Ilimitado',
            $coupon->total_usage_limit ?? 'Ilimitado',
            $coupon->current_usage ?? 0,
            $appliesToLabels[$coupon->applies_to] ?? $coupon->applies_to,
            $coupon->applies_to_ids ? json_encode($coupon->applies_to_ids) : '',
            $coupon->except_product_ids ? json_encode($coupon->except_product_ids) : '',
            $coupon->except_category_ids ? json_encode($coupon->except_category_ids) : '',
            $coupon->except_brand_ids ? json_encode($coupon->except_brand_ids) : '',
            $coupon->except_vendor_ids ? json_encode($coupon->except_vendor_ids) : '',
            $coupon->except_customer_ids ? json_encode($coupon->except_customer_ids) : '',
            $coupon->except_customer_types ? json_encode($coupon->except_customer_types) : '',
            $coupon->allowed_zone_ids ? json_encode($coupon->allowed_zone_ids) : '',
            $coupon->allowed_zones ? json_encode($coupon->allowed_zones) : '',
            $coupon->allowed_routes ? json_encode($coupon->allowed_routes) : '',
            $coupon->minimum_amount ?? '',
            $coupon->is_mass_created ? 'Sí' : 'No',
            $coupon->parent_coupon_id ? ($coupon->parentCoupon->code ?? $coupon->parent_coupon_id) : '',
            $coupon->active ? 'Activo' : 'Inactivo',
            $coupon->created_at ? $coupon->created_at->format('Y-m-d H:i:s') : '',
            $coupon->updated_at ? $coupon->updated_at->format('Y-m-d H:i:s') : '',
        ];
    }

    public function headings(): array
    {
        return [
            'ID',
            'Código',
            'Nombre',
            'Descripción',
            'Tipo',
            'Valor',
            'Válido Desde',
            'Válido Hasta',
            'Límite por Cliente',
            'Límite por Vendedor',
            'Límite Total',
            'Uso Actual',
            'Aplica A',
            'IDs de Aplicación',
            'Productos Excluidos',
            'Categorías Excluidas',
            'Marcas Excluidas',
            'Vendedores Excluidos',
            'Clientes Excluidos',
            'Tipos de Cliente Excluidos',
            'Zonas Permitidas (IDs)',
            'Zonas Permitidas',
            'Rutas Permitidas',
            'Monto Mínimo',
            'Creado Masivamente',
            'Cupón Base',
            'Estado',
            'Fecha de Creación',
            'Última Actualización',
        ];
    }
}
