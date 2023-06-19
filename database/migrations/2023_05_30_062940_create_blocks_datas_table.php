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
        Schema::create('blocks_datas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('version_id');
            $table->string('id_block_data')->unique();
            $table->string('position');
            $table->string('type');
            $table->string('parentNode');
            $table->boolean('expandParent')->default(false);
            $table->foreign('version_id')->references('id')->on('versions')->onDelete('cascade');
            $table->longText('data');
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
        Schema::dropIfExists('blocks_datas');
    }
};
