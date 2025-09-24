<?php

class Role extends Model
{
    protected string $table = 'roles';
    protected array $fillable = ['name', 'description'];

    /**
     * Rol için kullanıcıları getir (çoka-çok ilişki)
     */
    public function users(): array
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id');
    }
}
