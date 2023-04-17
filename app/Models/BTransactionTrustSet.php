<?php

namespace App\Models;

/**
 * Transaction model of type TrustSet.
 * PK: rAcct-3 SK: <INT> (Ledger index)
 */
final class BTransactionTrustSet extends BTransaction
{
  const TYPE = 3;
  const CONTEXT_ADDTRUSTLINE = 'addtrustline';
  const CONTEXT_REMOVETRUSTLINE = 'removetrustline';

  public function toFinalArray()
  {
    $array = [
      'type' => $this::TYPE,
      'context' => $this::CONTEXT_ADDTRUSTLINE
    ];
    $array = \array_merge(parent::toArray(),$array);
    if(isset($array['i']) && $array['i'] !== null && isset($array['c']) && $array['c'] !== null){
      //it is issued currency
      $array['c_formatted'] = xrp_currency_to_symbol($array['c']);
    }

    //CONTEXT
    if($array['a'] == '0' || empty($array['a']))
      $array['context'] = $this::CONTEXT_REMOVETRUSTLINE;

    return $array;
  }
  
}