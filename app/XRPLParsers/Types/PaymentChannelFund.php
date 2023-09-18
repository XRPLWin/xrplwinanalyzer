<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class PaymentChannelFund extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SENT','UNKNOWN'];

  /**
   * Parses TrustSet type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @see https://xrpl.org/paymentchannelcreate.html#paymentchannelcreate
   * @see 98ABAD54B58A12A025F510CA50DE3296501530097434FE556E4F6D082CE15D29
   * @see 4632019AF5174B7E69B99B5B4D968FA61F1347C48CD9DE6B873759C964DC437E with context rMdG3ju8pgyVh29ELPWaDuA74CpWW6Fxns
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on PaymentChannelFund with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

    $this->data['Counterparty'] = $this->tx->Account;

    //Counterparty is found in PayChannel Modified node
    if($this->reference_address == $this->tx->Account) {

      foreach($this->meta->AffectedNodes as $an) {
        if(isset($an->ModifiedNode) && $an->ModifiedNode->LedgerEntryType == 'PayChannel') {
          if(isset($an->ModifiedNode->FinalFields->Destination))
            $this->data['Counterparty'] = $an->ModifiedNode->FinalFields->Destination;
          break;
        }
      }
    }
    
    //Fund is always out
    $this->data['In'] = false;

    /*if($this->reference_address == $this->tx->Account) {
      $this->data['In'] = false;
    } else {
      $this->data['In'] = false;
    }*/
    
    # Balance changes from eventList (primary/secondary, both, one, or none)
    if(isset($this->data['eventList']['primary'])) {
      $this->data['Amount'] = $this->data['eventList']['primary']['value'];
      if($this->data['eventList']['primary']['currency'] !== 'XRP') {
        throw new \Exception('Unhandled non XRP value on PaymentChannelFund with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
      }
    }

  }

  /**
   * Returns standardized array of relevant data for storing to Dynamo database.
   * key => value one dimensional array which correlates to column => value in DyDb.
   * @return array
   */
  public function toBArray(): array
  {
    $r = [
      't' => ripple_epoch_to_carbon((int)$this->data['Date'])->format('Y-m-d H:i:s.uP'),
      'l' => $this->data['LedgerIndex'],
      'li' => $this->data['TransactionIndex'],
      'isin' => $this->data['In'],
      'r' => (string)$this->data['Counterparty'],
      'h' => (string)$this->data['hash'],
      'offers' => [],
      'nftoffers' => [],
    ];

    if(\array_key_exists('Amount', $this->data))
      $r['a'] = $this->data['Amount'];
    
    if(\array_key_exists('Fee', $this->data))
      $r['fee'] = $this->data['Fee'];

    return $r;
  }
}