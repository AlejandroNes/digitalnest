<?php

return [
    'assistant_default_country' => env('CONTACT_ASSISTANT_DEFAULT_COUNTRY', 'BO'),
    'notifications_enabled' => (bool) env('CONTACT_NOTIFICATIONS_ENABLED', false),
    'recipients' => array_values(array_filter(array_map('trim', explode(',', (string) env('CONTACT_NOTIFICATION_RECIPIENTS', ''))))),
    'smtp_allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('CONTACT_SMTP_ALLOWED_HOSTS', env('MAIL_HOST', '')))))),
    'smtp_port_schemes' => array_replace([465 => 'smtps', 587 => 'smtp'],
        array_reduce(explode(',', (string) env('CONTACT_SMTP_PORT_SCHEMES', '')), static function (array $ports, string $entry): array {
            $parts = explode(':', trim($entry), 2);
            if (count($parts) === 2 && ctype_digit($parts[0]) && (int) $parts[0] > 0 && (int) $parts[0] <= 65535 && in_array($parts[1], ['smtp', 'smtps'], true) && ! in_array((int) $parts[0], [465, 587], true)) {
                $ports[(int) $parts[0]] = $parts[1];
            }

            return $ports;
        }, []),
    ),
    'smtp_timeout' => 10,
    'token_lifetime_minutes' => 120,
];
