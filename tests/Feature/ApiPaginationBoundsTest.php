<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApiPaginationBoundsTest extends TestCase
{
    use RefreshDatabase;

    private const ROWS = 105;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->admin()->create());
    }

    /**
     * @return array<string, array{0: string, 1: callable(): void}>
     */
    public static function listEndpoints(): array
    {
        return [
            'clients' => ['clients', function (): void {
                Client::factory()->count(self::ROWS)->create();
            }],
            'products' => ['products', function (): void {
                Product::factory()->count(self::ROWS)->create();
            }],
            'sales' => ['sales', function (): void {
                $client = Client::factory()->create();
                $seller = User::factory()->seller()->create();

                Sale::factory()->count(self::ROWS)->create([
                    'client_id' => $client->id,
                    'seller_id' => $seller->id,
                ]);
            }],
            'inventory' => ['inventory', function (): void {
                $product = Product::factory()->create();
                $user = User::factory()->manager()->create();

                InventoryMovement::factory()->count(self::ROWS)->create([
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                ]);
            }],
        ];
    }

    /**
     * @return array<string, array{0: callable(): string, 1: string}>
     */
    public static function nestedEndpoints(): array
    {
        return [
            'product recent movements' => [
                function (): string {
                    $product = Product::factory()->create();
                    $user = User::factory()->manager()->create();

                    InventoryMovement::factory()->count(self::ROWS)->create([
                        'product_id' => $product->id,
                        'user_id' => $user->id,
                    ]);

                    return "/api/products/{$product->id}";
                },
                'data.recent_movements',
            ],
            'client sales' => [
                function (): string {
                    $client = Client::factory()->create();
                    $seller = User::factory()->seller()->create();

                    Sale::factory()->count(self::ROWS)->create([
                        'client_id' => $client->id,
                        'seller_id' => $seller->id,
                    ]);

                    return "/api/clients/{$client->id}";
                },
                'data.sales',
            ],
        ];
    }

    #[DataProvider('listEndpoints')]
    public function test_list_endpoints_use_default_page_size_when_absent(string $endpoint, callable $seed): void
    {
        $seed();

        $this->getJson("/api/{$endpoint}")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonCount(10, 'data');
    }

    #[DataProvider('listEndpoints')]
    public function test_list_endpoints_clamp_zero_to_one(string $endpoint, callable $seed): void
    {
        $seed();

        $this->getJson("/api/{$endpoint}?per_page=0")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonCount(1, 'data');
    }

    #[DataProvider('listEndpoints')]
    public function test_list_endpoints_clamp_negative_to_one(string $endpoint, callable $seed): void
    {
        $seed();

        $this->getJson("/api/{$endpoint}?per_page=-5")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonCount(1, 'data');
    }

    #[DataProvider('listEndpoints')]
    public function test_list_endpoints_accept_minimum_boundary(string $endpoint, callable $seed): void
    {
        $seed();

        $this->getJson("/api/{$endpoint}?per_page=1")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonCount(1, 'data');
    }

    #[DataProvider('listEndpoints')]
    public function test_list_endpoints_accept_maximum_boundary(string $endpoint, callable $seed): void
    {
        $seed();

        $this->getJson("/api/{$endpoint}?per_page=100")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonCount(100, 'data');
    }

    #[DataProvider('listEndpoints')]
    public function test_list_endpoints_clamp_excessively_large_to_maximum(string $endpoint, callable $seed): void
    {
        $seed();

        $this->getJson("/api/{$endpoint}?per_page=9999")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonCount(100, 'data');
    }

    #[DataProvider('listEndpoints')]
    public function test_list_endpoints_clamp_non_numeric_to_one(string $endpoint, callable $seed): void
    {
        $seed();

        $this->getJson("/api/{$endpoint}?per_page=abc")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonCount(1, 'data');
    }

    #[DataProvider('nestedEndpoints')]
    public function test_nested_endpoints_use_default_limit_when_absent(callable $seed, string $path): void
    {
        $uri = $seed();

        $this->getJson($uri)
            ->assertOk()
            ->assertJsonCount(20, $path);
    }

    #[DataProvider('nestedEndpoints')]
    public function test_nested_endpoints_clamp_zero_to_one(callable $seed, string $path): void
    {
        $uri = $seed();

        $this->getJson("{$uri}?limit=0")
            ->assertOk()
            ->assertJsonCount(1, $path);
    }

    #[DataProvider('nestedEndpoints')]
    public function test_nested_endpoints_clamp_negative_to_one(callable $seed, string $path): void
    {
        $uri = $seed();

        $this->getJson("{$uri}?limit=-5")
            ->assertOk()
            ->assertJsonCount(1, $path);
    }

    #[DataProvider('nestedEndpoints')]
    public function test_nested_endpoints_accept_minimum_boundary(callable $seed, string $path): void
    {
        $uri = $seed();

        $this->getJson("{$uri}?limit=1")
            ->assertOk()
            ->assertJsonCount(1, $path);
    }

    #[DataProvider('nestedEndpoints')]
    public function test_nested_endpoints_accept_maximum_boundary(callable $seed, string $path): void
    {
        $uri = $seed();

        $this->getJson("{$uri}?limit=100")
            ->assertOk()
            ->assertJsonCount(100, $path);
    }

    #[DataProvider('nestedEndpoints')]
    public function test_nested_endpoints_clamp_excessively_large_to_maximum(callable $seed, string $path): void
    {
        $uri = $seed();

        $this->getJson("{$uri}?limit=9999")
            ->assertOk()
            ->assertJsonCount(100, $path);
    }

    #[DataProvider('nestedEndpoints')]
    public function test_nested_endpoints_clamp_non_numeric_to_one(callable $seed, string $path): void
    {
        $uri = $seed();

        $this->getJson("{$uri}?limit=abc")
            ->assertOk()
            ->assertJsonCount(1, $path);
    }
}
