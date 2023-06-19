<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlockData extends Model
{
    use HasFactory;

    public function version()
    {
        return $this->belongsTo('App\Models\Version');
    }
}
