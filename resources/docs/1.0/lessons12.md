# Episodes from 112 to 121

- [112-Mocking up our payment gateway](#section-1)
- [113-Storing a payment method](#section-2)
- [114-Responding with a card and writing some tests](#section-3)
- [115-Storing a new card with Stripe](#section-4)
- [116-Event handler for processing the payment](#section-5)
- [117-Processing a payment](#section-6)
- [118-Handling a failed payment](#section-7)
- [119-Handling a successful payment](#section-8)
- [120-Fixing failing 'cart empty' test](#section-9)
- [121-Testing listeners](#section-10)

<a name="section-1"></a>

## Episode-112 Mocking up our payment gateway

`1` -  `app/Http/Controllers/PaymentMethods/PaymentMethodController.php`

```php
<?php
...
use App\Cart\Payments\Gateway;
...

class PaymentMethodController extends Controller
{
    protected $gateway;

    public function __construct(Gateway $gateway)
    {
        ..
        $this->gateway = $gateway;
    }
    ...
    public function store(Request $request)
    {
        $cart = $this->gateway->withUser($request->user())
            ->createCustomer()
            ->addCard($request->token);
    }
}
```

`2` - Create new folder `Payments` into `app/Cart`

`3` - Create new file `Gateway` into `app/Cart/Payments` this will be interface

`4` - Edit `app/Cart/Payments/Gateway.php`

```php
<?php

namespace App\Cart\Payments;

use App\Models\User;

interface Gateway
{
    public function withUser(User $user);

    public function createCustomer();
}
```

`5` - Create new folder `Gateways` into `app/Cart/Payments`

`6` - Create new file `StripeGateway.php` into `app/Cart/Payments/Gateways`

`7` - Edit `app/Cart/Payments/Gateways/StripeGateway.php`

```php
<?php

namespace App\Cart\Payments\Gateways;

use App\Models\User;
use App\Cart\Payments\Gateway;
use App\Cart\Payments\Gateways\StripeGatewayCustomer;

class StripeGateway implements Gateway
{
    protected $user;

    public function withUser(User $user)
    {
        $this->user = $user;
        return $this;
    }

    public function createCustomer()
    {
        return new StripeGatewayCustomer();
    }
}
```

`8` - Edit `app/Providers/AppServiceProvider.php`

```php
use App\Cart\Payments\Gateway;
use App\Cart\Payments\Gateways\StripeGateway;
...
    public function register()
    {
        ...

        $this->app->singleton(Gateway::class, function () {
            return new StripeGateway();
        });
    }
...
```

`9` - Create new file `GatewayCustomer` into `app/Cart/Payments` this will be interface

`10` - Edit `app/Cart/Payments/GatewayCustomer.php`

```php
<?php

namespace App\Cart\Payments;

use App\Models\PaymentMethod;

interface GatewayCustomer
{
    public function charge(PaymentMethod $cart, $amount);

    public function addCard($token);
}
```

`11` - Create new file `StripeGatewayCustomer.php` into `app/Cart/Payments/Gateways`

`12` - Edit `app/Cart/Payments/Gateways/StripeGatewayCustomer.php`

```php
<?php

namespace App\Cart\Payments\Gateways;

use App\Models\PaymentMethod;
use App\Cart\Payments\GatewayCustomer;

class StripeGatewayCustomer implements GatewayCustomer
{
    public function charge(PaymentMethod $cart, $amount)
    {
        //
    }

    public function addCard($token)
    {
        //
    }
}
```

<a name="section-2"></a>

## Episode-113 Storing a payment method

`1` - Create new migration file `add_gateway_customer_id_to_users_table`

```command
php artisan make:migration add_gateway_customer_id_to_users_table --table=users
```

`2` - Edit `database/migrations/2019_08_05_072518_add_gateway_customer_id_to_users_table.php`

```php
...
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('gateway_customer_id')->nullable();
        });
    }
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('gateway_customer_id');
        });
    }
...
```

`3` - Edit `app/Http/Controllers/PaymentMethods/PaymentMethodController.php`

```php
...
public function index(Request $request)
    {
        return PaymentMethodResource::collection(
            $request->user()->paymentMethods
        );
    }
public function store(Request $request)
    {
        $cart = $this->gateway->withUser($request->user())
            ->createCustomer()
            ->addCart($request->token);

        dd($cart);
    }
...
```

`4` - Edit `app/Models/User.php`

- Added `gateway_customer_id` to fillable array

```php
...
protected $fillable = [
        'name', 'email', 'password', 'gateway_customer_id'
    ];
...
```

`5` - Edit `app/Cart/Payments/GatewayCustomer.php`

```php
...
interface GatewayCustomer
{
   ...
    public function addCart($token);
    public function id();
}
```

`6` - Edit `app/Cart/Payments/Gateways/StripeGatewayCustomer.php`

```php
use App\Cart\Payments\Gateway;
use Stripe\Customer as StripeCustomer;
...
    protected $gateway;
    protected $customer;

    public function __construct(Gateway $gateway, StripeCustomer $customer)
    {
        $this->gateway = $gateway;
        $this->customer = $customer;
    }

   ...

    public function addCart($token)
    {
        $cart = $this->customer->sources->create([
            'source' => $token,
        ]);
        $this->customer->default_source = $cart->id;

        $this->customer->save();

        $this->gateway->user()->paymentMethods()->create([
            'cart_type' => $cart->brand,
            'last_four' => $cart->last4,
            'provider_id' => $cart->id,
            'default' => true
        ]);
    }

    public function id()
    {
        return $this->customer->id;
    }
...
```

`7` - Edit `app/Cart/Payments/Gateways/StripeGateway.php`

```php
use Stripe\Customer as StripeCustomer;
...
    public function user()
    {
        return $this->user;
    }

    public function createCustomer()
    {
        if ($this->user->gateway_customer_id) {
            return $this->getCustomer();
        }

        $customer = new StripeGatewayCustomer(
            $this,
            $this->createStripeCustomer()
        );

        $this->user->update([
            'gateway_customer_id' => $customer->id()
        ]);

        return $customer;
    }

    protected function getCustomer()
    {
        return new StripeGatewayCustomer(
            $this,
            StripeCustomer::retrieve($this->user->gateway_customer_id)
        );
    }

    protected function createStripeCustomer()
    {
        return StripeCustomer::create([
            'email' => $this->user->email
        ]);
    }
...
```

<a name="section-3"></a>

## Episode-114 Responding with a card and writing some tests

`1` - Edit `app/Cart/Payments/Gateways/StripeGatewayCustomer.php`

- added return to getting created paymentMethod instance

```php
...
public function addCart($token)
    {
        return $this->gateway->user()->paymentMethods()->create([
            'cart_type' => $cart->brand,
            'last_four' => $cart->last4,
            'provider_id' => $cart->id,
            'default' => true
        ]);
    }
...
```

`2` - Edit `app/Http/Controllers/PaymentMethods/PaymentMethodController.php`

```php
public function store(Request $request)
    {
        $this->validate($request, [
            'token' => 'required'
        ]);
        ...
        return new PaymentMethodResource($cart);
    }
```

`3` - Create new test `PaymentMethodStoreTest`

```command
php artisan make:test PaymentMethods\\PaymentMethodStoreTest
```

`4` - Edit `tests/Feature/PaymentMethods/PaymentMethodStoreTest.php`

```php
<?php
namespace Tests\Feature\PaymentMethods;

use Tests\TestCase;
use App\Models\User;

class PaymentMethodStoreTest extends TestCase
{
    public function test_it_fails_if_not_authenticated()
    {
        $this->json("POST", "api/payment-methods")
            ->assertStatus(401);
    }

    public function test_it_requires_a_token()
    {
        $user = factory(User::class)->create();

        $this->jsonAs($user, "POST", "api/payment-methods")
            ->assertJsonValidationErrors(['token']);
    }

    public function test_it_can_successfully_add_a_card()
    {
        $user = factory(User::class)->create();

        $this->jsonAs($user, 'POST', 'api/payment-methods', [
            'token' => 'tok_visa'
        ]);

        $this->assertDatabaseHas('payment_methods', [
            'user_id' => $user->id,
            'cart_type' => 'Visa',
            'last_four' => '4242'
        ]);
    }

    public function test_it_returns_the_created_card()
    {
        $user = factory(User::class)->create();

        $this->jsonAs($user, 'POST', 'api/payment-methods', [
            'token' => 'tok_visa'
        ])
            ->assertJsonFragment([
                'cart_type' => 'Visa'
            ]);
    }

    public function test_it_sets_the_created_card_as_default()
    {
        $user = factory(User::class)->create();

        $response = $this->jsonAs($user, 'POST', 'api/payment-methods', [
            'token' => 'tok_visa'
        ]);
        $this->assertDatabaseHas('payment_methods', [
            'id' => json_decode($response->getContent())->data->id,
            'default' => true
        ]);
    }
}
```

`5` - Create new request file `PaymentMethodStoreRequest`

```command
php artisan make:request PaymentMethods\\PaymentMethodStoreRequest
```

`6` - Edit `app/Http/Requests/PaymentMethods/PaymentMethodStoreRequest.php`

```php
...
    public function authorize()
    {
        return true;
    }
    public function rules()
    {
        return [
            'token' => 'required'
        ];
    }
...
```

`7` - Edit `app/Http/Controllers/PaymentMethods/PaymentMethodController.php`

```php
use App\Http\Requests\PaymentMethods\PaymentMethodStoreRequest;
...
public function store(PaymentMethodStoreRequest $request)
    {
        $cart = $this->gateway->withUser($request->user())
            ->createCustomer()
            ->addCart($request->token);

        return new PaymentMethodResource($cart);
    }
...
```

<a name="section-4"></a>

## Episode-115 Storing a new card with Stripe

`1` - Edit `resources/views/layouts/app.blade.php`

```html
...
<script src="https://js.stripe.com/v3/"></script>
...
```

`2` - Create new file `PaymentMethodCreator.vue` into `resources/js/components/checkout/paymentMethods` and edit

```html
<template>
  <form action="#" @submit.prevent="store">
    <div class="form-group">
      <label for="card-element">Credit or debit card</label>
      <div id="card-element" class="form-control"></div>
    </div>
    <div class="form-group">
      <button type="submit" class="btn btn-primary btn-sm" :disabled="storing">Store card</button>
      <button type="button" class="btn btn-light btn-sm" @click.prevent="$emit('cancel')">Cancel</button>
    </div>
  </form>
</template>
```

js part

```js
<script>
export default {
  data() {
    return {
      stripe: null,
      card: null,
      storing: false
    };
  },
  methods: {
    async store() {
      this.storing = true;
      const { token, error } = await this.stripe.createToken(this.card);
      if (error) {
        //
      } else {
        let response = await axios.post("api/payment-methods", {
          token: token.id
        });
        this.$emit("added", response.data);
      }
      this.storing = false;
    }
  },
  mounted() {
    const stripe = Stripe("pk_test_EzuIlgdPvUDCa2ScLlsdo06j00qtkWwqZe");
    this.stripe = stripe;
    this.card = stripe.elements().create("card");
    this.card.mount("#card-element");
  }
};
</script>
```

`3` - Edit `resources/js/components/checkout/paymentMethods/PaymentMethods.vue`

```html
...
<template v-else-if="creating">
    <PaymentMethodCreator
        @cancel="creating = false"
        @added="created" />
</template>
...
<button type="button" class="btn btn-primary btn-sm"
    @click.prevent="selecting = true"
    v-if="paymentMethods.length"
    >Change payment method
</button>
...
```

js part

```js
import PaymentMethodCreator from "../paymentMethods/PaymentMethodCreator";
...
components: {
    ...
    PaymentMethodCreator
  },
...
```

<a name="section-5"></a>

## Episode-116 Event handler for processing the payment

`1` - Edit `app/Providers/EventServiceProvider.php`

- added new `ProcessPayment` listener

```php
...
   protected $listen = [
        ...
        'App\Events\Order\OrderCreated' => [
            'App\Listeners\Order\ProcessPayment',
            ...
        ]
    ];
...
```

`2` - Create new Listeners file `ProcessPayment.php` into `app/Listeners/Order`

`3` - Edit `app/Listeners/Order/ProcessPayment.php`

```php
<?php

namespace App\Listeners\Order;

use App\Cart\Payments\Gateway;
use App\Events\Order\OrderCreated;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessPayment implements ShouldQueue
{
    protected $gateway;

    public function __construct(Gateway $gateway)
    {
        $this->gateway = $gateway;
    }

    public function handle(OrderCreated $event)
    {
        // with user x
        // get customer
        // charge
    }
}

```

`4` - Edit `app/Models/Order.php`

```php
use App\Models\PaymentMethod;
...
public function paymentMethod()
{
    return $this->belongsTo(PaymentMethod::class);
}
...
```

`5` - Edit `tests/Unit/Models/Orders/OrderTest.php`

```php
use App\Models\PaymentMethod;
...
public function test_it_belongs_to_a_payment_method()
{
    $order = factory(Order::class)->create([
        'user_id' => factory(User::class)->create()->id
    ]);

    $this->assertInstanceOf(PaymentMethod::class, $order->paymentMethod);
}
...
```

<a name="section-6"></a>

## Episode-117 Processing a payment

`1` - Edit `app/Cart/Payments/Gateways/StripeGateway.php`

- Change protected to public

```php
public function getCustomer(){
    ...
}
```

`2` - Edit `app/Listeners/Order/ProcessPayment.php`

```php
...
public function handle(OrderCreated $event)
    {
        $order = $event->order;

        $this->gateway->withUser($order->user)
            ->getCustomer()
            ->charge(
                $order->paymentMethod,
                $order->total()->amount()
            );
    }
...
```

`3` - Edit `app/Cart/Payments/Gateways/StripeGatewayCustomer.php`

```php
use Stripe\Charge as StripeCharge;
...
 public function charge(PaymentMethod $cart, $amount)
    {
        StripeCharge::create([
            'currency' => 'gbp',
            'amount' => $amount,
            'customer' => $this->customer->id,
            'source' => $cart->provider_id
        ]);
    }
...
```

<a name="section-7"></a>

## Episode-118 Handling a failed payment

`1` - Edit `app/Cart/Payments/Gateways/StripeGatewayCustomer.php`

```php
use Exception;
use App\Exceptions\PaymentFaildException;
...

public function charge(PaymentMethod $cart, $amount)
    {
        try {
            StripeCharge::create([
                'currency' => 'gbp',
                'amount' => $amount,
                'customer' => $this->customer->id,
                'source' => $cart->provider_id
            ]);
        } catch (Exception $e) {
            throw new PaymentFaildException();
        }
    }
...

```

`2` - Create new Exception file `PaymentFaildException`

```command
    php artisan make:exception PaymentFaildException
```

`3` - Edit `app/Exceptions/PaymentFaildException.php`

```php
<?php

namespace App\Exceptions;

use Exception;

class PaymentFaildException extends Exception
{
    //
}
```

`4` - Edit `app/Listeners/Order/ProcessPayment.php`

```php
use App\Events\Order\OrderPaymentFaild;
use App\Exceptions\PaymentFaildException;
...

public function handle(OrderCreated $event)
{
    $order = $event->order;

    try {
        $this->gateway->withUser($order->user)
            ->getCustomer()
            ->charge(
                $order->paymentMethod,
                $order->total()->amount()
            );
        // event
    } catch (PaymentFaildException $e) {
        event(new OrderPaymentFaild($order));
    }
}
```

`5` - Create new event file `OrderPaymentFaild`

```command
php artisan make:event Order\\OrderPaymentFaild
```

`6` - Edit `app/Events/Order/OrderPaymentFaild.php`

```php
<?php

namespace App\Events\Order;


use App\Models\Order;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class OrderPaymentFaild
{
    use Dispatchable, SerializesModels;

    public $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }
}

```

`7` - Edit `app/Providers/EventServiceProvider.php`

```php
...
 protected $listen = [
     ...
    'App\Events\Order\OrderPaymentFaild' => [
        'App\Listeners\Order\MarkOrderPaymentFailed',
    ]
];
...
```

`8` - Create new Listener file `MarkOrderFailed.php` into `app/Listeners/Order`

`9` - Edit `app/Listeners/Order/MarkOrderPaymentFailed.php`

```php
<?php

namespace App\Listeners\Order;

use App\Models\Order;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class MarkOrderPaymentFailed
{

    public function handle($event)
    {
        $event->order->update([
            'status' => Order::PAYMENT_FAILED
        ]);
    }
}
```

<a name="section-8"></a>

## Episode-119 Handling a successful payment

`1` - Edit `app/Listeners/Order/ProcessPayment.php`

```php
use App\Events\Order\OrderPaid;
...
public function handle(OrderCreated $event)
    {
        ...
        try {
            ...
            event(new OrderPaid($order));
        } catch (PaymentFaildException $e) {
            ...
        }
    }
...
```

`2` - Create new file `OrderPaid.php` into `app/Events/Order`

`3` - Edit `app/Events/Order/OrderPaid.php`

```php
<?php

namespace App\Events\Order;


use App\Models\Order;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class OrderPaid
{
    use Dispatchable, SerializesModels;

    public $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }
}
```

`4` - Edit `app/Providers/EventServiceProvider.php`

```php
...
protected $listen = [
        ...
        'App\Events\Order\OrderPaid' => [
            'App\Listeners\Order\MarkOrderProcessing',
        ]
    ];
...
```

`5` - Create new listener `MarkOrderProcessing.php` into `app/Listeners/Order`

`6` - Edit `app/Listeners/Order/MarkOrderProcessing.php`

```php
<?php

namespace App\Listeners\Order;

use App\Models\Order;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class MarkOrderProcessing
{

    public function handle($event)
    {
        $event->order->update([
            'status' => Order::PROCESSING
        ]);
    }
}
```

## Displaying a successful payment status `Front-end`

`7` - Edit `resources/js/components/orders/Order.vue`

- js part changes

```js
<script>
import OrderStatusProcessing from "./statuses/OrderStatus-processing";
...
export default {
  components: {
    processing: OrderStatusProcessing,
    ...
  },
  ...
};
</script>
```

`8` - Create new file `OrderStatus-processing.vue` into `resources/js/components/orders/statuses`

`9` Edit - `resources/js/components/orders/statuses/OrderStatus-processing.vue`

```html
<template>
  <div class="text-default">Processing</div>
</template>
```

<a name="section-9"></a>

## Episode-120 Fixing failing 'cart empty' test

`1` - Edit `tests/Feature/Orders/OrderStoreTest.php`

```php
...
protected function orderDependencies(User $user)
    {
        // Create new stripe account
        $stripeCustomer = \Stripe\Customer::create([
            'email' => $user->email
        ]);
        // Update the user with a real stripe account
        $user->update([
            'gateway_customer_id' => $stripeCustomer->id
        ]);

        $address = factory(Address::class)->create([
            'user_id' => $user->id
        ]);

        $shipping = factory(ShippingMethod::class)->create();
        $shipping->countries()->attach($address->country);

        $payment = factory(PaymentMethod::class)->create([
            'user_id' => $user->id
        ]);

        return [$address, $shipping, $payment];
    }
}
...
```

<a name="section-10"></a>

## Episode-121 Testing listeners

`1` - Create new test file `EmptyCartListenerTest` with will be unit test

```command
php artisan make:test Listeners\\EmptyCartListenerTest --unit
```

`2` - Edit `tests/Unit/Listeners/EmptyCartListenerTest.php`

```php
<?php

namespace Tests\Unit\Listeners;

use App\Cart\Cart;
use Tests\TestCase;
use App\Models\User;
use App\Models\ProductVariation;
use App\Listeners\Order\EmptyCart;

class EmptyCartListenerTest extends TestCase
{

    public function test_is_should_clear_the_cart()
    {
        $cart = new Cart(
            $user = factory(User::class)->create()
        );


        $user->cart()->attach(
            $product = factory(ProductVariation::class)->create()
        );

        $listener = new EmptyCart($cart);

        $listener->handle();

        $this->assertEmpty($user->cart);
    }
}
```

`3` - Create new test file `MarkOrderPaymentFailedListenerTest` this will be unit test

```command
php artisan make:test Listeners\\MarkOrderPaymentFailedListenerTest --unit
```

`4` - Edit `tests/Unit/Listeners/MarkOrderPaymentFailedListenerTest.php`

```php
<?php
namespace Tests\Unit\Listeners;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Events\Order\OrderPaymentFaild;
use App\Listeners\Order\MarkOrderPaymentFailed;

class MarkOrderPaymentFailedListenerTest extends TestCase
{

    public function test_marks_order_as_payment_failed()
    {
        $event = new OrderPaymentFaild(
            $order = factory(Order::class)->create([
                'user_id' => factory(User::class)->create()
            ])
        );

        $listener = new MarkOrderPaymentFailed();

        $listener->handle($event);

        $this->assertEquals($order->fresh()->status, Order::PAYMENT_FAILED);
    }
}
```

`5` - Create new test file `MarkOrderProcessingListenerTest` this will be unit test

```command
php artisan make:test Listeners\\MarkOrderProcessingListenerTest --unit
```

`6` - Edit `tests/Unit/Listeners/MarkOrderProcessingListenerTest.php`

```php
<?php

namespace Tests\Unit\Listeners;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Events\Order\OrderPaid;
use App\Listeners\Order\MarkOrderProcessing;

class MarkOrderProcessingListenerTest extends TestCase
{

    public function test_it_marks_order_as_processing()
    {
        $event = new OrderPaid(
            $order = factory(Order::class)->create([
                'user_id' => factory(User::class)->create()
            ])
        );

        $listener = new MarkOrderProcessing();

        $listener->handle($event);

        $this->assertEquals($order->fresh()->status, Order::PROCESSING);
    }
}
```

`7` - Edit `app/Listeners/Order/EmptyCart.php`

```php
<?php
namespace App\Listeners\Order;

use App\Cart\Cart;

class EmptyCart
{
    protected $cart;
    public function __construct(Cart $cart)
    {
        $this->cart = $cart;
    }

    public function handle()
    {
        $this->cart->empty();
    }
}
```
