<?php

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Actions\CreateCategory;
use App\Modules\Catalog\Actions\CreateProduct;
use App\Modules\Catalog\Actions\CreateUnitOfMeasure;
use App\Modules\Catalog\Actions\SetProductWarehouseSettings;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Catálogo de desarrollo, creado con las mismas Actions que la API.
 * Idempotente: una unidad se identifica por su código, una categoría por su
 * nombre bajo su padre y un producto por su SKU; solo se crea lo que falta y
 * nada existente se modifica.
 *
 * Las unidades siguen ADR-005: lo que se vende por peso o volumen usa gramos
 * o mililitros como base. Los códigos de barras son EAN-13 con dígito
 * verificador válido.
 */
class CatalogSeeder extends Seeder
{
    /** @var list<array{code: string, name: string}> */
    public const UNITS = [
        ['code' => 'UN', 'name' => 'Unidad'],
        ['code' => 'G', 'name' => 'Gramo'],
        ['code' => 'ML', 'name' => 'Mililitro'],
        ['code' => 'MM', 'name' => 'Milímetro'],
    ];

    /** @var array<string, list<string>> Categoría raíz => subcategorías */
    public const CATEGORIES = [
        'Ferretería' => ['Tornillería', 'Herramientas manuales'],
        'Electricidad' => ['Cables', 'Iluminación'],
        'Limpieza' => [],
    ];

    /** @var list<array{sku: string, barcode: string|null, name: string, category: array{0: string, 1: string|null}, uom: string, weight_grams: int|null}> */
    public const PRODUCTS = [
        ['sku' => 'TOR-M6-30', 'barcode' => '7790000000010', 'name' => 'Tornillo hexagonal M6 x 30 mm', 'category' => ['Ferretería', 'Tornillería'], 'uom' => 'UN', 'weight_grams' => 9],
        ['sku' => 'TOR-M8-40', 'barcode' => '7790000000027', 'name' => 'Tornillo hexagonal M8 x 40 mm', 'category' => ['Ferretería', 'Tornillería'], 'uom' => 'UN', 'weight_grams' => 20],
        ['sku' => 'TUE-M6', 'barcode' => '7790000000034', 'name' => 'Tuerca hexagonal M6', 'category' => ['Ferretería', 'Tornillería'], 'uom' => 'UN', 'weight_grams' => 3],
        ['sku' => 'MAR-500', 'barcode' => '7790000000041', 'name' => 'Martillo de carpintero 500 g', 'category' => ['Ferretería', 'Herramientas manuales'], 'uom' => 'UN', 'weight_grams' => 650],
        ['sku' => 'DES-PH2', 'barcode' => '7790000000058', 'name' => 'Destornillador Phillips PH2', 'category' => ['Ferretería', 'Herramientas manuales'], 'uom' => 'UN', 'weight_grams' => 120],
        ['sku' => 'CAB-2-5', 'barcode' => '7790000000065', 'name' => 'Cable unipolar 2,5 mm² (por metro, en milímetros)', 'category' => ['Electricidad', 'Cables'], 'uom' => 'MM', 'weight_grams' => null],
        ['sku' => 'LAM-LED-9', 'barcode' => '7790000000072', 'name' => 'Lámpara LED 9 W E27', 'category' => ['Electricidad', 'Iluminación'], 'uom' => 'UN', 'weight_grams' => 45],
        ['sku' => 'DET-5L', 'barcode' => '7790000000089', 'name' => 'Detergente industrial bidón 5 L', 'category' => ['Limpieza', null], 'uom' => 'ML', 'weight_grams' => null],
        ['sku' => 'GRA-LIT', 'barcode' => null, 'name' => 'Grasa de litio a granel', 'category' => ['Ferretería', null], 'uom' => 'G', 'weight_grams' => null],
    ];

    /**
     * Stock mínimo por depósito (DM-21), en la unidad base. Se carga solo si
     * el depósito existe (WarehouseSeeder corre antes) y el producto todavía
     * no tiene datos en él.
     *
     * @var array<string, array<string, int>> Código de depósito => SKU => stock mínimo
     */
    public const MIN_STOCK = [
        'CENTRAL' => ['TOR-M6-30' => 500, 'TUE-M6' => 500, 'LAM-LED-9' => 40],
        'NORTE' => ['TOR-M6-30' => 200],
    ];

    public function __construct(
        private readonly CreateUnitOfMeasure $createUnit,
        private readonly CreateCategory $createCategory,
        private readonly CreateProduct $createProduct,
        private readonly SetProductWarehouseSettings $setSettings,
    ) {}

    public function run(): void
    {
        $units = [];
        foreach (self::UNITS as $unit) {
            $units[$unit['code']] = UnitOfMeasure::query()->where('code', $unit['code'])->first()
                ?? ($this->createUnit)($unit['code'], $unit['name']);
        }

        /** @var array<string, Category> $categories Ruta raíz o raíz/subcategoría => categoría */
        $categories = [];
        foreach (self::CATEGORIES as $root => $children) {
            $parent = $this->category($root, null);
            $categories[$root] = $parent;

            foreach ($children as $child) {
                $categories[$root.'/'.$child] = $this->category($child, $parent);
            }
        }

        foreach (self::PRODUCTS as $product) {
            if (Product::query()->where('sku', $product['sku'])->exists()) {
                continue;
            }

            [$root, $child] = $product['category'];

            ($this->createProduct)([
                'sku' => $product['sku'],
                'barcode' => $product['barcode'],
                'name' => $product['name'],
                'category_id' => $categories[$child === null ? $root : $root.'/'.$child]->id,
                'base_uom_id' => $units[$product['uom']]->id,
                'weight_grams' => $product['weight_grams'],
            ]);
        }

        $this->seedMinStock();
    }

    private function seedMinStock(): void
    {
        foreach (self::MIN_STOCK as $warehouseCode => $minimums) {
            $warehouse = Warehouse::query()->where('code', $warehouseCode)->first();

            if ($warehouse === null) {
                continue;
            }

            foreach ($minimums as $sku => $minStock) {
                $product = Product::query()->where('sku', $sku)->sole();

                if ($product->warehouseSettings()->where('warehouse_id', $warehouse->id)->doesntExist()) {
                    ($this->setSettings)($product, $warehouse, $minStock);
                }
            }
        }
    }

    private function category(string $name, ?Category $parent): Category
    {
        return Category::query()->where('name', $name)->where('parent_id', $parent?->id)->first()
            ?? ($this->createCategory)($name, $parent);
    }
}
