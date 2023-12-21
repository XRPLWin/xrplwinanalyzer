<?php


if (!function_exists('instanceid')) {
  /**
  * Config on demand, Laravel way.
  * @param string $namespace - dot seperated namespace where first param is config_static/FILE.php
  * @return mixed
  */
  function instanceid()
  {
    return \substr(config('app.key'),7,4);
  }
}


if (!function_exists('config_static')) {
  /**
  * Config on demand, Laravel way.
  * @param string $namespace - dot seperated namespace where first param is config_static/FILE.php
  * @return mixed
  */
  function config_static(string $namespace)
  {
    $ex = \explode('.',$namespace);
    $path = base_path().'/config_static/'.$ex[0].'.php';
    if(!is_file($path))
      return null;
    $data = include $path;
    array_shift($ex);
    return data_get($data,$ex);
  }
}

if (!function_exists('ripple_epoch_to_epoch')) {
  function ripple_epoch_to_epoch(int $ripple_date)
  {
    return $ripple_date + config('xrpl.ripple_epoch');
  }
}

if (!function_exists('ripple_epoch_to_carbon')) {
  function ripple_epoch_to_carbon(int $ripple_date)
  {
    $timestamp = $ripple_date + config('xrpl.ripple_epoch');
    return \Carbon\Carbon::createFromTimestamp($timestamp);
  }
}

if (!function_exists('bqtimestamp_to_carbon')) {
  function bqtimestamp_to_carbon(string $timestamp) {
    return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s.uP',$timestamp);
  }
}

if (!function_exists('transactions_db_name')) {
  /**
   * Returns table name (depending of configuration variables)
   * @param string $yyyymm eg 202303 or $m->format('Ym')
   * @return string
   */
  function transactions_db_name(string $yyyymm): string
  {
    if(config('xwa.database_engine') == 'sql')
      return 'transactions'.$yyyymm;
    return 'transactions';
  }
}

if (!function_exists('transactions_shard_period')) {
  /**
   * Returns array of strings (suffixes) for transactions_db_name() generation.
   * @return array
   */
  function transactions_shard_period(): array
  {
    if(config('xwa.database_engine') == 'sql') {
      $startdate = ripple_epoch_to_carbon(config('xrpl.'.config('xrpl.net').'.genesis_ledger_close_time'));
      $period = \Carbon\CarbonPeriod::create($startdate, '1 month', now()->addMonth());
      $r = [];
      foreach($period as $m) {
        $r[] = $m->format('Ym');
      }
      return $r;
    }
    return [];
  }
}

if (!function_exists('xrpl_has_flag')) {
  /**
   * Check if $check is included in $flags using bitwise-and operator.
   * @return bool
   */
  function xrpl_has_flag(int $flags, int $check): bool
  {
  	return ($flags & $check) ? true : false;
  }
}

if (!function_exists('calcPercentFromTwoNumbers')) {
  function calcPercentFromTwoNumbers($num_amount, $num_total,$decimal_places = 3): float {
  	$count1 = $num_amount / $num_total;
  	$count2 = $count1 * 100;
  	$count = number_format($count2, $decimal_places);
    return (float)$count;
  }
}

if (!function_exists('wallet_to_short')) {
  /**
   * Shortify wallet address to xxxx....xxxx
   * @return string
   */
  function wallet_to_short(string $wallet, string $seperator = '....'): string
  {
    return substr($wallet,0,4).$seperator.substr($wallet,-4,4);
  }
}

if (!function_exists('drops_to_xrp')) {
  /**
  * Converts drops to XRP.
  */
  function drops_to_xrp(int $num)
  {
    return $num/1000000;
  }
}

