<?php

namespace Tests\Feature;

use App\ContactMessageStatus;
use App\Filament\Pages\ContactAssistantSettings;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Models\ContactAssistantSetting;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\ContactAssistant;
use App\Services\ContactAssistantFields;
use App\Services\MediaUploader;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ContactAssistantFieldsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_can_save_custom_questions_and_reorder_system_fields(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $settings = ContactAssistantSetting::factory()->enabled()->create();
        $fields = ContactAssistantFields::defaults($settings);
        array_unshift($fields, $this->field('choice'));
        [$fields[1], $fields[3]] = [$fields[3], $fields[1]];

        Livewire::test(ContactAssistantSettings::class)->fillForm(['field_schema' => $fields])->call('save')->assertHasNoFormErrors();

        $this->assertSame(array_column($fields, 'id'), array_column($settings->fresh()->field_schema, 'id'));
        $response = $this->getJson('/asistente-contacto')->assertOk();
        $this->assertSame('f_aaaaaaaaaaaaaaaa', $response->json('field_schema.0.id'));
        $this->get('/contacto')->assertOk()->assertSee('Elige una opción')->assertSee('Opción segura');
    }

    #[DataProvider('validAnswers')]
    public function test_saves_every_supported_type_with_a_historical_snapshot(string $type, mixed $value, string $display): void
    {
        $settings = ContactAssistantSetting::factory()->enabled()->create();
        $settings->update(['field_schema' => [...ContactAssistantFields::defaults($settings), $this->field($type)]]);

        $response = $this->postJson('/asistente-contacto', $this->payload(['answers' => ['f_aaaaaaaaaaaaaaaa' => $value]]))->assertAccepted();

        $record = ContactMessage::query()->sole();
        $answer = collect($record->assistant_answers)->firstWhere('id', 'f_aaaaaaaaaaaaaaaa');
        $this->assertSame('Pregunta adicional', $answer['label']);
        $this->assertSame($type, $answer['type']);
        $this->assertSame($display, $answer['display_value']);
        parse_str(parse_url($response->json('whatsapp_url'), PHP_URL_QUERY), $query);
        $this->assertStringContainsString('*Pregunta adicional:* '.$display, $query['text']);
        $this->assertDatabaseCount('contact_messages', 1);
    }

    public static function validAnswers(): array
    {
        return [
            ['text', 'Una respuesta', 'Una respuesta'], ['textarea', "Una respuesta\ncon dos líneas", "Una respuesta\ncon dos líneas"],
            ['email', 'extra@example.test', 'extra@example.test'], ['phone', '0044 7700 900123', '+447700900123'],
            ['number', '25.5', '25.5'], ['date', '2026-10-15', '2026-10-15'],
            ['choice', 'o_aaaaaaaaaaaa', 'Opción segura'], ['multiple', ['o_aaaaaaaaaaaa', 'o_bbbbbbbbbbbb'], 'Opción segura, Otra opción'],
        ];
    }

    #[DataProvider('invalidAnswers')]
    public function test_rejects_invalid_answers_without_creating_a_consultation(string $type, mixed $value): void
    {
        $settings = ContactAssistantSetting::factory()->enabled()->create();
        $settings->update(['field_schema' => [...ContactAssistantFields::defaults($settings), $this->field($type)]]);

        $this->postJson('/asistente-contacto', $this->payload(['answers' => ['f_aaaaaaaaaaaaaaaa' => $value]]))
            ->assertUnprocessable()->assertJsonValidationErrors($type === 'multiple' && is_array($value) && $value ? 'answers.f_aaaaaaaaaaaaaaaa.0' : 'answers.f_aaaaaaaaaaaaaaaa');

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public static function invalidAnswers(): array
    {
        return [
            ['text', ''], ['text', '<script>x</script>'], ['text', str_repeat('x', 101)], ['text', ['unexpected']],
            ['email', 'invalid'], ['phone', '77777777'], ['number', '1e10'], ['number', '101'], ['number', '-1'],
            ['date', '2026-02-31'], ['date', '2025-10-15'], ['date', '2028-01-01'],
            ['choice', 'forged'], ['multiple', ['forged']], ['multiple', []], ['multiple', 'o_aaaaaaaaaaaa'],
        ];
    }

    public function test_rejects_unknown_and_disabled_custom_fields(): void
    {
        $settings = ContactAssistantSetting::factory()->enabled()->create();
        $field = $this->field('text');
        $field['enabled'] = false;
        $settings->update(['field_schema' => [...ContactAssistantFields::defaults($settings), $field]]);
        $this->postJson('/asistente-contacto', $this->payload(['answers' => ['f_aaaaaaaaaaaaaaaa' => 'Hidden']]))->assertUnprocessable();
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_rejects_unknown_answer_keys_even_when_custom_fields_exist(): void
    {
        $settings = ContactAssistantSetting::factory()->enabled()->create();
        $settings->update(['field_schema' => [...ContactAssistantFields::defaults($settings), $this->field('text')]]);
        $this->postJson('/asistente-contacto', $this->payload(['answers' => ['f_aaaaaaaaaaaaaaaa' => 'Valid', 'admin' => true]]))->assertUnprocessable()->assertJsonValidationErrors('answers');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_schema_changes_require_review_but_do_not_rewrite_old_requests(): void
    {
        $settings = ContactAssistantSetting::factory()->enabled()->create();
        $fields = [...ContactAssistantFields::defaults($settings), $this->field('choice')];
        $settings->update(['field_schema' => $fields]);
        $payload = $this->payload(['answers' => ['f_aaaaaaaaaaaaaaaa' => 'o_aaaaaaaaaaaa']]);
        $this->postJson('/asistente-contacto', $payload)->assertAccepted();
        $record = ContactMessage::query()->sole();
        $original = $record->assistant_answers;
        $fields[5]['options'][0]['label'] = 'Etiqueta cambiada';
        $settings->update(['field_schema' => $fields]);
        $refreshed = $this->getJson('/asistente-contacto?fields=1')->assertOk();
        $this->assertStringContainsString('Etiqueta cambiada', $refreshed->json('fields_html'));
        $this->assertSame('Etiqueta cambiada', $refreshed->json('field_schema.5.options.0.label'));
        $this->assertNotSame($payload['submission_token'], $refreshed->json('submission_token'));
        $this->postJson('/asistente-contacto', $payload)->assertUnprocessable()->assertJsonValidationErrors('submission_token');
        $this->assertSame($original, $record->fresh()->assistant_answers);
        $this->assertStringContainsString('Opción segura', urldecode(app(ContactAssistant::class)->whatsappUrl($record)));
        $record->markAs(ContactMessageStatus::Read);
        $this->assertSame($original, $record->fresh()->assistant_answers);
        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_snapshot_is_immutable_and_displayed_as_escaped_text_in_the_inbox(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $record = ContactMessage::factory()->create(['assistant_answers' => [['id' => 'f_aaaaaaaaaaaaaaaa', 'question' => '<script>bad</script>', 'display_value' => '<img src=x onerror=alert(1)>']]]);
        Livewire::test(ViewContactMessage::class, ['record' => $record->id])->assertSee('&lt;script&gt;', false)->assertDontSee('<script>bad</script>', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
        $this->expectException(ValidationException::class);
        $record->update(['assistant_answers' => []]);
    }

    #[DataProvider('invalidConfigurations')]
    public function test_admin_cannot_save_an_unsafe_configuration(string $change): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $settings = ContactAssistantSetting::factory()->enabled()->create();
        $fields = ContactAssistantFields::defaults($settings);
        if ($change === 'missing') {
            array_shift($fields);
        }
        if ($change === 'disabled') {
            $fields[0]['enabled'] = false;
        }
        if ($change === 'optional') {
            $fields[2]['required'] = false;
        }
        if ($change === 'type') {
            $fields[0]['type'] = 'number';
        }
        if ($change === 'duplicate') {
            $fields[1]['id'] = $fields[0]['id'];
        }
        if ($change === 'html') {
            $fields[0]['question'] = '<img src=x>';
        }
        if ($change === 'limits') {
            $fields[0]['min_length'] = 151;
        }
        $component = Livewire::test(ContactAssistantSettings::class)->fillForm(['field_schema' => $fields])->call('save');
        $component->assertHasFormErrors();
        $this->assertNull($settings->fresh()->field_schema);
    }

    public static function invalidConfigurations(): array
    {
        return array_map(fn (string $change): array => [$change], ['missing', 'disabled', 'optional', 'type', 'duplicate', 'html', 'limits']);
    }

    public function test_simulator_uses_the_public_renderer_without_saving_settings_or_messages(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $fields = [...ContactAssistantFields::defaults(new ContactAssistantSetting), $this->field('multiple')];
        $component = Livewire::test(ContactAssistantSettings::class)->fillForm(['name' => 'Vista previa', 'field_schema' => $fields])->call('preview')->assertHasNoFormErrors();
        $html = $component->get('previewHtml');
        $this->assertStringContainsString('data-preview="true"', $html);
        $this->assertStringContainsString('Vista previa', $html);
        $this->assertStringContainsString('Esta simulación no guarda consultas ni abre WhatsApp', $html);
        $this->assertStringContainsString('name="f_aaaaaaaaaaaaaaaa"', $html);
        $this->assertDatabaseCount('contact_messages', 0);
        $this->assertDatabaseCount('contact_assistant_settings', 0);
    }

    public function test_rendered_client_handles_all_custom_types_and_the_simulator_never_posts(): void
    {
        $settings = ContactAssistantSetting::factory()->enabled()->create();
        $fields = ContactAssistantFields::defaults($settings);
        foreach (array_keys(ContactAssistantFields::TYPES) as $index => $type) {
            $field = $this->field($type);
            $field['id'] = 'f_'.str_repeat(chr(97 + $index), 16);
            $fields[] = $field;
        }
        $settings->update(['field_schema' => $fields]);
        $refreshToken = app(ContactAssistant::class)->configuration()['submission_token'];
        $html = $this->get('/contacto')->assertOk()->getContent();
        $process = new Process(['node', base_path('tests/Js/contact-assistant-flow.mjs')]);
        $process->setInput($html.'<input data-refresh-token value="'.e($refreshToken).'">');
        $process->setTimeout(20);
        $process->mustRun();
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(8, $result['payload']['answers']);
        $this->postJson('/asistente-contacto', $result['payload'])->assertAccepted();
        $this->assertDatabaseCount('contact_messages', 1);

        $this->actingAs(User::factory()->administrator()->create());
        if ($directory = getenv('CONTACT_ASSISTANT_PREVIEW_DIR')) {
            Vite::useHotFile($directory.'/absent-hot-file');
            Vite::createAssetPathsUsing(fn (string $path): string => '/'.$path);
        }
        $component = Livewire::test(ContactAssistantSettings::class)->call('preview')->assertHasNoFormErrors();
        if ($directory) {
            file_put_contents($directory.'/index.html', $component->get('previewHtml'));
        }
        $process = new Process(['node', base_path('tests/Js/contact-assistant-flow.mjs')]);
        $process->setInput($component->get('previewHtml').'<input data-refresh-token value="">');
        $process->setTimeout(20);
        $process->mustRun();
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($result['simulated']);
        $this->assertSame(0, $result['calls']);
        $this->assertDatabaseCount('contact_messages', 1);
        $component->call('preview')->assertHasNoFormErrors();
        $this->assertSame(2, $component->get('previewRevision'));
    }

    public function test_simulator_rechecks_authorization_after_revocation(): void
    {
        $user = User::factory()->administrator()->create();
        $this->actingAs($user);
        $component = Livewire::test(ContactAssistantSettings::class);
        $user->update(['is_active' => false]);
        $component->call('preview')->assertForbidden();
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_selected_photo_is_cropped_to_200_pixels_and_cannot_be_deleted_while_in_use(): void
    {
        Storage::fake('public');
        $user = User::factory()->administrator()->create();
        $this->actingAs($user);
        $asset = app(MediaUploader::class)->upload($user, UploadedFile::fake()->image('portrait.png', 500, 300), 'Retrato', 'Retrato del asistente')['asset'];
        $media = $asset->getFirstMedia('images');
        Livewire::test(ContactAssistantSettings::class)->fillForm(['avatar_media_id' => $media->id])->call('save')->assertHasNoFormErrors();
        $media->refresh();
        $size = getimagesize(Storage::disk('public')->path($media->getPathRelativeToRoot('contact-avatar')));
        $this->assertSame([200, 200], [$size[0], $size[1]]);
        $this->assertSame($media->id, ContactAssistantSetting::current()->avatar_media_id);
        $this->assertTrue($asset->isReferenced());
        $settings = ContactAssistantSetting::current();
        $settings->update(['enabled' => true, 'destination_whatsapp' => '+447700900123']);
        $this->get('/contacto')->assertOk()->assertSee('width="200" height="200"', false);
        $this->expectException(ValidationException::class);
        $asset->delete();
    }

    public function test_missing_photo_uses_the_default_avatar(): void
    {
        Storage::fake('public');
        $settings = ContactAssistantSetting::factory()->enabled()->create();
        $this->assertNull(app(ContactAssistant::class)->configuration()['avatar_url']);
        $this->assertNull($settings->avatar_media_id);
    }

    public function test_new_migrations_reverse_without_deleting_existing_contact_data(): void
    {
        $record = ContactMessage::factory()->create();
        $settings = ContactAssistantSetting::factory()->enabled()->create();
        $configuration = require database_path('migrations/2026_10_13_182824_add_conversation_configuration_to_contact_assistant_settings_table.php');
        $answers = require database_path('migrations/2026_10_13_182825_add_assistant_answers_to_contact_messages_table.php');
        try {
            $answers->down();
            $configuration->down();
            $this->get('/contacto')->assertOk()->assertSee('data-contact-assistant', false);
            $this->postJson('/asistente-contacto', $this->payload())->assertAccepted();
            $this->assertDatabaseHas('contact_messages', ['id' => $record->id, 'email' => $record->email]);
            $this->actingAs(User::factory()->administrator()->create());
            Livewire::test(ContactAssistantSettings::class)->fillForm(['name' => 'Configuración anterior'])->call('save')->assertHasNoFormErrors();
            $this->assertSame('Configuración anterior', $settings->fresh()->name);
        } finally {
            $configuration->up();
            $answers->up();
        }
    }

    public function test_rollback_refuses_to_discard_collected_answers(): void
    {
        ContactMessage::factory()->create(['assistant_answers' => [['display_value' => 'Conservar']]]);
        $migration = require database_path('migrations/2026_10_13_182825_add_assistant_answers_to_contact_messages_table.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_configuration_rollback_refuses_to_discard_saved_questions(): void
    {
        $settings = ContactAssistantSetting::factory()->enabled()->create();
        $settings->update(['field_schema' => ContactAssistantFields::defaults($settings)]);
        $migration = require database_path('migrations/2026_10_13_182824_add_conversation_configuration_to_contact_assistant_settings_table.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    /** @return array<string, mixed> */
    private function field(string $type): array
    {
        return [
            'id' => 'f_aaaaaaaaaaaaaaaa', 'type' => $type, 'label' => 'Pregunta adicional', 'question' => 'Elige una opción', 'help' => 'Una ayuda breve',
            'enabled' => true, 'required' => true, 'min_length' => 0, 'max_length' => 100, 'min_value' => 0, 'max_value' => 100,
            'min_date' => '2026-01-01', 'max_date' => '2027-12-31',
            'options' => [['value' => 'o_aaaaaaaaaaaa', 'label' => 'Opción segura'], ['value' => 'o_bbbbbbbbbbbb', 'label' => 'Otra opción']],
        ];
    }

    /** @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function payload(array $extra = []): array
    {
        return [...[
            'name' => 'Ana Ejemplo', 'whatsapp' => '+447700900123', 'consent' => true,
            'submission_token' => app(ContactAssistant::class)->configuration()['submission_token'],
        ], ...$extra];
    }
}
