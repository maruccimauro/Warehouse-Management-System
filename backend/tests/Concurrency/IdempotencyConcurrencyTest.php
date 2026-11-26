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
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Modules\Sample\SampleServiceProvider;
use Tests\Support\Concurrency\ConcurrentRun;

/*
| Idempotencia bajo concurrencia real (E3.10, INV-007): la transacción del
| middleware es la más externa, como en producción, sin la de RefreshDatabase.
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
        [new MovementLine($this->product->id, $this->location->id, 561, 100)],
        reasonCode: AdjustmentReason::InitialLoad,
    )));

    $this->body = ['warehouse' => $this->warehouse->id, 'product' => $this->product->id, 'location' => $this->location->id, 'quantity' => 10];
});

it('runs one operation for ten simultaneous requests with the same key', function () {
    $results = ConcurrentRun::scenario('http-withdraw', array_fill(0, 10, [...$this->body, 'user' => $this->user->id, 'key' => 'retiro-simultaneo']));

    $ids = array_unique(array_map(fn (array $result): mixed => $result['body']['id'] ?? null, $results));

    expect(array_unique(array_column($results, 'http_status')))->toBe([201])
        ->and($ids)->toHaveCount(1)
        ->and(count(array_filter(array_column($results, 'replayed'))))->toBe(9)
        ->and(InventoryOperation::query()->where('type', OperationType::AdjustmentOut)->count())->toBe(1)
        ->and((int) Stock::query()->value('quantity_on_hand'))->toBe(90);
});

it('answers 409 when the key stays in use by a request that does not finish in time', function () {
    config(['wms.idempotency.lock_timeout_ms' => 300, 'database.connections.blocker' => config('database.connections.pgsql')]);
    $blocker = DB::connection('blocker');
    $blocker->beginTransaction();
    $blocker->insert(
        "INSERT INTO idempotency_keys (user_id, key, request_method, request_path, request_hash, response_status, response_body) VALUES (?, 'en-curso', 'POST', 'x', ?, 0, 'null')",
        [$this->user->id, str_repeat('0', 64)],
    );

    try {
        $this->actingAs($this->user)
            ->postJson('/api/v1/sample/idempotent/withdraw', $this->body, [Idempotency::HEADER => 'en-curso'])
            ->assertStatus(409)
            ->assertJson(['code' => 'IDEMPOTENCY_REQUEST_IN_PROGRESS']);
    } finally {
        $blocker->rollBack();
        $blocker->disconnect();
    }

    expect(InventoryOperation::query()->where('type', OperationType::AdjustmentOut)->count())->toBe(0);
});

it('retries the whole request when the action hits a deadlock', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/sample/idempotent/flaky', [], [Idempotency::HEADER => 'con-deadlock'])
        ->assertCreated()
        ->assertJson(['calls' => 2]);

    expect(DB::table('idempotency_keys')->where('key', 'con-deadlock')->value('response_status'))->toBe(201);
});
