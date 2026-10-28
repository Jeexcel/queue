<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->unique();
            $table->string('role')->default('staff')->index();
            $table->boolean('is_active')->default(true)->index();
        });

        Schema::create('service_desks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('service_desk_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('queue_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('status')->default('open')->index();
            $table->string('ticket_prefix', 8)->default('A');
            $table->unsignedBigInteger('next_sequence')->default(1);
            $table->unsignedBigInteger('next_queue_order')->default(1);
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('queue_control', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->foreignId('active_session_id')->nullable()->constrained('queue_sessions')->nullOnDelete();
            $table->timestamps();
        });
        DB::table('queue_control')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('queue_session_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('sequence_number');
            $table->unsignedBigInteger('queue_order');
            $table->string('ticket_number', 32);
            $table->string('request_key', 36)->nullable()->unique();
            $table->string('status')->default('waiting')->index();
            $table->foreignId('assigned_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('service_desk_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('called_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['queue_session_id', 'sequence_number']);
            $table->unique(['queue_session_id', 'queue_order']);
            $table->unique(['queue_session_id', 'ticket_number']);
            $table->index(['queue_session_id', 'status', 'queue_order', 'sequence_number']);
            $table->index(['assigned_staff_id', 'status']);
        });

        Schema::create('ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('service_desk_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_desk_id')->constrained()->restrictOnDelete();
            $table->string('event_type')->default('ticket_called');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['event_type', 'id']);
        });

        Schema::create('queue_settings', function (Blueprint $table) {
            $table->id();
            $table->string('organization_name')->default('Queue Management');
            $table->text('logo_url')->nullable();
            $table->string('ticket_prefix', 8)->default('A');
            $table->string('skip_policy')->default('requeue');
            $table->string('announcement_language', 16)->default('en-US');
            $table->string('announcement_voice')->nullable();
            $table->timestamps();
        });
        DB::table('queue_settings')->insert(['organization_name' => 'Queue Management', 'ticket_prefix' => 'A', 'skip_policy' => 'requeue', 'announcement_language' => 'en-US', 'created_at' => now(), 'updated_at' => now()]);

        Schema::create('sms_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('hubtel');
            $table->text('credentials')->nullable();
            $table->string('sender_id')->nullable();
            $table->string('endpoint')->nullable();
            $table->timestamps();
        });
        DB::table('sms_settings')->insert(['provider' => 'hubtel', 'created_at' => now(), 'updated_at' => now()]);

        Schema::create('password_reset_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phone');
            $table->string('otp_hash');
            $table->timestamp('expires_at');
            $table->timestamp('last_sent_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['phone', 'expires_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('password_reset_otps');
        Schema::dropIfExists('sms_settings');
        Schema::dropIfExists('queue_settings');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('ticket_events');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('queue_control');
        Schema::dropIfExists('queue_sessions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_desk_id');
        });
        Schema::dropIfExists('service_desks');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'role', 'is_active']);
        });
    }
};
