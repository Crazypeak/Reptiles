<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateArticles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 64)->nullable(false)->default('')->index();
            $table->string('author', 64)->nullable(false)->default('')->comment('作者')->index();
            $table->string('url', 128)->nullable(false)->default('')->comment('爬虫源');
            $table->string('info', 512)->nullable(false)->default('')->comment('简介');
            $table->string('thumb', 128)->nullable(false)->default('')->comment('封面/缩略图');
            $table->bigInteger('category_id')->nullable(false)->default(0)->comment('分类id')->index();
            $table->string('category', 64)->nullable(false)->default('')->comment('分类');
            $table->string('last_chapter', 255)->nullable(false)->default('')->comment('最新章节');
            $table->bigInteger('last_chapter_id')->nullable(false)->default(0)->comment('最新章节ID');
            $table->tinyInteger('is_full')->nullable(false)->default(0)->comment('是否完本');
            $table->tinyInteger('is_push')->nullable(false)->default(0)->comment('是否推送');
            $table->integer('font_count')->nullable(false)->default(0)->comment('字数统计');
            $table->tinyInteger('status')->nullable(false)->default(0)->comment('状态');
            $table->timestamps();
        });
        Schema::table('articles',function (Blueprint $table){
            $table->index('created_at');
            $table->index('updated_at');
        });

        Schema::create('articles_category', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 64)->nullable(false)->default('');
            $table->integer('order')->nullable(false)->default(0)->comment('排序');
            $table->integer('status')->nullable(false)->default(0)->comment('状态')->index();
            $table->timestamps();
        });

        Schema::create('articles_chapter', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('article_id')->index()->comment('article_id');
            $table->unsignedBigInteger('chapter_id')->unique()->comment('章节ID');
            $table->string('chapter_name',255)->comment('章节名称');
//            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('articles');
    }
}
