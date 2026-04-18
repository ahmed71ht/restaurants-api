<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantComment extends Model
{
    protected $fillable = [
        'restaurant_id',
        'user_id',
        'comment',
        'rating',
        'image',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function isRestaurantOwner()
    {
        return optional($this->restaurant)->owner_id === $this->user_id;
    }

    public function replies()
    {
        return $this->hasMany(CommentReply::class, 'comment_id');
    }
}