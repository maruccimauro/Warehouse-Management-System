<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Data\MovementLine;
use App\Modules\Inventory\Data\OperationDraft;
use App\Modules\Inventory\Enums\AdjustmentReason;
use App\Modules\Inventory\Enums\OperationType;
use App\Modules\Inventory\Models\InventoryOperation;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Warehouse\Enums\ZoneType;
use App\Modules\Warehouse\Models\Warehouse;
use App\Shared\Http\Idempotency\Idempotency;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Fixtures\Modules\Sample\SampleServiceProvider;

/*
| Middleware de idempotencia (E3.10, ADR-007, INV-007) sobre una ruta del
| módulo de ejemplo que retira stock con StockLedger. El reintento ante
| deadlock y las peticiones simultáneas están en tests/Concurrency, porque
| necesitan la transacción de la petición como la más externa.
*/

beforeEach(function () {
    $this->app->register(SampleServiceProvider::class);
    $this->app['router']->getRoutes()->refreshNameLookups();

    $this->user = User::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
    $zone = $this->warehouse->zones()->create(['code' => 'ALM', 'name' => 'Almacenamiento', 'type' => ZoneType::Storage]);
    $this->location = $this->warehouse->locations()->create(['zone_id' => $zone->id, 'code' => 'A-01-01']);
    $this->product = Product::factory()->create();

    DB::transaction(fn () => app(StockLedger::class)->apply(new OperationDraft(
        OperationType::AdjustmentIn,
        $this->warehouse->id,
        $this->user->id,
        [new MovementLine($this->product->id, $this->location->id, 561, 10)],
        reasonCode: AdjustmentReason::InitialLoad,
    )));

    $this->body = ['warehouse' => $this->warehouse->id, 'product' => $this->product->id, 'location' => $this->location->id, 'quantity' => 3];
    $this->actingAs($this->user);
});

/**
 * @param  array<string, mixed>|null  $body
 */
function idempotentWithdraw(?string $key, ?array $body = null, string $path = 'withdraw'): TestResponse
{
    $headers = $key === null ? [] : [Idempotency::HEADER => $key];

    return test()->postJson('/api/v1/sample/idempotent/'.$path, $body ?? test()->body, $headers);
}

function withdrawals(): int
{
    return InventoryOperation::query()->where('type', OperationType::AdjustmentOut)->count();
}

it('requires a valid key before running anything (428)', function (?string $key) {
    idempotentWithdraw($key)
        ->assertStatus(428)
        ->assertJson(['code' => 'IDEMPOTENCY_KEY_REQUIRED']);

    expect(withdrawals())->toBe(0)
        ->and(DB::table('idempotency_keys')->count())->toBe(0);
})->with([
    'without the header' => [null],
    'empty' => ['   '],
    'longer than 100 characters' => [str_repeat('k', 101)],
]);

it('runs the operation once and replays the stored response for the same key and body', function () {
    $first = idempotentWithdraw('retiro-1')->assertCreated();
    $second = idempotentWithdraw('retiro-1')->assertCreated();

    expect($second->json())->toBe($first->json())
        ->and($second->headers->get(Idempotency::REPLAYED_HEADER))->toBe('true')
        ->and($first->headers->has(Idempotency::REPLAYED_HEADER))->toBeFalse()
        ->and(withdrawals())->toBe(1)
        ->and((int) Stock::query()->value('quantity_on_hand'))->toBe(7)
        ->and(InventoryOperation::query()->where('type', OperationType::AdjustmentOut)->value('idempotency_key'))->toBe('retiro-1');
});

it('treats the same body with its fields in another order as the same request', function () {
    idempotentWithdraw('retiro-1')->assertCreated();

    idempotentWithdraw('retiro-1', array_reverse($this->body, preserve_keys: true))
        ->assertCreated()
        ->assertHeader(Idempotency::REPLAYED_HEADER, 'true');

    expect(withdrawals())->toBe(1);
});

it('rejects the same key with another body or on another route (422)', function (array $change, string $path) {
    idempotentWithdraw('retiro-1')->assertCreated();

    idempotentWithdraw('retiro-1', [...$this->body, ...$change], $path)
        ->assertStatus(422)
        ->assertJson(['code' => 'IDEMPOTENCY_KEY_REUSED']);

    expect(withdrawals())->toBe(1);
})->with([
    'another quantity' => [['quantity' => 4], 'withdraw'],
    'another route' => [[], 'flaky'],
]);

it('keeps the keys of each user apart', function () {
    idempotentWithdraw('retiro-1')->assertCreated();

    $this->actingAs(User::factory()->create());
    idempotentWithdraw('retiro-1')->assertCreated()->assertHeaderMissing(Idempotency::REPLAYED_HEADER);

    expect(withdrawals())->toBe(2);
});

it('leaves no key after a business rejection, so the retry is evaluated again', function () {
    idempotentWithdraw('retiro-1', [...$this->body, 'quantity' => 11])
        ->assertStatus(422)
        ->assertJson(['code' => 'INSUFFICIENT_STOCK']);

    expect(DB::table('idempotency_keys')->count())->toBe(0);

    idempotentWithdraw('retiro-1', [...$this->body, 'quantity' => 11])->assertStatus(422);
    idempotentWithdraw('retiro-2')->assertCreated();

    expect(withdrawals())->toBe(1);
});

it('prunes the keys older than the retention period', function () {
    idempotentWithdraw('vieja')->assertCreated();
    idempotentWithdraw('nueva', [...$this->body, 'quantity' => 1])->assertCreated();
    DB::table('idempotency_keys')->where('key', 'vieja')->update(['created_at' => now()->subDays(8)]);

    Artisan::call('wms:prune-idempotency-keys');

    expect(DB::table('idempotency_keys')->pluck('key')->all())->toBe(['nueva']);
});
