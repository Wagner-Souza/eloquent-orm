<?php

class Profile extends Model
{
    protected string $table = 'profiles';
    protected array $fillable = ['user_id', 'bio', 'avatar', 'website', 'location'];

    /**
     * Profile'a sahip olan kullanıcıyı getir (ters bire-bir ilişki)
     */
    public function user(): ?Model
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
