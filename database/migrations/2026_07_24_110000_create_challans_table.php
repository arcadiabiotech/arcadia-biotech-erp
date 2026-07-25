<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Only created when a dealer's lines within a dispatch span 2+ farmers
     * (see ChallanService::generateForDispatch()) — a single-farmer dealer
     * is still fully covered by the existing dispatches.challan_no + print
     * view, so no row is generated for that case. parent_challan_id links
     * each farmer challan back to the one dealer (master) challan it was
     * split out of.
     */
    public function up(): void
    {
        Schema::create('challans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_challan_id')->nullable()->constrained('challans')->cascadeOnDelete();
            $table->foreignId('dealer_id')->constrained('dealers')->restrictOnDelete();
            $table->foreignId('farmer_id')->nullable()->constrained('farmers')->restrictOnDelete();
            $table->string('type');
            $table->string('challan_no')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['dispatch_id', 'dealer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challans');
    }
};
