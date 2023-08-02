<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lti_info', function (Blueprint $table) {
            $table->id();
            $table->string('tool_consumer_info_product_family_code');
            $table->string('context_id');
            $table->string('context_title');
            $table->string('launch_presentation_locale');
            $table->string('platform_id');
            $table->string('token');
            $table->string('ext_sakai_serverid')->nullable();
            $table->string('session_id')->nullable();
            $table->string('launch_presentation_return_url');
            $table->string('user_id');
            $table->string('lis_person_name_full');
            $table->longText('profile_url');
            $table->mediumText('roles');
            $table->bigInteger('expires_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lti_info');
    }
};
