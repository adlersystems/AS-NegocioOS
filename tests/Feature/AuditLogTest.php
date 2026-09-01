<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_creation_is_logged(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $client = Client::factory()->create(['name' => 'Cliente Auditado']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'created',
            'auditable_type' => Client::class,
            'auditable_id' => $client->id,
        ]);

        $log = AuditLog::query()
            ->where('auditable_id', $client->id)
            ->where('action', 'created')
            ->first();

        $this->assertSame('Cliente Auditado', $log->new_values['name']);
    }

    public function test_client_update_is_logged_with_old_and_new_values(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $client = Client::factory()->create(['name' => 'Antes']);
        $client->update(['name' => 'Después']);

        $log = AuditLog::query()
            ->where('auditable_id', $client->id)
            ->where('action', 'updated')
            ->latest()
            ->first();

        $this->assertSame('Antes', $log->old_values['name']);
        $this->assertSame('Después', $log->new_values['name']);
    }

    public function test_client_deletion_is_logged(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $client = Client::factory()->create(['name' => 'Eliminar']);
        $client->delete();

        $log = AuditLog::query()
            ->where('auditable_id', $client->id)
            ->where('action', 'deleted')
            ->first();

        $this->assertSame('Eliminar', $log->old_values['name']);
        $this->assertNotNull($log->user_id);
    }

    public function test_seeded_or_unauthenticated_writes_are_not_logged(): void
    {
        Client::factory()->create();

        $this->assertDatabaseCount('audit_logs', 0);
    }
}
