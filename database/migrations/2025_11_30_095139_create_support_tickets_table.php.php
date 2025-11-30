<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_id')->unique(); 
            
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            
            $table->string('subject');
            $table->text('description');
            $table->enum('category', [
                'airtime_issue',
                'data_issue',
                'wallet_issue',
                'payment_issue',
                'account_issue',
                'technical_issue',
                'other'
            ])->default('other');
            
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            
            $table->enum('status', [
                'pending',
                'under_review',
                'in_progress',
                'waiting_customer',
                'resolved',
                'closed',
                'rejected'
            ])->default('pending');
            
            $table->string('transaction_reference')->nullable();
            $table->string('transaction_type')->nullable(); 
            
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            
            $table->json('attachments')->nullable();
            
            $table->json('metadata')->nullable();
            
            $table->boolean('is_escalated')->default(false);
            $table->foreignId('escalated_to_admin')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('escalated_at')->nullable();
            
            $table->integer('rating')->nullable(); 
            $table->text('feedback')->nullable();
            
            $table->timestamps();
            
            $table->index(['assigned_to', 'status']);
            $table->index(['submitted_by', 'status']);
            $table->index('status');
            $table->index('created_at');
            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};