<?php

namespace App\Repository\Sql;

use App\Models\BTransaction;
use Illuminate\Support\Facades\DB;

class TransactionsRepository extends Repository
{
  /**
   * Fetches one record from database.
   * @return ?\stdClass
   */
  public static function fetchOne(array $where, array $select = null, ?array $orderBy = null): ?\stdClass
  {
    return self::fetchMany($where, $select, $orderBy, 1)->first();
  }

  public static function fetchMany(array $where, array $select, ?array $orderBy, int $limit)
  {
    if(count($select) == 0)
      throw new \Exception('Please define columns to select (none found)');

    $results = DB::table('transactions')
      ->select($select);
    foreach($where as $k => $v) {
      $results = $results->where($k,$v);
    }

    if($orderBy !== null)
      $results = $results->orderBy($orderBy[0],$orderBy[1]);

    return $results->get();
  }
  
}