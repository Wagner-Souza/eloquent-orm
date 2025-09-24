<?php

class Comment extends Model
{
    protected string $table = 'comments';
    protected array $fillable = ['post_id', 'user_id', 'content', 'status'];

    /**
     * Yoruma sahip olan postu getir
     */
    public function post(): ?Model
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    /**
     * Yoruma sahip olan kullanıcıyı getir
     */
    public function user(): ?Model
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
