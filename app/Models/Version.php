<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Version extends Model
{
    use HasFactory;

    protected $fillable = [
        'map_id',
        'name',
        'default',
        'blocks_data'
    ];

    public function map()
    {
        return $this->belongsTo('App\Models\Map');
    }

    public function blocks()
    {
        return $this->hasMany('App\Models\Block');
    }
}