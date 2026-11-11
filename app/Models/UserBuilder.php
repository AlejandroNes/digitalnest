<?php

namespace App\Models;

use App\UserRole;
use DomainException;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<User>
 */
class UserBuilder extends Builder
{
    /**
     * @return int
     */
    public function delete()
    {
        // Protect superadmin from bulk deletion
        $this->where('email', '!=', User::SUPER_ADMIN_EMAIL)
            ->where('role', '!=', UserRole::SuperAdmin->value);

        return parent::delete();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return int
     */
    public function update(array $values)
    {
        $touchesProtectedFields = (
            (array_key_exists('role', $values) && $values['role'] !== UserRole::SuperAdmin && $values['role'] !== UserRole::SuperAdmin->value)
            || (array_key_exists('is_active', $values) && ! $values['is_active'])
            || (array_key_exists('email', $values) && strcasecmp((string) $values['email'], User::SUPER_ADMIN_EMAIL) !== 0)
        );

        if ($touchesProtectedFields) {
            $queryWheres = collect($this->getQuery()->wheres);
            $specificallyTargetsSuperAdmin = $queryWheres->contains(function (array $where): bool {
                return ($where['column'] ?? null) === 'email'
                    && strcasecmp((string) ($where['value'] ?? ''), User::SUPER_ADMIN_EMAIL) === 0;
            });

            if ($specificallyTargetsSuperAdmin) {
                throw new DomainException('La cuenta superadministradora está protegida contra modificaciones masivas e indirectas.');
            }

            // Exclude superadmin from the mass update
            $this->where('email', '!=', User::SUPER_ADMIN_EMAIL)
                ->where('role', '!=', UserRole::SuperAdmin->value);
        }

        return parent::update($values);
    }
}
