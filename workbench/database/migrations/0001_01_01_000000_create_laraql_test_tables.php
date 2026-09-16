<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('body')->nullable();
            $table->string('status')->default('draft');
            $table->json('meta')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('price', 8, 2)->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->nullable();
            $table->nullableMorphs('commentable');
            $table->foreignId('user_id')->nullable();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('deep_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('article_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id');
            $table->foreignId('tag_id');
        });

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id');
            $table->text('bio')->nullable();
            $table->timestamps();
        });

        Schema::create('searchable_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamps();
        });

        Schema::create('guarded_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('admin_notes', function (Blueprint $table) {
            $table->id();
            $table->string('note');
            $table->timestamps();
        });

        Schema::create('legacy_records', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'users',
            'articles',
            'comments',
            'deep_articles',
            'tags',
            'article_tag',
            'profiles',
            'searchable_posts',
            'guarded_articles',
            'admin_notes',
            'legacy_records',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
