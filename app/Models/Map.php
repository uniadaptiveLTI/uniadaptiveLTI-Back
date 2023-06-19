<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Map extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'course_id',
        'lesson_id'
    ];

    public function course()
    {
        return $this->belongsTo('App\Models\Course');
    }

    public function versions()
    {
        return $this->hasMany('App\Models\Version');
    }
}
