<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Instance extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform',
        'url_lms',
        'instance_id',
    ];

    public function courses()
    {
        return $this->hasMany('App\Models\Course');
    }
}
