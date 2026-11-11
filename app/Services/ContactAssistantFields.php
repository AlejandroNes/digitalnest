<?php

namespace App\Services;

use App\Models\ContactAssistantSetting;
use App\Rules\InternationalPhone;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContactAssistantFields
{
    public const TYPES = ['text' => 'Texto corto', 'textarea' => 'Texto largo', 'email' => 'Correo', 'phone' => 'Teléfono internacional', 'number' => 'Número', 'date' => 'Fecha', 'choice' => 'Una opción (botones)', 'multiple' => 'Varias opciones'];

    public const BUILTINS = ['name' => 'text', 'service_id' => 'service', 'whatsapp' => 'phone', 'email' => 'email', 'message' => 'textarea'];

    /** @return list<array<string, mixed>> */
    public static function defaults(ContactAssistantSetting $settings): array
    {
        $rows = [
            ['name', 'Nombre', '¿Cómo te llamas?', true, true, 2, 150],
            ['service_id', 'Servicio', '¿Qué servicio te interesa?', $settings->services_enabled, false, 0, 100],
            ['whatsapp', 'WhatsApp', '¿Cuál es tu número de WhatsApp?', true, true, 0, 40],
            ['email', 'Correo', '¿Cuál es tu correo electrónico?', true, $settings->email_required, 0, 255],
            ['message', 'Mensaje', '¿Qué necesitas consultar?', true, false, 0, 1000],
        ];

        return array_map(fn (array $row): array => [
            'id' => $row[0], 'type' => self::BUILTINS[$row[0]], 'label' => $row[1], 'question' => $row[2],
            'help' => '', 'enabled' => $row[3], 'required' => $row[4], 'min_length' => $row[5], 'max_length' => $row[6],
            'options' => [], 'min_value' => null, 'max_value' => null, 'min_date' => null, 'max_date' => null,
        ], $rows);
    }

    /** @return list<array<string, mixed>> */
    public static function fields(ContactAssistantSetting $settings, bool $activeOnly = true): array
    {
        $fields = $settings->field_schema ?? self::defaults($settings);
        foreach ($fields as &$field) {
            if ($field['id'] === 'email') {
                $field['required'] = $settings->email_required;
                $field['enabled'] = $settings->email_required || $field['enabled'];
            }
            if ($field['id'] === 'service_id') {
                $field['enabled'] = $settings->services_enabled;
                $field['required'] = false;
            }
        }
        unset($field);

        return array_values($activeOnly ? array_filter($fields, fn (array $field): bool => $field['enabled']) : $fields);
    }

    /** @param array<mixed> $fields
     * @return list<array<string, mixed>>
     */
    public static function validateConfiguration(array $fields): array
    {
        $plain = 'not_regex:/[<>\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u';
        $validated = Validator::make(['field_schema' => array_values($fields)], [
            'field_schema' => ['required', 'array', 'min:5', 'max:20'],
            'field_schema.*' => ['required', 'array:id,type,label,question,help,enabled,required,options,min_length,max_length,min_value,max_value,min_date,max_date'],
            'field_schema.*.id' => ['required', 'string', 'distinct', 'regex:/^(?:name|service_id|whatsapp|email|message|f_[a-z0-9]{16})$/D'],
            'field_schema.*.type' => ['required', Rule::in([...array_keys(self::TYPES), 'service'])],
            'field_schema.*.label' => ['required', 'string', 'max:100', $plain, 'not_regex:/[\r\n]/'],
            'field_schema.*.question' => ['required', 'string', 'max:200', $plain],
            'field_schema.*.help' => ['nullable', 'string', 'max:300', $plain],
            'field_schema.*.enabled' => ['required', 'boolean'],
            'field_schema.*.required' => ['required', 'boolean'],
            'field_schema.*.min_length' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'field_schema.*.max_length' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'field_schema.*.min_value' => ['nullable', 'numeric', 'between:-100000000000,100000000000'],
            'field_schema.*.max_value' => ['nullable', 'numeric', 'between:-100000000000,100000000000'],
            'field_schema.*.min_date' => ['nullable', 'date_format:Y-m-d'],
            'field_schema.*.max_date' => ['nullable', 'date_format:Y-m-d'],
            'field_schema.*.options' => ['nullable', 'array', 'max:20'],
            'field_schema.*.options.*' => ['array:value,label'],
            'field_schema.*.options.*.value' => ['required', 'string', 'regex:/^o_[a-z0-9]{12}$/D'],
            'field_schema.*.options.*.label' => ['required', 'string', 'max:100', $plain, 'not_regex:/[\r\n]/'],
        ])->validate()['field_schema'];
        $ids = array_column($validated, 'id');
        if (array_diff(array_keys(self::BUILTINS), $ids)) {
            throw ValidationException::withMessages(['field_schema' => 'Conserva los cinco campos del sistema; puedes desactivar los opcionales.']);
        }
        foreach ($validated as $index => &$field) {
            $error = null;
            $builtin = self::BUILTINS[$field['id']] ?? null;
            if (($builtin && $field['type'] !== $builtin) || (! $builtin && $field['type'] === 'service')) {
                $error = 'El tipo de los campos del sistema no se puede cambiar.';
            }
            if (in_array($field['id'], ['name', 'whatsapp'], true) && (! $field['enabled'] || ! $field['required'])) {
                $error = 'Nombre y WhatsApp deben estar activos y ser obligatorios.';
            }
            $defaultLength = match ($field['id']) {
                'name' => 150, 'email' => 255, 'whatsapp' => 40, default => $field['type'] === 'email' ? 255 : 1000
            };
            $field = [...['help' => '', 'options' => [], 'min_length' => 0, 'max_length' => $defaultLength, 'min_value' => null, 'max_value' => null, 'min_date' => null, 'max_date' => null], ...$field];
            $field['help'] ??= '';
            $field['options'] = array_values($field['options'] ?? []);
            $field['min_length'] ??= 0;
            $field['max_length'] ??= $defaultLength;
            if (! in_array($field['type'], ['text', 'textarea', 'email'], true)) {
                $field['min_length'] = 0;
                $field['max_length'] = $defaultLength;
            }
            if ($field['type'] !== 'number') {
                $field['min_value'] = $field['max_value'] = null;
            }
            if ($field['type'] !== 'date') {
                $field['min_date'] = $field['max_date'] = null;
            }
            if ($field['id'] === 'name' && $field['min_length'] < 2) {
                $error = 'El nombre debe tener como mínimo dos caracteres.';
            }
            $limit = match ($field['id']) {
                'name' => 150, 'email' => 255, 'whatsapp' => 40, default => $field['type'] === 'email' ? 255 : 1000
            };
            if ($field['max_length'] > $limit || $field['min_length'] > $field['max_length']) {
                $error = 'Revisa los límites de longitud del campo.';
            }
            foreach ([['min_value', 'max_value'], ['min_date', 'max_date']] as [$min, $max]) {
                if ($field[$min] !== null && $field[$max] !== null && $field[$min] > $field[$max]) {
                    $error = 'El mínimo no puede superar el máximo.';
                }
            }
            if (in_array($field['type'], ['choice', 'multiple'], true)) {
                $values = array_column($field['options'], 'value');
                if (! $values || count($values) !== count(array_unique($values))) {
                    $error = 'Añade opciones con identificadores distintos.';
                }
            } else {
                $field['options'] = [];
            }
            if ($error) {
                throw ValidationException::withMessages(['field_schema.'.$index => 'Campo «'.$field['label'].'»: '.$error]);
            }
            $field['enabled'] = (bool) $field['enabled'];
            $field['required'] = (bool) $field['required'];
        }
        unset($field);

        return array_values($validated);
    }

    /** @param list<array<string, mixed>> $fields
     * @return array<string, mixed>
     */
    public static function answerRules(array $fields): array
    {
        $custom = array_values(array_filter($fields, fn (array $field): bool => ! isset(self::BUILTINS[$field['id']])));
        $rules = ['answers' => $custom ? ['nullable', 'array:'.implode(',', array_column($custom, 'id'))] : ['prohibited']];
        foreach ($custom as $field) {
            $key = 'answers.'.$field['id'];
            $rules[$key] = [$field['required'] ? 'required' : 'nullable'];
            $rules[$key] = [...$rules[$key], ...match ($field['type']) {
                'email' => ['string', 'email:rfc', 'max:'.$field['max_length']],
                'phone' => ['string', new InternationalPhone],
                'number' => ['numeric', 'regex:/^-?\d{1,12}(?:\.\d{1,4})?$/D', ...($field['min_value'] !== null ? ['min:'.$field['min_value']] : []), ...($field['max_value'] !== null ? ['max:'.$field['max_value']] : [])],
                'date' => ['date_format:Y-m-d', ...($field['min_date'] ? ['after_or_equal:'.$field['min_date']] : []), ...($field['max_date'] ? ['before_or_equal:'.$field['max_date']] : [])],
                'choice' => ['string', Rule::in(array_column($field['options'], 'value'))],
                'multiple' => ['array', 'max:20', ...($field['required'] ? ['min:1'] : [])],
                default => ['string', 'min:'.$field['min_length'], 'max:'.$field['max_length'], 'not_regex:/[<>\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            }];
            if ($field['type'] === 'multiple') {
                $rules[$key.'.*'] = ['required', 'string', 'distinct', Rule::in(array_column($field['options'], 'value'))];
            }
        }

        return $rules;
    }

    /** @param list<array<string, mixed>> $fields
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    public static function snapshot(array $fields, array $data, ?string $serviceName): array
    {
        return array_map(function (array $field) use ($data, $serviceName): array {
            $value = isset(self::BUILTINS[$field['id']]) ? ($data[$field['id']] ?? '') : ($data['answers'][$field['id']] ?? ($field['type'] === 'multiple' ? [] : ''));
            $display = $field['id'] === 'service_id' ? ($serviceName ?? 'Necesito orientación') : $value;
            if ($field['id'] === 'whatsapp') {
                $value = $display = PhoneCountries::normalize($value, $data['phone_country'] ?? null);
            }
            if ($field['type'] === 'phone' && $field['id'] !== 'whatsapp' && $value !== '') {
                $value = $display = InternationalPhone::normalize($value);
            }
            if (in_array($field['type'], ['choice', 'multiple'], true)) {
                $options = array_column($field['options'], 'label', 'value');
                $display = is_array($value) ? implode(', ', array_map(fn (string $selected): string => $options[$selected], $value)) : ($options[$value] ?? '');
            }

            return ['id' => $field['id'], 'type' => $field['type'], 'label' => $field['label'], 'question' => $field['question'], 'value' => $value, 'display_value' => (string) ($display ?? '')];
        }, $fields);
    }
}