//TODO: https://github.com/XRPLF/xrpl-dev-portal/blob/master/content/_code-samples/normalize-currency-codes/js/normalize-currency-code.js
if (!function_exists('xrp_currency_to_symbol')) {
  /**
  * Decode HEX XRPL currency to symbol.
  * If already symbol returns that symbol (checked by length).
  * Examples: USD,EUR,534F4C4F00000000000000000000000000000000
  * @return string
  */
  function xrp_currency_to_symbol($currencycode, $malformedUtf8ReturnString = '?') : string
  {
    //$tempArray = array_fill(0, 20, 0x00);
    //$bytesArray = SplFixedArray::fromArray($tempArray);
    //dd($bytesArray);
    //dd(\hex2bin($currencycode));
    ################# OLD
    if( \strlen($currencycode) == 40 )
    {
      $r = \trim(\hex2bin($currencycode));
      $r = preg_replace('/[\x00-\x1F\x7F]/', '', $r); //remove first 32 ascii characters and \x7F https://en.wikipedia.org/wiki/Control_character
      if(preg_match('//u', $r)) //This will will return 0 (with no additional information) if an invalid string is given.
        return $r;
      return $malformedUtf8ReturnString; //malformed UTF-8 string
    }
    return $currencycode;
  }
}

if (!function_exists('xw_number_format')) {

  function xw_number_format($decimalnumber) {

    if(\Str::contains((string)$decimalnumber,'.'))
      return rtrim(rtrim((string)$decimalnumber,'0'),'.');

    return $decimalnumber;
  }
}


if (!function_exists('format_with_suffix')) {
  /**
  * For claim this site domain verification and other
  */
  function format_with_suffix(mixed $number)
  {
    $number_orig = $number;
    $suffixes = array('', 'k', 'm', 'B', 'T', ' quad', ' quint', ' sext', ' sept');
    $suffixIndex = 0;

    while(abs($number) >= 1000 && $suffixIndex < sizeof($suffixes))
    {
        $suffixIndex++;
        $number /= 1000;
    }

    if(!isset($suffixes[$suffixIndex]))
      return $number_orig;

    return (
        $number > 0
            // precision of 3 decimal places
            ? round((floor($number * 1000) / 1000),1)
            : ceil($number * 1000) / 1000
        )
        . $suffixes[$suffixIndex];
  }
}

if (!function_exists('getbaseurlfromurl')) {
  /**
  * For claim this site domain verification and other
  */
  function getbaseurlfromurl(string $url)
  {
    $url_info = parse_url($url);
    return $url_info['scheme'] . '://' . $url_info['host'];
  }
}

if (!function_exists('getbasedomainfromurl')) {
  /**
  * For claim this site domain verification and other
  * https://xrpl.win -> xrpl.win
  * https://beta.xrpl.win -> beta.xrpl.win
  * beta.xrpl.win -> beta.xrpl.win
  * xrpl.win -> xrpl.win
  */
  function getbasedomainfromurl(string $url)
  {
    $url_info = parse_url($url);
    return $url_info['host'];
  }
}

if (!function_exists('validateXRPAddressOrFail')) {
  /**
  * Validates XRP Address or throw exception.
  * @throws Symfony\Component\HttpKernel\Exception\HttpException
  */
  function validateXRPAddressOrFail(mixed $address): void
  {
    if(!isValidXRPAddressFormat($address))
      abort(422, 'XRP address format is invalid');
  }
}

if (!function_exists('isValidXRPAddressFormat')) {
  /**
  * Validates XRP Address or throw exception.
  * @throws Symfony\Component\HttpKernel\Exception\HttpException
  */
  function isValidXRPAddressFormat(mixed $address): bool
  {
    $validator = \Illuminate\Support\Facades\Validator::make(['address' => $address], [
      'address' => ['string',  new \App\Rules\XRPAddress],
    ]);
    if ($validator->fails())
      return false;
    return true;
  }
}

if (!function_exists('memory_get_usage_formatted')) {
  function memory_get_usage_formatted()
  {
    $size = memory_get_usage(true);
    $unit=array('b','kb','mb','gb','tb','pb');
    return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
  }
}

if (!function_exists('stringDecimalX10000')) {
  /**
   * Converts 123456.079 to 1234560790
   */
  function stringDecimalX10000(string $num): int
  {
    $ex = \explode('.',$num);
    if(!isset($ex[1])) $ex[1] = '0';
    $r = $ex[0].\str_pad($ex[1],4,'0',STR_PAD_RIGHT);
    return $r;
  }
}

