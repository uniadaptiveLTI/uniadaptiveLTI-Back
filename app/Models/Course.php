<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [

        'instance_id',
        'course_id',
    ];
    public function instance()
    {
        return $this->belongsTo('App\Models\Instance');
    }

    public function maps()
    {
        return $this->hasMany('App\Models\Map');
    }
}
