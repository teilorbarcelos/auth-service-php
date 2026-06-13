<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Product\ProductService;
use App\Modules\Product\ProductRepository;
use App\Modules\Product\Product;
use Tests\WebTestCase;

class ProductServiceTest extends WebTestCase
{
    private ProductService $productService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productService = new ProductService(
            new ProductRepository(),
            new \App\Infrastructure\Auth\UserSession()
        );
    }

    public function testListProducts(): void
    {
        Product::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'sku' => 'PROD-1',
            'name' => 'Product 1',
            'price' => 10.50,
            'active' => true
        ]);

        $result = $this->productService->listItems([]);
        $this->assertEquals(1, $result['total']);
        $this->assertEquals('Product 1', $result['items'][0]['name']);
    }
}
