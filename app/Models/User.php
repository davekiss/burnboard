<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'github_id',
        'github_username',
        'avatar_url',
        'api_token',
        'is_verified',
        'verification_score',
        'verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
        'api_token',
    ];

    public function metrics(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Metric::class);
    }

    public function githubVerifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GitHubVerification::class);
    }

    public function latestVerification(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(GitHubVerification::class)->latestOfMany('fetched_at');
    }

    public function generateApiToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->api_token = $token;
        $this->save();

        return $token;
    }

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
            'two_factor_confirmed_at' => 'datetime',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }
}
