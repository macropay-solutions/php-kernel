<?php

use MacropaySolutions\Kernel\Database\Migrations\Migration;
use MacropaySolutions\Kernel\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        \app('db')->connection()->getSchemaBuilder()->create('gadgets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
    }

    public function down(): void
    {
        \app('db')->connection()->getSchemaBuilder()->drop('gadgets');
    }
};
