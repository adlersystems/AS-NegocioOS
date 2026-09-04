<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_settings(): void
    {
        $this->get(route('settings.index'))->assertRedirect(route('login'));
        $this->post(route('settings.update'))->assertRedirect(route('login'));
    }

    public function test_only_admin_can_access_settings(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee(__('app.settings.title'))
            ->assertSee(__('app.settings.company_name'));

        foreach ([User::ROLE_SELLER, User::ROLE_MANAGER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('settings.index'))
                ->assertForbidden();
        }
    }

    public function test_admin_can_update_company_settings_and_they_persist(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('settings.update'), [
                'company_name' => 'Mi Empresa SA',
                'nit' => '9876543-2',
                'address' => 'Zona 10, Ciudad de Guatemala',
                'phone' => '5555-1234',
                'email' => 'ventas@miempresa.com',
                'currency' => 'USD',
                'iva_percentage' => '15',
                'default_language' => 'en',
            ])
            ->assertRedirect(route('settings.index'));

        $this->assertSame('Mi Empresa SA', Setting::get('company_name'));
        $this->assertSame('9876543-2', Setting::get('nit'));
        $this->assertSame('Zona 10, Ciudad de Guatemala', Setting::get('address'));
        $this->assertSame('5555-1234', Setting::get('phone'));
        $this->assertSame('ventas@miempresa.com', Setting::get('email'));
        $this->assertSame('USD', Setting::get('currency'));
        $this->assertSame('15', Setting::get('iva_percentage'));
        $this->assertSame('en', Setting::get('default_language'));
    }

    public function test_admin_can_update_the_tagline(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('settings.update'), [
                'company_name' => 'AS-NegocioOS',
                'tagline' => 'Calidad y confianza',
                'currency' => 'GTQ',
                'iva_percentage' => '12',
                'default_language' => 'es',
            ])
            ->assertRedirect(route('settings.index'));

        $this->assertSame('Calidad y confianza', Setting::get('tagline'));
    }

    public function test_admin_can_upload_a_logo(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('settings.update'), [
                'company_name' => 'AS-NegocioOS',
                'currency' => 'GTQ',
                'iva_percentage' => '12',
                'default_language' => 'es',
                'logo' => UploadedFile::fake()->image('logo.png'),
            ])
            ->assertRedirect(route('settings.index'));

        $path = Setting::logoPath();
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringContainsString('storage/', Setting::logoUrl());
    }

    public function test_iva_must_be_within_range(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('settings.update'), [
                'company_name' => 'AS-NegocioOS',
                'currency' => 'GTQ',
                'iva_percentage' => '120',
                'default_language' => 'es',
            ])
            ->assertSessionHasErrors('iva_percentage');

        $this->assertSame('12', Setting::get('iva_percentage'));
    }

    public function test_currency_and_language_are_restricted_to_valid_values(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('settings.update'), [
                'company_name' => 'AS-NegocioOS',
                'currency' => 'EUR',
                'iva_percentage' => '12',
                'default_language' => 'fr',
            ])
            ->assertSessionHasErrors(['currency', 'default_language']);
    }

    public function test_company_name_is_required(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('settings.update'), [
                'company_name' => '',
                'currency' => 'GTQ',
                'iva_percentage' => '12',
                'default_language' => 'es',
            ])
            ->assertSessionHasErrors('company_name');
    }

    public function test_updating_settings_clears_the_cache(): void
    {
        // Seed defaults so the cache is warm.
        Setting::setMany([
            'company_name' => 'Viejo Nombre',
            'currency' => 'GTQ',
            'iva_percentage' => '8',
            'default_language' => 'es',
        ]);

        // Cache is now warm.
        $this->assertSame('Viejo Nombre', Setting::get('company_name'));

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('settings.update'), [
                'company_name' => 'Nuevo Nombre',
                'currency' => 'GTQ',
                'iva_percentage' => '8',
                'default_language' => 'es',
            ]);

        $this->assertSame('Nuevo Nombre', Setting::get('company_name'));
    }
}
