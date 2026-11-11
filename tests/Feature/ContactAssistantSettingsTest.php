<?php

use App\ContactMessageStatus;
use App\Filament\Pages\ContactAssistantSettings;
use App\Filament\Pages\RolesAndPermissions;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Models\ContactAssistantSetting;
use App\Models\ContactMessage;
use App\Models\RolePermission;
use App\Models\User;
use App\Permission;
use App\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    RolePermission::clearCache();
});

it('allows administrators to persist every setting and disable the module', function (): void {
    $this->actingAs(User::factory()->administrator()->create());
    $data = [
        'enabled' => true, 'name' => 'Asistente comercial', 'welcome_message' => 'Hola, ¿qué necesitas?',
        'form_intro' => 'Completa tus datos.', 'destination_whatsapp' => '0044 (7700) 900-123',
        'final_message' => 'Consulta guardada. Abre WhatsApp para enviarla.', 'button_label' => 'Hablar con nosotros',
        'email_required' => true, 'services_enabled' => false,
        'consent_text' => 'Acepto el tratamiento de datos para esta consulta.', 'privacy_path' => '/privacidad',
    ];
    Livewire::test(ContactAssistantSettings::class)->fillForm($data)->call('save')->assertHasNoFormErrors()->assertNotified();
    $this->assertDatabaseHas('contact_assistant_settings', [...$data, 'id' => 1, 'destination_whatsapp' => '+447700900123']);
    Livewire::test(ContactAssistantSettings::class)->assertFormSet([...$data, 'destination_whatsapp' => '+447700900123'])
        ->fillForm(['enabled' => false])->call('save')->assertHasNoFormErrors();
    $this->getJson('/asistente-contacto')->assertExactJson(['enabled' => false]);
    $this->assertDatabaseCount('contact_assistant_settings', 1);
});

it('saves reasonable disabled defaults without a destination', function (): void {
    $this->actingAs(User::factory()->administrator()->create());
    Livewire::test(ContactAssistantSettings::class)->call('save')->assertHasNoFormErrors();
    $settings = ContactAssistantSetting::current();
    expect($settings->enabled)->toBeFalse()->and($settings->email_required)->toBeFalse()
        ->and($settings->services_enabled)->toBeTrue()->and($settings->destination_whatsapp)->toBeNull();
});

it('validates administrative text destination and privacy routes', function (string $field, mixed $value): void {
    $this->actingAs(User::factory()->administrator()->create());
    Livewire::test(ContactAssistantSettings::class)->fillForm([$field => $value])->call('save')->assertHasFormErrors([$field]);
    $this->assertDatabaseCount('contact_assistant_settings', 0);
})->with([
    ['name', ''], ['name', '<script>bad</script>'], ['name', str_repeat('x', 101)],
    ['welcome_message', '<b>Hello</b>'], ['welcome_message', str_repeat('x', 1001)],
    ['form_intro', "bad\x00text"], ['final_message', ''], ['button_label', "Button\nTwo"], ['consent_text', ''],
    ['destination_whatsapp', '611123456'], ['destination_whatsapp', '+1234567890123456'], ['destination_whatsapp', 'https://evil.test'],
    ['privacy_path', 'javascript:alert(1)'], ['privacy_path', '//evil.test'], ['privacy_path', 'https://evil.test/privacy'],
    ['privacy_path', '/admin'], ['privacy_path', '/livewire/update'], ['privacy_path', '/privacy?next=https://evil.test'],
]);

it('requires a destination before enabling and preserves saved settings on failure', function (): void {
    $this->actingAs(User::factory()->administrator()->create());
    ContactAssistantSetting::factory()->create(['destination_whatsapp' => null]);
    Livewire::test(ContactAssistantSettings::class)->fillForm(['enabled' => true])->call('save')->assertHasFormErrors(['destination_whatsapp' => 'required']);
    expect(ContactAssistantSetting::current()->enabled)->toBeFalse();
});

it('checks the role and permission matrix through policies', function (string $role, bool $allowed): void {
    $user = User::factory()->create($role === 'super_admin'
        ? ['email' => User::SUPER_ADMIN_EMAIL] : ['role' => $role]);
    expect(Gate::forUser($user)->allows('viewAny', ContactAssistantSetting::class))->toBe($allowed)
        ->and(Gate::forUser($user)->allows('update', new ContactAssistantSetting))->toBe($allowed)
        ->and(Gate::forUser($user)->allows('viewAny', ContactMessage::class))->toBe($allowed);
})->with([
    ['super_admin', true], ['admin', true], ['administrator', true], ['editor', false], ['user', false],
]);

