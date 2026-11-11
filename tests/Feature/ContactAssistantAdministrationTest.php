<?php

namespace Tests\Feature;

use App\Filament\Pages\ContactAssistantSettings;
use App\Models\ContactAssistantSetting;
use App\Models\User;
use App\Permission;
use App\Services\ContactAssistant;
use App\Services\ContactAssistantFields;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ContactAssistantAdministrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_saving_a_stale_tab_preserves_the_latest_configuration(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $settings = ContactAssistantSetting::factory()->enabled()->create();
        $first = Livewire::test(ContactAssistantSettings::class)->fillForm(['name' => 'Primera pestaña']);
        $second = Livewire::test(ContactAssistantSettings::class)->fillForm(['name' => 'Segunda pestaña']);

        $first->call('save')->assertHasNoFormErrors();
        $second->call('save')->assertHasErrors(['data'])->assertSee('La configuración cambió en otra sesión.');

        $this->assertSame('Primera pestaña', $settings->fresh()->name);
        $this->assertDatabaseCount('contact_assistant_settings', 1);
        $second->call('reloadSettings')->assertHasNoFormErrors()->fillForm(['name' => 'Ajustes revisados'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Ajustes revisados', $settings->fresh()->name);
    }

    public function test_reload_discards_the_draft_and_clears_an_obsolete_simulator(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        ContactAssistantSetting::factory()->create(['name' => 'Nombre guardado']);
        $component = Livewire::test(ContactAssistantSettings::class)->fillForm(['name' => 'Borrador'])
            ->call('preview')->assertHasNoFormErrors();
        $this->assertStringContainsString('Borrador', $component->get('previewHtml'));

        $component->call('reloadSettings')->assertSet('data.name', 'Nombre guardado')->assertSet('previewHtml', '');

        $this->assertSame('Nombre guardado', ContactAssistantSetting::current()->name);
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_saving_clears_the_previous_simulator_without_creating_a_consultation(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $component = Livewire::test(ContactAssistantSettings::class)->call('preview');
        $this->assertNotSame('', $component->get('previewHtml'));

        $component->fillForm(['name' => 'Asistente actualizado'])->call('save')->assertHasNoFormErrors()->assertSet('previewHtml', '');

        $this->assertDatabaseHas('contact_assistant_settings', ['id' => 1, 'name' => 'Asistente actualizado']);
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_read_only_simulator_ignores_forged_draft_settings_and_can_be_closed(): void
    {
        $editor = User::factory()->create();
        $editor->givePermission(Permission::ViewContactAssistantSettings);
        $this->actingAs($editor);
        ContactAssistantSetting::factory()->create(['name' => 'Configuración guardada']);
        $component = Livewire::test(ContactAssistantSettings::class)->set('data.name', 'Borrador no autorizado')->call('preview');
        $this->assertStringContainsString('Configuración guardada', $component->get('previewHtml'));
        $this->assertStringNotContainsString('Borrador no autorizado', $component->get('previewHtml'));

        $component->call('closePreview')->assertSet('previewHtml', '');

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_reload_rechecks_revoked_view_access(): void
    {
        $editor = User::factory()->create();
        $editor->givePermission(Permission::ViewContactAssistantSettings);
        $this->actingAs($editor);
        $component = Livewire::test(ContactAssistantSettings::class);
        $editor->revokePermission(Permission::ViewContactAssistantSettings);

        $component->call('reloadSettings')->assertForbidden();

        $this->assertDatabaseCount('contact_assistant_settings', 0);
    }

    public function test_hidden_limits_from_a_previous_field_type_do_not_block_a_text_question(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $fields = [...ContactAssistantFields::defaults(new ContactAssistantSetting), $this->customField()];
        $fields[5]['min_value'] = 100;
        $fields[5]['max_value'] = 1;
        $fields[5]['min_date'] = '2027-01-01';
        $fields[5]['max_date'] = '2026-01-01';

        Livewire::test(ContactAssistantSettings::class)->fillForm(['field_schema' => $fields])->call('save')->assertHasNoFormErrors();

        $question = ContactAssistantSetting::current()->field_schema[5];
        foreach (['min_value', 'max_value', 'min_date', 'max_date'] as $limit) {
            $this->assertNull($question[$limit]);
        }
    }

    public function test_name_limits_cannot_conflict_with_required_server_validation(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $fields = ContactAssistantFields::defaults(new ContactAssistantSetting);
        $fields[0]['min_length'] = 0;

        Livewire::test(ContactAssistantSettings::class)->fillForm(['field_schema' => $fields])->call('save')
            ->assertHasFormErrors()->assertSee('Revisa la configuración antes de continuar');

        $this->assertDatabaseCount('contact_assistant_settings', 0);
    }

    public function test_schema_changes_request_refresh_before_validating_new_required_answers(): void
    {
        $settings = ContactAssistantSetting::factory()->enabled()->create();
        $payload = [
            'name' => 'Ana Ejemplo', 'whatsapp' => '+447700900123', 'consent' => true,
            'submission_token' => app(ContactAssistant::class)->configuration()['submission_token'],
        ];
        $settings->update(['field_schema' => [...ContactAssistantFields::defaults($settings), $this->customField()]]);

        $response = $this->postJson('/asistente-contacto', $payload)->assertUnprocessable()->assertJsonValidationErrors('submission_token');

        $this->assertArrayNotHasKey('answers.f_aaaaaaaaaaaaaaaa', $response->json('errors'));
        $this->assertDatabaseCount('contact_messages', 0);
        $payload['submission_token'] = app(ContactAssistant::class)->configuration()['submission_token'];
        $payload['answers'] = ['f_aaaaaaaaaaaaaaaa' => 'Respuesta revisada'];
        $this->postJson('/asistente-contacto', $payload)->assertAccepted();
        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_schema_refresh_also_precedes_validation_for_direct_service_calls(): void
    {
        $settings = ContactAssistantSetting::factory()->enabled()->create();
        $token = app(ContactAssistant::class)->configuration()['submission_token'];
        $settings->update(['field_schema' => [...ContactAssistantFields::defaults($settings), $this->customField()]]);

        try {
            app(ContactAssistant::class)->store(['submission_token' => $token]);
            $this->fail('A changed form must require review.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('submission_token', $exception->errors());
            $this->assertArrayNotHasKey('name', $exception->errors());
        }
        $this->assertDatabaseCount('contact_messages', 0);
    }

    /** @return array<string, mixed> */
    private function customField(): array
    {
        return [
            'id' => 'f_aaaaaaaaaaaaaaaa', 'type' => 'text', 'label' => 'Información adicional', 'question' => '¿Qué información deseas añadir?',
            'help' => '', 'enabled' => true, 'required' => true, 'min_length' => 0, 'max_length' => 100,
            'options' => [], 'min_value' => null, 'max_value' => null, 'min_date' => null, 'max_date' => null,
        ];
    }
}
