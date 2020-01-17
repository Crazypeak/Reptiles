<?php

namespace App\Console\Commands;

use App\Fiction\Reptile;
use Illuminate\Console\Command;

class FictionChapter extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fiction:chapter {chapter_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fiction Reptile Chapter';

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
        $reptile->getChapter(intval($this->argument('chapter_id')));
    }
}
