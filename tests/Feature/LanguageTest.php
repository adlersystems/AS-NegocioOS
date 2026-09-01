<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_switch_language(): void
    {
        $this->post(route('language.switch', 'en'))
            ->assertRedirect()
            ->assertSessionHas('locale', 'en')
            ->assertCookie('locale', 'en');
    }

    public function test_switch_rejects_unknown_locale(): void
    {
        $this->post(route('language.switch', 'fr'))
            ->assertNotFound();
    }
}
