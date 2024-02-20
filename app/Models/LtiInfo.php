<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LtiInfo extends Model
{
    use HasFactory;

    protected $table = 'lti_info';
    protected $fillable = [
        'tool_consumer_info_product_family_code',
        'context_id',
        'context_title',
        'launch_presentation_locale',
        'platform_id',
        'token',
        'ext_sakai_serverid',
        'session_id',
        'launch_presentation_return_url',
        'user_id',
        'lis_person_name_full',
        'profile_url',
        'roles',
        'expires_at',
        'session_active',
        'created_at',
        'updated_at'
    ];
}
