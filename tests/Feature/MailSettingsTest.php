<?php

use App\Filament\Pages\MailSettings;
use App\Models\MailSetting;
use App\Models\User;
use App\Services\ContactMail;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    config(['contact.smtp_allowed_hosts' => ['smtp.example.test']]);
});

it('saves encrypted credentials without returning them to the form or HTML', function (): void {
    $this->actingAs(User::factory()->administrator()->create());
    $component = Livewire::test(MailSettings::class)->fillForm([
        'notifications_enabled' => true, 'recipients' => ['owner@example.test'], 'from_address' => 'sender@example.test', 'from_name' => 'Empresa',
        'use_panel_smtp' => true, 'smtp_host' => 'smtp.example.test', 'smtp_port' => 587, 'smtp_scheme' => 'smtp', 'smtp_username' => 'private-user', 'smtp_password' => 'private-password',
    ])->set('data.id', 42)->call('save')->assertHasNoFormErrors()->assertNotified()->assertSet('data.smtp_username', null)->assertSet('data.smtp_password', null);
    $settings = MailSetting::current();
    expect($settings->id)->toBe(1);
    expect($settings->smtp_username)->toBe('private-user');
    expect($settings->smtp_password)->toBe('private-password');
    expect(DB::table('mail_settings')->value('smtp_password'))->not->toContain('private-password');
    expect(DB::table('mail_settings')->value('smtp_username'))->not->toContain('private-user');
    expect($settings->toArray())->not->toHaveKeys(['smtp_username', 'smtp_password']);
    $this->get('/admin/mail-settings')->assertOk()->assertDontSee('private-user', false)->assertDontSee('private-password', false);
    $this->assertDatabaseCount('mail_settings', 1);
});

it('preserves write only credentials on an empty save and clears them only explicitly', function (): void {
    $this->actingAs(User::factory()->administrator()->create());
    MailSetting::factory()->create(['smtp_username' => 'stored-user', 'smtp_password' => 'stored-password']);
    Livewire::test(MailSettings::class)->call('save')->assertHasNoFormErrors();
    expect(MailSetting::current()->smtp_password)->toBe('stored-password');
    Livewire::test(MailSettings::class)->fillForm(['clear_credentials' => true])->call('save')->assertHasNoFormErrors();
    expect(MailSetting::current()->smtp_username)->toBeNull();
    expect(MailSetting::current()->smtp_password)->toBeNull();
});

it('rejects invalid addresses SMTP settings and unsafe hosts', function (array $data, string $field): void {
    $this->actingAs(User::factory()->administrator()->create());
    Livewire::test(MailSettings::class)->fillForm($data)->call('save')->assertHasFormErrors([$field]);
    $this->assertDatabaseCount('mail_settings', 0);
})->with([
    [['from_address' => 'invalid'], 'from_address'], [['from_name' => "Sender\r\nBcc: evil@example.test"], 'from_name'],
    [['recipients' => ['invalid']], 'recipients.0'],
    [['use_panel_smtp' => true, 'smtp_host' => 'evil.test', 'smtp_port' => 587, 'smtp_scheme' => 'smtp'], 'smtp_host'],
    [['use_panel_smtp' => true, 'smtp_host' => 'smtp.example.test', 'smtp_port' => 25, 'smtp_scheme' => 'smtp'], 'smtp_port'],
    [['use_panel_smtp' => true, 'smtp_host' => 'smtp.example.test', 'smtp_port' => 587, 'smtp_scheme' => 'unsafe'], 'smtp_scheme'],
    [['use_panel_smtp' => true, 'smtp_host' => 'smtp.example.test', 'smtp_port' => 587, 'smtp_scheme' => 'smtp', 'smtp_password' => 'password'], 'smtp_username'],
]);

