<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Version extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_id',
        'map_id',
        'name',
        'default',
        'blocks_data'
    ];

    public function map()
    {
        return $this->belongsTo('App\Models\Map');
    }
}
