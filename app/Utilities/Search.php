<?php
/**
 * Main search class
 *
 * @category   Search Engine
 * @package    XRPLWinAnalyzer
 * @author     Zvjezdan Grguric <zgrgric@xrplwin.com>
 */

namespace App\Utilities;

#use App\Models\Ledgerindex;
#use App\Utilities\Mapper;
#use XRPLWin\XRPL\Client;
#use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
#use Carbon\Carbon;

class Search
{
  private string $address;
  private readonly array $result;
  private readonly array $params;
  private bool $isExecuted = false;
  private array $parametersWhitelist = ['from','to','dir','cp','dt','st'];
  private array $txTypes = [
    // App\Models\DTransaction<VALUE_BELOW>::TYPE => App\Models\DTransaction<VALUE_BELOW>
    1 => 'Payment',
    2 => 'Activation',
    3 => 'Trustset',
    // ...todo
  ];

  public function __construct(string $address)
  {
    $this->address = $address;
  }

  public function buildFromArray(array $data): self
  {
   
    ///($data);
    foreach($data as $k => $v)
    {
      //
    }
    $this->params = $data;
    return $this;
  }

  public function buildFromRequest(Request $request): self
  {
    return $this->buildFromArray($request->only($this->parametersWhitelist));
  }

  /**
   * Sample: /v1/account/search/rhotcWYdfn6qxhVMbPKGDF3XCKqwXar5J4?from=2022-05-01&to=2022-05-28&st=1&cp=r3mmzMZxRQaiuLRsKDATciyegSgZod88uT
   */
  public function execute(): self
  {
    $this->result = [];

    //Mapper
    $mapper = new Mapper();
    $mapper->setAddress($this->address);


    $mapper
    ->addCondition('from',$this->param('from'))
    ->addCondition('to',$this->param('to'));
    
    if(!$mapper->dateRangeIsValid())
      abort(422, 'From and to params spans more than allowed 31 days and from has to be before to');


    $txTypes = $this->txTypes; //thiese are all types
    //Todo get types from param

    $mapper->addCondition('txTypes',$txTypes);

    //Direction (in|out)
    $param_dir = $this->param('dir');
    if($param_dir && ($param_dir == 'in' || $param_dir == 'out'))
      $mapper->addCondition('dir',$param_dir);
    unset($param_dir);

    //Counterparty
    $param_cp = $this->param('cp');
    if($param_cp && isValidXRPAddressFormat($param_cp)) {
      $mapper->addCondition('cp',$param_cp);
    }
    unset($param_cp);
      

    //Destination Tag (int)
    $param_dt = $this->param('dt');
    if($param_dt && is_numeric($param_dt))
      $mapper->addCondition('dt',$param_dt);
    unset($param_dt);

    //Source Tag (int)
    $param_st = $this->param('st');
    if($param_st && is_numeric($param_st)) 
      $mapper->addCondition('st',$param_st);
    unset($param_st);
    
    /**
     * Execute counts and get intersection of transaction hits depending on sent conditions.
     */
    $intersected = $mapper->getIntersectedLedgerindexes();


    /**
     * Caculate optimal SCAN plan
     */
    $scanplan = $this->calculateScanPlan($intersected);

    /**
     * Query the DyDB using $scanplan
     */


    dd($intersected , $scanplan);


    


    dd($intersected);

    $this->isExecuted = true;
    return $this;
  }

  /**
   * This function takes intersected array and returns optimal
   * query SCAN plan which will be executed against DyDB
   * @param array $data - final intersected array of transaction counts
   * @return array [txTypeNamepart => [ QUERY_ITERATION => [int total, int ledgerindex_first, int ledgerindex_last] ] ]
   *  ledgerindex_last = 'id' from local db table ledgerindexes
   */
  private function calculateScanPlan(array $data): array
  {
    # Eject zero edges
    # - removes items with zero (0) 'found' param left and right, until cursor reaches filled item

    $newData = [];
    foreach($data as $txTypeNamepart => $l) {
      $l_fwd = $l;
      foreach($l_fwd as $ledgerindexID => $counts) {
        if(!$counts['found']) unset($l_fwd[$ledgerindexID]);
        else break; //stop inner loop
      }

      $l_rew = \array_reverse($l_fwd,true);
      unset($l_fwd);
      foreach($l_rew as $ledgerindexID => $counts) {
        if(!$counts['found']) unset($l_rew[$ledgerindexID]);
        else break; //stop inner loop
      }
      $newData[$txTypeNamepart] = \array_reverse($l_rew,true);
      unset($l_rew);
    }
    $data = $newData;
    unset($newData);

    # Calculate batch ranges of SCAN query which will not span more than 1000 items (max 1kb items from db)
    # - DyDB QUERY/SCAN operation will paginate after 1MB retrieved data, this should avoid this pagination
    # - QUERY/SCAN is sorted by SK (sort key, eg ledger_index.transaction_index), if there is very large number of 
    #   results and zero found results, it is safe to split to next query and skip group of ledger indexes
    #   Tradeoff is second query to DyDb, and benifit is no heavy/slow SCAN operation execution.

    $breakpoint = 1000; //how db items until new query is created, scan limit may overflow over this value - default: 1000

    $tracker = [];
    foreach($data as $txTypeNamepart => $l) {
      if(empty($l)) continue;
      $i = 1;
      $tracker[$txTypeNamepart] = [$i => ['total' => 0, 'llist' => []] ]; //first iteration starting point

      foreach($l as $ledgerindexID => $counts) {
        if($tracker[$txTypeNamepart][$i]['total'] !== 0 && ($tracker[$txTypeNamepart][$i]['total']+$counts['total']) > $breakpoint) {
          //breakpoint reached
          //dd($counts);
          $i++;
          $tracker[$txTypeNamepart][$i] = ['total' => 0, 'llist' => []]; //next iteration starting point
        }
        $tracker[$txTypeNamepart][$i]['total'] += $counts['total'];
        $tracker[$txTypeNamepart][$i]['llist'][] = $ledgerindexID;
      }
      unset($i);

      //from each llist take only edge ledger indexes (list of ledgerindexes are always sorted from past to future)
      foreach($tracker[$txTypeNamepart] as $i => $v) {
        //dd($tracker);
        $tracker[$txTypeNamepart][$i]['ledgerindex_first'] = $v['llist'][0];
        $tracker[$txTypeNamepart][$i]['ledgerindex_last'] = $v['llist'][count($v['llist'])-1];
        unset($tracker[$txTypeNamepart][$i]['llist']); //remove this line if you need info about all ledgers between ledgerindex_first and ledgerindex_last
      }
    }

    //dump($tracker);
    //dd($data);
    return $tracker;
  }


  ###
  

  public function result(): array
  {
    if(!$this->isExecuted)
      throw new \Exception('Search::result() called before execute()');

    return $this->result;
  }

  private function param($name)
  {
    return isset($this->params[$name]) ? $this->params[$name]:null;
  }
}