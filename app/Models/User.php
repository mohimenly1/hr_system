<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Permission\Traits\HasRoles; // 1. استيراد الـ Trait


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable,HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'fingerprint_id',
        'deactivated_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['is_active','full_name'];

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

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }
       /**
     * Get the teacher record associated with the user.
     */
    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function getIsActiveAttribute()
{
    return $this->deactivated_at === null;
}

// ... داخل كلاس User

public function getFullNameAttribute(): string
{
    // ابحث عن سجل الموظف أو المعلم المرتبط
    $person = $this->employee ?? $this->teacher;

    // افترض أن الاسم الأول موجود في جدول users، والباقي في جدول الموظف/المعلم
    if ($person && isset($person->middle_name) && isset($person->last_name)) {
        return trim("{$this->name} {$person->middle_name} {$person->last_name}");
    }

    // إذا لم يوجد، ارجع إلى الاسم الأساسي
    return $this->name;
}

}
