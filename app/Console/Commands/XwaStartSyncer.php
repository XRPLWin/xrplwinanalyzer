<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
#use Symfony\Component\Process\ExecutableFinder;
use App\Models\Synctracker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class XwaStartSyncer extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'xwa:startsyncer';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'This will start continous syncer threads.';

  protected int $proc_timeout = 600; //600 - must be same as in XwaContinuousSyncProc

  /**
   * Execute the console command.
   *
   * @return int
   */
  public function handle()
  {
    /*$executableFinder = new ExecutableFinder();
    $phpPath = $executableFinder->find('php');
    dd($chromedriverPath);
    exit;*/

    $numberOfProcess = 2; //16
    $ledgersPerProcess = 100; //1000

    $first_l = config('xrpl.genesis_ledger'); //starting ledger
    $tracker = Synctracker::select('last_l')->orderBy('last_l','DESC')->first();
    if($tracker) {
      $first_l = $tracker->last_l+1;
    }

    //$plan = [];

    //dd($first_l);

    $processes = [];
    for ($i = 0; $i < $numberOfProcess; $i++) {
      $start_l = $first_l+($i*$ledgersPerProcess);
      $end_l = $start_l+$ledgersPerProcess-1;
      $this->info('Starting '.$i. ': php artisan xwa:continuoussyncproc '.$start_l.' '.$end_l);
      $process = new Process(['php', base_path('artisan'),'xwa:continuoussyncproc',(string)$start_l,(string)$end_l]);
      $process->setTimeout($this->proc_timeout); //10 mins max run
      $process->disableOutput();
      $process->start();
      $processes[] = ['proc' => $process,'start_l' => $start_l, 'end_l' => $end_l];
      sleep(2);
    }
    // wait for above processes to complete
    while (count($processes)) {
      foreach ($processes as $i => $v) {
        // specific process is finished, so we remove it   
        if (!$v['proc']->isRunning()) {
          
          if(!$v['proc']->isSuccessful()){
            $this->error('Process '.$i.' failed');
            //remove failed tracker
            DB::table('synctrackers')
              ->where('first_l',$v['start_l'])
              ->where('last_l',$v['end_l'])
              ->delete();
            //$exception = new ProcessFailedException($v['proc']);
            //dump( $exception->getTraceAsString() );
            //Log::error($exception->getTraceAsString());
          } else {
            $this->info('Process '.$i.' completed'); 
          }
          unset($processes[$i]);    
        } else {
          //$this->info('Process '.$i.' running...'); 
        } 
        sleep(1);
      }
    }

    $this->info('Normalizing sync trackers...');
    //TODO

    $this->info('Shutting down manager');
    return Command::SUCCESS;



    /*
    $process = new Process(['php', base_path('artisan'),'xwa:continuoussyncproc','73807163','73807173']);
    $process2 = new Process(['php', base_path('artisan'),'xwa:continuoussyncproc','73808163','73808173']);
    //$process->disableOutput();
    $process->setTimeout(0);
    $process2->setTimeout(0);
    $process->start();
    sleep(5);
    $this->info('killed');
    return;
    //dd($process);
    foreach ($process as $type => $data) {
        if ($process::OUT === $type) {
            echo "\nRead from stdout: ".$data;
        } else { // $process::ERR === $type
            echo "\nRead from stderr: ".$data;
        }
    }

    return Command::SUCCESS;*/
  }
}