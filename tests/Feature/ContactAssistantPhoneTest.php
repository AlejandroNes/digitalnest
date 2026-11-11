<?php

namespace Tests\Feature;

use App\Models\ContactAssistantSetting;
use App\Services\ContactAssistant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContactAssistantPhoneTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('validNumbers')]
    public function test_normalizes_selected_country_numbers_before_saving(string $country, string $number, string $expected): void
    {
        ContactAssistantSetting::factory()->enabled()->create();
        $payload = $this->payload(['phone_country' => $country, 'whatsapp' => $number]);

        $response = $this->postJson('/asistente-contacto', $payload)->assertAccepted()->assertJsonPath('whatsapp_status', 'link_prepared');

        $this->assertDatabaseHas('contact_messages', ['phone' => $expected, 'source' => 'contact_assistant']);
        parse_str(parse_url($response->json('whatsapp_url'), PHP_URL_QUERY), $query);
        $this->assertStringContainsString('*WhatsApp:* '.$expected, $query['text']);
        $this->postJson('/asistente-contacto', $payload)->assertAccepted()->assertJsonPath('whatsapp_url', $response->json('whatsapp_url'));
        $this->assertDatabaseCount('contact_messages', 1);
    }

    public static function validNumbers(): array
    {
        return [
            'Bolivia national' => ['BO', '7777 7777', '+59177777777'],
            'Spain international paste' => ['ES', '+34 (611) 123-456', '+34611123456'],
            'UK domestic prefix' => ['GB', '07700 900123', '+447700900123'],
            'UK international paste' => ['GB', '0044 7700 900123', '+447700900123'],
            'Italy retains significant zero' => ['IT', '02 1234 5678', '+390212345678'],
            'Canada shares calling code' => ['CA', '416 555 0123', '+14165550123'],
        ];
    }

    #[DataProvider('invalidNumbers')]
    public function test_rejects_invalid_country_numbers_without_saving(mixed $country, mixed $number, string $field): void
    {
        ContactAssistantSetting::factory()->enabled()->create();

        $this->postJson('/asistente-contacto', $this->payload(['phone_country' => $country, 'whatsapp' => $number]))
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public static function invalidNumbers(): array
    {
        return [
            'unknown country' => ['ZZ', '77777777', 'phone_country'],
            'array country' => [['BO'], '77777777', 'phone_country'],
            'lowercase country' => ['bo', '77777777', 'phone_country'],
            'country prefix mismatch' => ['BO', '+34611123456', 'whatsapp'],
            'too short Bolivia' => ['BO', '7777777', 'whatsapp'],
            'too long Bolivia' => ['BO', '777777777', 'whatsapp'],
            'duplicate country prefix' => ['BO', '59177777777', 'whatsapp'],
            'non numeric' => ['BO', '7777abc77', 'whatsapp'],
            'array number' => ['BO', ['77777777'], 'whatsapp'],
            'embedded markup' => ['BO', '<img src=x>', 'whatsapp'],
            'national without country' => [null, '77777777', 'whatsapp'],
        ];
    }

    public function test_exposes_local_country_choices_and_a_configurable_fallback(): void
    {
        ContactAssistantSetting::factory()->enabled()->create();
        config()->set('contact.assistant_default_country', 'CA');

        $response = $this->getJson('/asistente-contacto')->assertOk()->assertJsonPath('default_phone_country', 'CA');

        $countries = collect($response->json('phone_countries'))->keyBy('code');
        $this->assertSame('591', $countries['BO']['dial_code']);
        $this->assertSame('Bolivia', $countries['BO']['name']);
        $this->assertSame('🇧🇴', $countries['BO']['flag']);
        $this->assertCount(245, $countries);
        config()->set('contact.assistant_default_country', 'ZZ');
        $this->getJson('/asistente-contacto')->assertOk()->assertJsonPath('default_phone_country', 'BO');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_direct_service_calls_apply_the_same_country_validation(): void
    {
        ContactAssistantSetting::factory()->enabled()->create();
        try {
            app(ContactAssistant::class)->store($this->payload(['phone_country' => 'BO', 'whatsapp' => '+34611123456']));
            $this->fail('A direct call must not bypass country validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('whatsapp', $exception->errors());
        }
        $this->assertDatabaseCount('contact_messages', 0);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Ana Ejemplo', 'whatsapp' => '+34611123456', 'consent' => true,
            'submission_token' => app(ContactAssistant::class)->configuration()['submission_token'],
        ], $overrides);
    }
}
