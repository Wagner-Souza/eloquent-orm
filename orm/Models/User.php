<?php

class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = ['id', 'name', 'email', 'password', 'age', 'status'];

    /**
     * Kullanıcı için postları getir (bire-çok ilişki)
     */
    public function posts(): array
    {
        return $this->hasMany(Post::class, 'user_id');
    }

    /**
     * Kullanıcı için profili getir (bire-bir ilişki)
     */
    public function profile(): ?Model
    {
        return $this->hasOne(Profile::class, 'user_id');
    }

    /**
     * Kullanıcı için rolleri getir (çoka-çok ilişki)
     */
    public function roles(): array
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }

    /**
     * Sorguyu sadece aktif kullanıcıları içerecek şekilde sınırla
     */
    public static function active(): QueryBuilder
    {
        return static::where('status', 'active');
    }

    /**
     * Sorguyu belirli yaştan büyük kullanıcıları içerecek şekilde sınırla
     */
    public static function olderThan(int $age): QueryBuilder
    {
        return static::where('age', '>', $age);
    }

    /**
     * Kullanıcının tam adı attribute'unu getir
     */
    public function getFullNameAttribute(): string
    {
        return $this->getAttribute('name');
    }

    /**
     * Kullanıcının şifre attribute'unu ayarla (hashleme ile)
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->setAttribute('password', password_hash($value, PASSWORD_DEFAULT));
    }
}
