<?php

namespace App\Console\Commands;

use App\Mail\NewDBSyncNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class DbSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'DbSync Executed Successfully!';

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
        Log::info("DbSync execution!");
        $this->info('Db:Sync Command is working fine!');

        // $response = Http::get("http://192.168.1.10:8000/api/backup");
        $response = Http::get("https://kinema.today/api/backup");

        Log::info($response->json());

        Mail::to("03.manu@gmail.com")->send(new NewDBSyncNotification($response));
    }
}
