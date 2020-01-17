<?php

namespace App\Console\Commands;

use App\Fiction\Reptile;
use Illuminate\Console\Command;

class FictionArticle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fiction:article {article_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fiction Reptile Article Detail';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $reptile = new Reptile();
        $reptile->getArticle(intval($this->argument('article_id')));
    }
}
