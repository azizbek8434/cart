<?php

namespace App\Events\Order;


use App\Models\Order;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;


class OrderPaymentFaild
{
    use Dispatchable, SerializesModels;

    /**
     * Order desc
     *
     * @var [type]
     */
    public $order;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }
}
