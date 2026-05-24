<?php

namespace App\Policies;

use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UnitKerjaPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isIAMAdmin();
    }

    public function view(User $user, UnitKerja $unitKerja): bool
    {
        return $user->isIAMAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isIAMAdmin();
    }

    public function update(User $user, UnitKerja $unitKerja): bool
    {
        return $user->isIAMAdmin();
    }

    public function delete(User $user, UnitKerja $unitKerja): bool
    {
        return $user->isIAMAdmin();
    }

    public function restore(User $user, ?UnitKerja $unitKerja = null): bool
    {
        return $user->isIAMAdmin();
    }

    public function forceDelete(User $user, ?UnitKerja $unitKerja = null): bool
    {
        return $user->isIAMAdmin();
    }
}
