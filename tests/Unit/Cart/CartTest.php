<?php

namespace Tests\Unit\Cart;

use App\Cart\Cart;
use Tests\TestCase;
use App\Models\User;
use App\Models\ProductVariation;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CartTest extends TestCase
{
    public function test_it_can_add_product_to_the_cart()
    {
        $cart = new Cart(
            $user = factory(User::class)->create()
        );
        $product = factory(ProductVariation::class)->create();

        $cart->add([
            ['id'=> $product->id, 'quantity' => 1]
        ]);
        $this->assertCount(1, $user->fresh()->cart);
    }
}
