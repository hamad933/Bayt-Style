<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class OrderLineOption extends Model
{
    public $timestamps = false;
    protected $fillable = ['order_line_id','attribute_code','attribute_name','option_value'];
    public function orderLine(): BelongsTo { return $this->belongsTo(OrderLine::class); }
}
