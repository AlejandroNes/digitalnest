<?php

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

it('verifies existing MySQL schema indexes and deletion constraints without changing records', function (): void {
    $database = getenv('MYSQL_AUDIT_DATABASE');
    config(['database.connections.mysql_audit' => [...config('database.connections.mysql'), 'database' => $database, 'url' => null]]);
    $connection = DB::connection('mysql_audit');
    expect($connection->getDriverName())->toBe('mysql');
    $indexes = collect($connection->select('SELECT TABLE_NAME, INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS indexed_columns FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? GROUP BY TABLE_NAME, INDEX_NAME', [$database]));
    foreach (['posts' => 'status,published_at,id', 'services' => 'is_active,sort_order,id', 'faqs' => 'is_active,sort_order,id', 'contact_messages' => 'status,created_at', 'redirects' => 'source'] as $table => $columns) {
        expect($indexes->contains(fn (object $index): bool => $index->TABLE_NAME === $table && $index->indexed_columns === $columns))->toBeTrue();
    }
    $constraints = collect($connection->select('SELECT TABLE_NAME, REFERENCED_TABLE_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ?', [$database]));
    foreach (['posts', 'services', 'testimonials', 'search_settings'] as $table) {
        expect($constraints->contains(fn (object $constraint): bool => $constraint->TABLE_NAME === $table && $constraint->REFERENCED_TABLE_NAME === 'media' && in_array($constraint->DELETE_RULE, ['RESTRICT', 'NO ACTION'], true)))->toBeTrue();
    }
    $credentials = collect($connection->select('SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME IN (?, ?)', [$database, 'mail_settings', 'smtp_username', 'smtp_password']));
    expect($credentials)->toHaveCount(2);
    expect($credentials->pluck('DATA_TYPE')->unique()->all())->toBe(['text']);
})->skip(fn (): bool => ! getenv('MYSQL_AUDIT_DATABASE'), 'MYSQL_AUDIT_DATABASE no configurada; auditoría MySQL opcional de solo lectura.');