it('blocks unauthorized configuration and test sends by direct URL and component', function (string $role): void {
    if ($role !== 'guest') {
        $this->actingAs($role === 'editor' ? User::factory()->create() : User::factory()->administrator()->inactive()->create());
    }
    $response = $this->get('/admin/mail-settings');
    if ($role === 'guest') {
        $response->assertRedirect('/admin/login');
    } else {
        $response->assertForbidden();
    }
    Livewire::test(MailSettings::class)->assertForbidden();
})->with(['guest', 'editor', 'inactive']);

it('rechecks permissions before saving or sending a test', function (): void {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin);
    $component = Livewire::test(MailSettings::class);
    $testComponent = Livewire::test(MailSettings::class);
    $admin->update(['role' => UserRole::Editor]);
    $component->call('save')->assertForbidden();
    $testComponent->call('sendTest')->assertForbidden();
    $this->assertDatabaseCount('mail_settings', 0);
});

it('sends tests using saved settings validates recipients and rate limits administrator requests', function (): void {
    $this->actingAs(User::factory()->administrator()->create());
    $mail = $this->partialMock(ContactMail::class);
    $mail->shouldReceive('sendTest')->with('owner@example.test')->times(3)->andReturn(true);
    Livewire::test(MailSettings::class)->set('testRecipient', 'invalid')->call('sendTest')->assertHasErrors(['testRecipient']);
    $component = Livewire::test(MailSettings::class)->set('testRecipient', 'owner@example.test');
    for ($i = 0; $i < 3; $i++) {
        $component->call('sendTest')->assertNotified();
    }
    $component->call('sendTest')->assertHasErrors(['testRecipient']);
});

it('shows a safe failure notification when a test cannot be delivered', function (): void {
    $this->actingAs(User::factory()->administrator()->create());
    $this->partialMock(ContactMail::class)->shouldReceive('sendTest')->with('owner@example.test')->once()->andReturn(false);
    Livewire::test(MailSettings::class)->set('testRecipient', 'owner@example.test')->call('sendTest')->assertNotified();
});

it('does not log credentials or retain them in form state when storage fails', function (): void {
    $this->actingAs(User::factory()->administrator()->create());
    MailSetting::saving(fn () => throw new QueryException('sqlite', 'INSERT encrypted-credentials', [], new PDOException('secret password')));
    Log::shouldReceive('error')->once()->with('mail.settings_storage_failed', ['exception' => QueryException::class]);
    Livewire::test(MailSettings::class)->fillForm(['smtp_password' => 'new-secret-password'])->call('save')->assertNotified()
        ->assertSet('data.smtp_username', null)->assertSet('data.smtp_password', null);
    $this->assertDatabaseCount('mail_settings', 0);
});

it('handles unreadable stored credentials safely and permits explicit replacement', function (): void {
    $this->actingAs(User::factory()->administrator()->create());
    $settings = MailSetting::factory()->create(['use_panel_smtp' => true, 'smtp_host' => 'smtp.example.test', 'smtp_port' => 587, 'smtp_scheme' => 'smtp']);
    DB::table('mail_settings')->where('id', $settings->id)->update(['smtp_username' => 'corrupted-ciphertext', 'smtp_password' => 'corrupted-ciphertext']);
    Livewire::test(MailSettings::class)->call('save')->assertNotified()->assertSet('data.smtp_password', null);
    Livewire::test(MailSettings::class)->fillForm(['clear_credentials' => true])->call('save')->assertHasNoFormErrors();
    expect(MailSetting::current()->smtp_password)->toBeNull();
});

it('permits switching back to environment SMTP when a saved host is no longer allowed', function (): void {
    $this->actingAs(User::factory()->administrator()->create());
    MailSetting::factory()->create(['use_panel_smtp' => true, 'smtp_host' => 'old.example.test', 'smtp_port' => 587, 'smtp_scheme' => 'smtp']);
    Livewire::test(MailSettings::class)->fillForm(['use_panel_smtp' => false])->call('save')->assertHasNoFormErrors();
    expect(MailSetting::current()->use_panel_smtp)->toBeFalse();
});
