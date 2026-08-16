<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class OrderLine extends Model
{
    protected $fillable = ['order_id','product_id','variant_id','product_name','variant_name','variant_sku','unit_price','quantity','line_total','currency'];
    protected function casts(): array { return ['unit_price'=>'decimal:2','line_total'=>'decimal:2','quantity'=>'integer']; }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function options(): HasMany { return $this->hasMany(OrderLineOption::class); }
}
