<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProcessedFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('processed_files', function (Blueprint $table) {
            $table->id();
            $table->string('file_id')->unique(); // Stores the unique file ID from OneDrive
            $table->string('file_name'); // Stores the name of the file
            $table->timestamp('processed_at')->nullable(); // Timestamp of when the file was processed
            $table->timestamps(); // Created at and updated at timestamps
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('processed_files');
    }
}
