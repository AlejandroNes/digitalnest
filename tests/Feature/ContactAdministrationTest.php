<?php

use App\ContactMessageStatus;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Models\ContactMessage;
use App\Models\User;
use App\UserRole;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('blocks unauthorized users from direct inbox and detail URLs', function (string $role): void {
    $record = ContactMessage::factory()->create();
    if ($role !== 'guest') {
        $this->actingAs($role === 'editor' ? User::factory()->create() : User::factory()->administrator()->inactive()->create());
    }
    foreach (['/admin/contact-messages', '/admin/contact-messages/'.$record->id] as $url) {
        $response = $this->get($url);
        if ($role === 'guest') {
            $response->assertRedirect('/admin/login');
        } else {
            $response->assertForbidden();
        }
    }
})->with(['guest', 'editor', 'inactive']);

it('allows administrators to search filter and paginate without editing original content', function (): void {
    $this->actingAs(User::factory()->administrator()->create());
    $old = ContactMessage::factory()->create(['name' => 'Antiguo', 'created_at' => now()->subDays(5)]);
    $current = ContactMessage::factory()->create(['name' => 'Actual']);
    $read = ContactMessage::factory()->create(['name' => 'Leído']);
    $read->markAs(ContactMessageStatus::Read);
    Livewire::test(ListContactMessages::class)->assertCanSeeTableRecords([$old, $current, $read])->searchTable('Actual')->assertCanSeeTableRecords([$current])->assertCanNotSeeTableRecords([$old, $read]);
    Livewire::test(ListContactMessages::class)->filterTable('status', 'pending')->assertCanSeeTableRecords([$old, $current])->assertCanNotSeeTableRecords([$read]);
    Livewire::test(ListContactMessages::class)->filterTable('received', ['from' => now()->subDay()->toDateString(), 'until' => now()->toDateString()])->assertCanSeeTableRecords([$current, $read])->assertCanNotSeeTableRecords([$old]);
    ContactMessage::factory()->count(12)->create();
    $component = Livewire::test(ListContactMessages::class)->set('tableRecordsPerPage', 10)->assertCountTableRecords(15);
    expect($component->instance()->getTableRecords()->count())->toBe(10);
    expect(Gate::allows('create', ContactMessage::class))->toBeFalse();
    $this->get('/admin/contact-messages/'.$current->id.'/edit')->assertNotFound();
});

it('changes status from detail and table and updates the pending badge', function (): void {
    $this->actingAs(User::factory()->administrator()->create());
    $record = ContactMessage::factory()->create();
    expect(ContactMessageResource::getNavigationBadge())->toBe('1');
    Livewire::test(ViewContactMessage::class, ['record' => $record->id])->callAction('changeStatus')->assertHasNoErrors();
    expect($record->fresh()->status)->toBe(ContactMessageStatus::Read);
    expect(ContactMessageResource::getNavigationBadge())->toBeNull();
    Livewire::test(ListContactMessages::class)->callAction(TestAction::make('changeStatus')->table($record))->assertHasNoErrors();
    expect($record->fresh()->status)->toBe(ContactMessageStatus::Pending);
    expect(ContactMessageResource::getNavigationBadge())->toBe('1');
});

it('protects all operations with policies and disallows content modification', function (): void {
    $record = ContactMessage::factory()->create();
    $admin = User::factory()->administrator()->create();
    $editor = User::factory()->create();
    foreach (['view', 'update', 'delete'] as $ability) {
        expect(Gate::forUser($admin)->allows($ability, $record))->toBeTrue();
        expect(Gate::forUser($editor)->allows($ability, $record))->toBeFalse();
    }
    expect(fn () => $record->update(['message' => 'Modificación prohibida']))->toThrow(ValidationException::class);
    expect(ContactMessage::query()->find($record->id)->message)->not->toBe('Modificación prohibida');
    $this->actingAs($editor);
    expect(ContactMessageResource::getNavigationBadge())->toBeNull();
});

it('authorizes deletion and rechecks access after role revocation', function (): void {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin);
    $record = ContactMessage::factory()->create();
    Livewire::test(ViewContactMessage::class, ['record' => $record->id])->callAction('delete');
    $this->assertModelMissing($record);
    $record = ContactMessage::factory()->create();
    $component = Livewire::test(ViewContactMessage::class, ['record' => $record->id]);
    $admin->update(['role' => UserRole::Editor]);
    $component->call('mountAction', 'changeStatus');
    $this->get('/admin/contact-messages/'.$record->id)->assertForbidden();
    expect($record->fresh()->status)->toBe(ContactMessageStatus::Pending);
});

it('escapes untrusted message content in detail views', function (): void {
    $this->actingAs(User::factory()->administrator()->create());
    $record = ContactMessage::factory()->create(['message' => '<script>alert(1)</script><img src=x onerror=alert(1)>']);
    $this->get('/admin/contact-messages/'.$record->id)->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x', false)->assertSee('&lt;script&gt;', false);
});
