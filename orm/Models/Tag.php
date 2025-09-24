<?php

class Tag extends Model
{
    protected string $table = 'tags';
    protected array $fillable = ['name', 'slug'];

    /**
     * Etiket için postları getir (çoka-çok ilişki)
     */
    public function posts(): array
    {
        return $this->belongsToMany(Post::class, 'post_tags', 'tag_id', 'post_id');
    }
}
