<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('popups', function (Blueprint $table): void {
            $table->id();
            $table->string('mode', 24)->default('modal');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_published')->default(false);
            $table->integer('priority')->default(0);
            $table->json('configuration');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'is_published', 'priority']);
        });
        Schema::create('popup_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('popup_id')->constrained('popups')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->foreignId('market_id')->nullable()->constrained('markets')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->timestamps();
            $table->unique(['popup_id', 'locale', 'market_id']);
        });
        Schema::create('market_popup', function (Blueprint $table): void {
            $table->foreignId('popup_id')->constrained('popups')->cascadeOnDelete();
            $table->foreignId('market_id')->constrained('markets')->cascadeOnDelete();
            $table->primary(['popup_id', 'market_id']);
        });
        Schema::create('popup_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('popup_id')->constrained('popups')->cascadeOnDelete();
            $table->string('type')->default('text');
            $table->unsignedInteger('order')->default(0);
            $table->json('styling_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
        Schema::create('popup_section_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('popup_section_id')->constrained('popup_sections')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->foreignId('market_id')->nullable()->constrained('markets')->cascadeOnDelete();
            $table->json('content_json')->nullable();
            $table->timestamps();
            $table->unique(['popup_section_id', 'locale', 'market_id'], 'popup_section_locale_market_unique');
        });
    }

    public function down(): void
    {
        foreach (['popup_section_translations', 'popup_sections', 'market_popup', 'popup_translations', 'popups'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
