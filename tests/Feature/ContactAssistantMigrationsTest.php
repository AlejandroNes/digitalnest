<?php

use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

it('extends and reverses the contact schema without deleting existing messages', function (): void {
    $record = ContactMessage::factory()->create();
    $migration = require database_path('migrations/2026_10_13_025959_add_assistant_fields_to_contact_messages_table.php');
    try {
        $migration->down();
        expect(Schema::hasColumn('contact_messages', 'source'))->toBeFalse();
        $this->assertDatabaseHas('contact_messages', ['id' => $record->id, 'email' => $record->email, 'message' => $record->message]);
    } finally {
        $migration->up();
    }
    $this->assertDatabaseHas('contact_messages', ['id' => $record->id, 'source' => 'contact_form']);
});

it('refuses rollback instead of discarding requests without email', function (): void {
    $record = ContactMessage::factory()->create(['source' => 'contact_assistant', 'email' => null]);
    $migration = require database_path('migrations/2026_10_13_025959_add_assistant_fields_to_contact_messages_table.php');
    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'No se puede restaurar email obligatorio');
    expect(Schema::hasColumn('contact_messages', 'source'))->toBeTrue();
    $this->assertDatabaseHas('contact_messages', ['id' => $record->id, 'email' => null, 'source' => 'contact_assistant']);
});
