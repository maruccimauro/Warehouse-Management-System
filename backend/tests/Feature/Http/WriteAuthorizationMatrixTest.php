<?php

use App\Modules\Catalog\Actions\CreateCategory;
use App\Modules\Catalog\Actions\CreateSupplier;
use App\Modules\Catalog\Actions\CreateUnitOfMeasure;
use App\Modules\Catalog\Actions\SetProductAdjustmentThresholds;
use App\Modules\Catalog\Actions\SetProductSupplier;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Authorization\Permission;
use App\Modules\Identity\Authorization\Role;
use App\Modules\Inventory\Actions\AdjustStock;
use App\Modules\Inventory\Data\AdjustmentItem;
use App\Modules\Inventory\Enums\AdjustmentReason;
use App\Modules\Inventory\Enums\MovementDirection;
use App\Modules\Warehouse\Actions\CreateWarehouse;
use App\Modules\Warehouse\Enums\ZoneType;
use App\Shared\Http\Idempotency\Idempotency;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;

/*
| Matriz de autorización de escritura (criterio de salida de E2): cada endpoint
| de escritura contra cada rol, más un usuario sin rol. El usuario tiene
| asignado el depósito del recurso, así que la respuesta depende solo del
| permiso: 403 FORBIDDEN sin él y, con él, cualquier respuesta que no sea de
| autorización (el cuerpo va vacío, así que lo habitual es 422: los Form
| Requests autorizan antes de validar).
|
| La matriz es exhaustiva: una ruta de escritura registrada que no figura acá
| hace fallar la prueba de cobertura, así ningún endpoint nuevo queda sin caso
| permitido y denegado. Las pruebas de cada módulo cubren el alcance por
| depósito (404 y 403) y el detalle de cada regla.
*/

const OWN_ACCOUNT = 'own';
const PUBLIC_ENDPOINT = 'public';

/**
 * Ruta de escritura (método y URI tal como se registra) => permiso requerido,
 * OWN_ACCOUNT si opera sobre la cuenta propia con cualquier sesión, o
 * PUBLIC_ENDPOINT si no requiere sesión (cubierto en AuthenticationTest).
 *
 * @return array<string, Permission|string>
 */
function writeEndpointPermissions(): array
{
    return [
        'POST api/v1/auth/login' => PUBLIC_ENDPOINT,
        'POST api/v1/auth/logout' => PUBLIC_ENDPOINT,
        'PATCH api/v1/me' => OWN_ACCOUNT,
        'PUT api/v1/me/favorites' => OWN_ACCOUNT,
        'PATCH api/v1/me/inbox' => OWN_ACCOUNT,
        'PUT api/v1/me/parameters' => OWN_ACCOUNT,
        'PUT api/v1/me/password' => OWN_ACCOUNT,
        'POST api/v1/warehouses' => Permission::WarehousesManage,
        'PATCH api/v1/warehouses/{warehouse}' => Permission::WarehousesManage,
        'POST api/v1/warehouses/{warehouse}/zones' => Permission::LocationsManage,
        'PATCH api/v1/zones/{zone}' => Permission::LocationsManage,
        'POST api/v1/warehouses/{warehouse}/locations' => Permission::LocationsManage,
        'POST api/v1/warehouses/{warehouse}/locations/bulk' => Permission::LocationsManage,
        'PATCH api/v1/locations/{location}' => Permission::LocationsManage,
        'POST api/v1/locations/{location}/block' => Permission::LocationsBlock,
        'POST api/v1/locations/{location}/unblock' => Permission::LocationsBlock,
        'POST api/v1/categories' => Permission::CatalogManage,
        'PATCH api/v1/categories/{category}' => Permission::CatalogManage,
        'POST api/v1/units' => Permission::CatalogManage,
        'PATCH api/v1/units/{unit}' => Permission::CatalogManage,
        'POST api/v1/products' => Permission::CatalogManage,
        'PATCH api/v1/products/{product}' => Permission::CatalogManage,
        'PUT api/v1/products/{product}/warehouse-settings/{warehouse}/min-stock' => Permission::CatalogMinStockManage,
        'PUT api/v1/products/{product}/warehouse-settings/{warehouse}/adjustment-thresholds' => Permission::InventoryThresholdsManage,
        'POST api/v1/suppliers' => Permission::SuppliersManage,
        'PATCH api/v1/suppliers/{supplier}' => Permission::SuppliersManage,
        'PUT api/v1/suppliers/{supplier}/products/{product}' => Permission::SuppliersManage,
        'DELETE api/v1/suppliers/{supplier}/products/{product}' => Permission::SuppliersManage,
        'POST api/v1/users' => Permission::UsersManage,
        'PATCH api/v1/users/{user}' => Permission::UsersManage,
        'POST api/v1/inventory/adjustments' => Permission::InventoryAdjust,
        'POST api/v1/inventory/adjustments/{adjustment}/approve' => Permission::InventoryAdjustApprove,
        'POST api/v1/inventory/adjustments/{adjustment}/reject' => Permission::InventoryAdjustApprove,
        'POST api/v1/inventory/initial-loads' => Permission::InventoryInitialLoad,
        'POST api/v1/inventory/transfers' => Permission::InventoryTransfer,
    ];
}

