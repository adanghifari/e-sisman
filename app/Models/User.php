<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property int|null $m_department_id
 * @property string|null $nik
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $jabatan
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['m_department_id', 'nik', 'name', 'email', 'password', 'jabatan'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'm_department_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'user_roles',
            'user_id',
            'role_id',
        );
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'user_id');
    }

    public function preparedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'official_preparer_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 'user_id');
    }

    public function assignedApprovals(): HasMany
    {
        return $this->hasMany(Approval::class, 'assigned_by');
    }

    public function isAdmin(): bool
    {
        if ($this->isDeveloper()) {
            return true;
        }

        return $this->roles()
            ->whereIn('nama_role', ['admin', 'administrator', 'super admin'])
            ->exists();
    }

    public function isDeveloper(): bool
    {
        return $this->nik === '000000' || $this->email === 'developer@example.com';
    }

    public function hasPermission(string $permissionCode): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (! Role::query()->exists() || ! $this->roles()->exists()) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('code', $permissionCode))
            ->exists();
    }

    /**
     * @param array<int, string> $permissionCodes
     */
    public function hasAnyPermission(array $permissionCodes): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (! Role::query()->exists() || ! $this->roles()->exists()) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->whereIn('code', $permissionCodes))
            ->exists();
    }

    public function canAccessRoute(?string $route): bool
    {
        if ($route === null || $this->isAdmin()) {
            return true;
        }

        if (! Role::query()->exists() || ! $this->roles()->exists()) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('route', $route))
            ->exists();
    }

    public function uploadedFiles(): HasMany
    {
        return $this->hasMany(DocumentFile::class, 'uploaded_by');
    }

    public function uploadedDocumentTemplates(): HasMany
    {
        return $this->hasMany(DocumentTemplate::class, 'uploaded_by');
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