/**
 * CTID
 * @see https://github.com/XRPLF/CTID/blob/main/ctid.php
 */
if (!function_exists('encodeCTID')) {
  function encodeCTID($ledger_seq, $txn_index, $network_id)
  {
    if (!is_numeric($ledger_seq))
      throw new Exception("ledger_seq must be a number.");
    if ($ledger_seq > 0xFFFFFFF || $ledger_seq < 0)
      throw new Exception("ledger_seq must not be greater than 268435455 or less than 0.");

    if (!is_numeric($txn_index))
      throw new Exception("txn_index must be a number.");
    if ($txn_index > 0xFFFF || $txn_index < 0)
      throw new Exception("txn_index must not be greater than 65535 or less than 0.");

    if (!is_numeric($network_id))
      throw new Exception("network_id must be a number.");
    if ($network_id > 0xFFFF || $network_id < 0)
      throw new Exception("network_id must not be greater than 65535 or less than 0.");

    $ledger_part = dechex($ledger_seq);
    $txn_part = dechex($txn_index);
    $network_part = dechex($network_id);

    if (strlen($ledger_part) < 7)
        $ledger_part = str_repeat("0", 7 - strlen($ledger_part)) . $ledger_part;
    if (strlen($txn_part) < 4)
        $txn_part = str_repeat("0", 4 - strlen($txn_part)) . $txn_part;
    if (strlen($network_part) < 4)
        $network_part = str_repeat("0", 4 - strlen($network_part)) . $network_part;

    return strtoupper("C" . $ledger_part . $txn_part . $network_part);
  }
}
/*echo PHP_INT_MAX;
$val = "18446744073709551614";
$maxUnsign64Bit ="18446744073709551615";
if(ctype_digit($val) AND bccomp($val,$maxUnsign64Bit) !== 1){
  echo 'is a 64 Bit number';
};
exit;*/

if (!function_exists('decodeCTID')) {
  function decodeCTID($ctid)
  {
    if (is_string($ctid))
    {
      if (!ctype_xdigit($ctid))
        throw new Exception("ctid must be a hexadecimal string");
      if (strlen($ctid) !== 16)
        throw new Exception("ctid must be exactly 16 nibbles and start with a C");
    } else
      throw new Exception("ctid must be a hexadecimal string");
  
    if (substr($ctid, 0, 1) !== 'C')
      throw new Exception("ctid must be exactly 16 nibbles and start with a C");
  
    $ledger_seq = substr($ctid, 1, 7);
    $txn_index = substr($ctid, 8, 4);
    $network_id = substr($ctid, 12, 4);
    return array(
      "ledger_seq" => hexdec($ledger_seq),
      "txn_index" => hexdec($txn_index),
      "network_id" => hexdec($network_id)
    );
  }
}
if (!function_exists('bchexdec')) {
  /**
   * hexdec but suppported uint64 numbers
   */
  function bchexdec(string $hex): string
  {
    $dec = 0;
    $len = strlen($hex);
    for ($i = 1; $i <= $len; $i++) {
      $dec = bcadd((string)$dec, bcmul(strval(hexdec($hex[$i - 1])), bcpow('16', strval($len - $i))));
    }
    return $dec;
  }
}
if (!function_exists('bcdechex')) {
  /**
   * dechex but suppported uint64 numbers
   */
  function bcdechex(string $dec): string
  {
    $last = bcmod($dec, 16);
    $remain = bcdiv(bcsub($dec, $last), 16);
    if($remain == 0) {
      $r = dechex($last);
    } else {
      $r = bcdechex($remain).dechex($last);
    }
    return \strtoupper($r);
  }
}
//https://github.com/protocolbuffers/protobuf/pull/14552/files
/*
var_dump('C01673490000535A');
$test = \bchexdec('C01673490000535A');
var_dump($test);
$test2 = \bcdechex($test);
var_dump($test2);
exit;
*/