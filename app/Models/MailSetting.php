<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['notifications_enabled', 'recipients', 'from_address', 'from_name', 'use_panel_smtp', 'smtp_host', 'smtp_port', 'smtp_scheme', 'smtp_username', 'smtp_password'])]
#[Hidden(['smtp_username', 'smtp_password'])]
class MailSetting extends Model
{
    use HasFactory;

    protected $attributes = ['use_panel_smtp' => false];

    protected function casts(): array
    {
        return ['notifications_enabled' => 'boolean', 'recipients' => 'array', 'use_panel_smtp' => 'boolean', 'smtp_port' => 'integer', 'smtp_username' => 'encrypted', 'smtp_password' => 'encrypted'];
    }

    public static function current(): self
    {
        return static::query()->find(1) ?? new static;
    }

    public function notificationsEnabled(): bool
    {
        return $this->notifications_enabled ?? config('contact.notifications_enabled', false);
    }

    /** @return list<string> */
    public function notificationRecipients(): array
    {
        return $this->recipients ?? config('contact.recipients', []);
    }
}
