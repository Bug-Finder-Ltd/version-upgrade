<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void
	{
		Schema::table('basic_controls', function (Blueprint $table) {
			if (!Schema::hasColumn('basic_controls', 'script_version')) {
				$table->text('script_version')->default('1.0')->after('id');
			}
		});
	}

	public function down(): void
	{
		Schema::table('basic_controls', function (Blueprint $table) {
			if (Schema::hasColumn('basic_controls', 'script_version')) {
				$table->dropColumn('script_version');
			}
		});
	}
};
