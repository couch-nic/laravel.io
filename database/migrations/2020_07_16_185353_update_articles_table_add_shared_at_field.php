<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateArticlesTableAddSharedAtField extends Migration
{
    public function up()
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dateTime('shared_at')->after('approved_at')->nullable();
        });
    }
}