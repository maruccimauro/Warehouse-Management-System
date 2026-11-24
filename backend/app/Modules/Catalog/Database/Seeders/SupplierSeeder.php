<?php

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Actions\CreateSupplier;
use App\Modules\Catalog\Actions\SetProductSupplier;
use App\Modules\Catalog\Actions\UpdateSupplier;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use Illuminate\Database\Seeder;

/**
 * Proveedores de desarrollo y su relación con los productos de CatalogSeeder,
 * creados con las mismas Actions que la API. Idempotente: un proveedor se
 * identifica por su identificador fiscal y un vínculo por producto y
 * proveedor; solo se crea lo que falta y nada existente se modifica.
 *
 * Los identificadores fiscales llevan el prefijo DEV y los emails el dominio
 * reservado .test, para que no coincidan con proveedores reales. Uno de los
 * proveedores queda inactivo, para probar REG-018 desde la UI.
 */
class SupplierSeeder extends Seeder
{
    /** @var list<array{tax_id: string, name: string, email: string|null, phone: string|null, is_active: bool}> */
    public const SUPPLIERS = [
        ['tax_id' => 'DEV-0001', 'name' => 'Bulonera del Sur', 'email' => 'ventas@buloneradelsur.test', 'phone' => '+54 11 4000-0001', 'is_active' => true],
        ['tax_id' => 'DEV-0002', 'name' => 'Distribuidora Eléctrica Norte', 'email' => 'pedidos@electricanorte.test', 'phone' => '+54 11 4000-0002', 'is_active' => true],
        ['tax_id' => 'DEV-0003', 'name' => 'Química Industrial Andina', 'email' => 'comercial@quimicaandina.test', 'phone' => null, 'is_active' => true],
        ['tax_id' => 'DEV-0004', 'name' => 'Herramientas Patagonia', 'email' => null, 'phone' => '+54 299 400-0004', 'is_active' => false],
    ];

    /**
     * Vínculos por proveedor: SKU => [código del proveedor, plazo en días].
     *
     * @var array<string, array<string, array{0: string|null, 1: int|null}>>
     */
    public const LINKS = [
        'DEV-0001' => [
            'TOR-M6-30' => ['BS-6030', 5],
            'TOR-M8-40' => ['BS-8040', 5],
            'TUE-M6' => ['BS-T06', 5],
            'GRA-LIT' => [null, 10],
        ],
        'DEV-0002' => [
            'CAB-2-5' => ['EN-CU25', 7],
            'LAM-LED-9' => ['EN-LED9W', 7],
        ],
        'DEV-0003' => [
            'DET-5L' => ['QA-DET5', 15],
            'GRA-LIT' => ['QA-GLI', 15],
        ],
        'DEV-0004' => [
            'MAR-500' => ['HP-M500', 20],
            'DES-PH2' => ['HP-DPH2', 20],
        ],
    ];

    public function __construct(
        private readonly CreateSupplier $createSupplier,
        private readonly UpdateSupplier $updateSupplier,
        private readonly SetProductSupplier $setProductSupplier,
    ) {}

    public function run(): void
    {
        foreach (self::SUPPLIERS as $data) {
            $supplier = Supplier::query()->where('tax_id', Supplier::normalizeTaxId($data['tax_id']))->first();

            if ($supplier === null) {
                $supplier = ($this->createSupplier)([
                    'tax_id' => $data['tax_id'],
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                ]);

                if (! $data['is_active']) {
                    ($this->updateSupplier)($supplier, [], false);
                }
            }

            $this->seedLinks($supplier, self::LINKS[$data['tax_id']]);
        }
    }

    /**
     * Solo vincula los productos que existen (CatalogSeeder corre antes) y
     * todavía no están vinculados a este proveedor.
     *
     * @param  array<string, array{0: string|null, 1: int|null}>  $links
     */
    private function seedLinks(Supplier $supplier, array $links): void
    {
        foreach ($links as $sku => [$supplierSku, $leadTimeDays]) {
            $product = Product::query()->where('sku', $sku)->first();

            if ($product === null || $supplier->productLinks()->where('product_id', $product->id)->exists()) {
                continue;
            }

            ($this->setProductSupplier)($supplier, $product, $supplierSku, $leadTimeDays);
        }
    }
}