it('blocks guest editor normal and inactive users at the administrative URL', function (string $role): void {
    if ($role !== 'guest') {
        $this->actingAs(User::factory()->create(['role' => $role === 'inactive' ? 'admin' : $role, 'is_active' => $role !== 'inactive']));
    }
    $response = $this->get('/admin/contact-assistant-settings');
    if ($role === 'guest') {
        $response->assertRedirect('/admin/login');
    } else {
        $response->assertForbidden();
    }
})->with(['guest', 'editor', 'user', 'inactive']);

it('lets explicitly permitted editors view settings without saving or reading requests', function (): void {
    $editor = User::factory()->create();
    $editor->givePermission(Permission::ViewContactAssistantSettings);
    $this->actingAs($editor);
    $this->get('/admin/contact-assistant-settings')->assertOk();
    Livewire::test(ContactAssistantSettings::class)->call('save')->assertForbidden();
    $this->get('/admin/contact-messages')->assertForbidden();
    $this->assertDatabaseCount('contact_assistant_settings', 0);
});

it('allows editor configuration and request access only through separate explicit permissions', function (): void {
    $editor = User::factory()->create();
    foreach ([Permission::ViewContactAssistantSettings, Permission::UpdateContactAssistantSettings, Permission::ViewContactMessage] as $permission) {
        $editor->givePermission($permission);
    }
    $this->actingAs($editor);
    Livewire::test(ContactAssistantSettings::class)->fillForm(['name' => 'Consulta autorizada'])->call('save')->assertHasNoFormErrors();
    $this->assertDatabaseHas('contact_assistant_settings', ['name' => 'Consulta autorizada']);
    $this->get('/admin/contact-messages')->assertOk();
});

it('uses configured admin permissions and exposes the assistant permission group', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $super = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
    $this->actingAs($super);
    Livewire::test(RolesAndPermissions::class)->assertSee('Asistente de Contacto')
        ->set('adminPermissions', [Permission::ViewContactMessage->value])->call('saveAdminPermissions');
    expect(Gate::forUser($admin->fresh())->allows('viewAny', ContactAssistantSetting::class))->toBeFalse();
    $this->actingAs($admin->fresh())->get('/admin/contact-assistant-settings')->assertForbidden();
    expect(Gate::forUser($super)->allows('update', new ContactAssistantSetting))->toBeTrue();
});

it('reauthorizes save after permission revocation or account deactivation', function (string $change): void {
    $editor = User::factory()->create();
    $editor->givePermission(Permission::ViewContactAssistantSettings);
    $editor->givePermission(Permission::UpdateContactAssistantSettings);
    $this->actingAs($editor);
    $component = Livewire::test(ContactAssistantSettings::class)->fillForm(['name' => 'No guardar']);
    if ($change === 'permission') {
        $editor->revokePermission(Permission::UpdateContactAssistantSettings);
    } else {
        $editor->update(['is_active' => false]);
    }
    $component->call('save')->assertForbidden();
    $this->assertDatabaseCount('contact_assistant_settings', 0);
})->with(['permission', 'inactive']);

it('filters assistant requests in the existing inbox and displays their details safely', function (): void {
    $this->actingAs(User::factory()->administrator()->create());
    $traditional = ContactMessage::factory()->create();
    $assistant = ContactMessage::factory()->create([
        'source' => 'contact_assistant', 'service_name' => '<script>alert(1)</script>',
        'phone' => '+34611123456', 'email' => null, 'whatsapp_status' => 'link_prepared',
        'consented_at' => now(), 'consent_text' => 'Acepto mi consulta.',
    ]);
    Livewire::test(ListContactMessages::class)->filterTable('source', 'contact_assistant')
        ->assertCanSeeTableRecords([$assistant])->assertCanNotSeeTableRecords([$traditional]);
    Livewire::test(ViewContactMessage::class, ['record' => $assistant->id])
        ->assertSee('Asistente de Contacto')->assertSee('Enlace preparado; apertura y recepción sin confirmar')
        ->assertSee('Sin correo')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    $assistant->markAs(ContactMessageStatus::Read);
    expect($assistant->fresh()->status)->toBe(ContactMessageStatus::Read);
});

it('keeps assistant provenance and consent immutable while allowing follow up status', function (string $field): void {
    $record = ContactMessage::factory()->create(['source' => 'contact_assistant', 'consent_text' => 'Original']);
    expect(fn () => $record->update([$field => 'modified']))->toThrow(ValidationException::class);
    $this->assertDatabaseHas('contact_messages', ['id' => $record->id, 'source' => 'contact_assistant', 'consent_text' => 'Original']);
})->with(['source', 'consent_text', 'service_name', 'whatsapp_status']);
