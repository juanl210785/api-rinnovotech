<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('bcv_rate', 12, 4)->nullable()->after('total');
            $table->decimal('total_bs', 14, 2)->nullable()->after('bcv_rate');
            $table->timestamp('expires_at')->nullable()->after('payment_method');
        });

        Schema::create('bank_payments', function (Blueprint $table) {
            $table->id();
            $table->string('bank')->default('BDV');
            $table->string('sender_name')->nullable();
            $table->string('reference')->index(); // Indexado para búsqueda rápida en conciliación
            $table->decimal('amount', 14, 2);
            $table->text('raw_message');
            $table->enum('status', ['unclaimed', 'claimed'])->default('unclaimed')->index();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('payment_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_payments');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['bcv_rate', 'total_bs', 'expires_at']);
        });
    }
};