/**
 * @return list<string>
 */
function sessionWriteEndpoints(): array
{
    return array_keys(array_filter(writeEndpointPermissions(), fn (Permission|string $rule): bool => $rule !== PUBLIC_ENDPOINT));
}

dataset('session write endpoints', fn (): array => array_combine(sessionWriteEndpoints(), sessionWriteEndpoints()));

dataset('roles', [
    'administrador' => [Role::Administrator],
    'gerente de operaciones' => [Role::OperationsManager],
    'jefe de operaciones' => [Role::OperationsLead],
    'supervisor' => [Role::Supervisor],
    'operador' => [Role::Operator],
    'compras' => [Role::Purchasing],
    'auditor' => [Role::Auditor],
    'sin rol' => [null],
]);

beforeEach(function () {
    app(SyncRolesAndPermissions::class)();

    $this->central = app(CreateWarehouse::class)('CENTRAL', 'Depósito central');
    $zone = $this->central->zones()->create(['code' => 'ALM', 'name' => 'Almacenamiento', 'type' => ZoneType::Storage]);
    $location = $this->central->locations()->create(['zone_id' => $zone->id, 'code' => 'A-01']);
    $product = Product::factory()->create();
    $supplier = app(CreateSupplier::class)(['tax_id' => 'DEV-0001', 'name' => 'ACME']);
    // El vínculo existe para que el DELETE autorizado no responda 404.
    app(SetProductSupplier::class)($supplier, $product, null, null);
    // Ajuste con una posición pendiente, de otro usuario: la decisión llega a la validación.
    app(SetProductAdjustmentThresholds::class)($product, $this->central, 1, null);
    $adjustment = app(AdjustStock::class)($this->central->id, MovementDirection::In, AdjustmentReason::Found, [new AdjustmentItem($product->id, $location->id, 5)], userWithAccess(Role::Operator, [$this->central])->id);

    $this->routeParameters = [
        '{warehouse}' => $this->central->id,
        '{zone}' => $zone->id,
        '{location}' => $location->id,
        '{category}' => app(CreateCategory::class)('Ferretería')->id,
        '{unit}' => app(CreateUnitOfMeasure::class)('KG', 'Kilogramo')->id,
        '{product}' => $product->id,
        '{supplier}' => $supplier->id,
        '{user}' => userWithAccess(Role::Operator)->id,
        '{adjustment}' => $adjustment->id,
    ];
});

/**
 * @return array{0: string, 1: string} método y URI con los identificadores de los recursos
 */
function resolveWriteEndpoint(string $endpoint, array $parameters): array
{
    [$method, $uri] = explode(' ', $endpoint, 2);

    return [$method, '/'.strtr($uri, array_map('strval', $parameters))];
}

it('covers every registered write route', function () {
    $registered = collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => str_starts_with($route->uri(), 'api/v1/'))
        ->flatMap(fn (Route $route): array => array_map(
            fn (string $method): string => "{$method} {$route->uri()}",
            array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']),
        ))
        ->sort()
        ->values()
        ->all();

    $declared = collect(writeEndpointPermissions())->keys()->sort()->values()->all();

    expect($declared)->toBe($registered);
});

it('answers each role according to its permissions', function (string $endpoint, ?Role $role) {
    $rule = writeEndpointPermissions()[$endpoint];
    $allowed = $rule === OWN_ACCOUNT || ($role !== null && in_array($rule, $role->permissions(), true));
    [$method, $uri] = resolveWriteEndpoint($endpoint, $this->routeParameters);

    // Con Idempotency-Key, para que una ruta idempotente llegue a la
    // autorización en lugar de responder 428; las demás la ignoran. Las
    // operaciones de inventario leen el depósito del cuerpo.
    $response = $this->actingAs(userWithAccess($role, [$this->central]))
        ->withHeader(Idempotency::HEADER, (string) Str::uuid())
        ->json($method, $uri, ['warehouse_id' => $this->central->id]);

    if ($allowed) {
        expect($response->status())->not->toBeIn([401, 403, 404, 428]);
    } else {
        $response->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
    }
})->with('session write endpoints')->with('roles');

it('requires a session', function (string $endpoint) {
    [$method, $uri] = resolveWriteEndpoint($endpoint, $this->routeParameters);

    $this->json($method, $uri)->assertUnauthorized();
})->with('session write endpoints');
