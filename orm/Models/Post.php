<?php

class Post extends Model
{
    protected string $table = 'posts';
    protected array $fillable = ['title', 'content', 'user_id', 'status', 'published_at'];

    /**
     * Posta sahip olan kullanıcıyı getir (ters bire-çok ilişki)
     */
    public function user(): ?Model
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Post için yorumları getir (bire-çok ilişki)
     */
    public function comments(): array
    {
        return $this->hasMany(Comment::class, 'post_id');
    }

    /**
     * Post için etiketleri getir (çoka-çok ilişki)
     */
    public function tags(): array
    {
        return $this->belongsToMany(Tag::class, 'post_tags', 'post_id', 'tag_id');
    }

    /**
     * Sorguyu sadece yayınlanmış postları içerecek şekilde sınırla
     */
    public static function published(): QueryBuilder
    {
        return static::where('status', 'published');
    }

    /**
     * Sorguyu sadece son postları içerecek şekilde sınırla
     */
    public static function recent(): QueryBuilder
    {
        return static::orderBy('published_at', 'desc');
    }
}
