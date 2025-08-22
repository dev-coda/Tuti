<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;


    protected $fillable = [
        'user_id',
        'total',
        'discount',
        'status_id',
        'request',
        'response',
        'zone_id',
        'seller_id',
        'delivery_date',
        'observations',
        'coupon_id',
        'coupon_code',
        'coupon_discount'
    ];


    const STATUS_PENDING = 0;
    const STATUS_PROCESED = 1;
    const STATUS_ERROR = 2;
    const STATUS_ERROR_WEBSERVICE = 3;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(OrderProduct::class);
        // return $this->belongsToMany(Product::class)->withPivot(["quantity","price", "discount", "variation_id", 'is_bonification']);
    }


    public function bonifications()
    {
        return $this->hasMany(OrderProductBonification::class);
    }


    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function couponUsages()
    {
        return $this->hasMany(CouponUsage::class);
    }
}
