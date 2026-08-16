<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
class OrderEvent extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'order_id','event_type','actor_type','entity_type','order_reference','resulting_order_state',
        'resulting_payment_state','resulting_reservation_state','resulting_fulfillment_state','reason_code',
        'correlation_id','occurred_at',
    ];
    protected function casts(): array { return ['occurred_at' => 'datetime']; }
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Order events are append-only.'));
        static::deleting(fn () => throw new LogicException('Order events are append-only.'));
    }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
}
