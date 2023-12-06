<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;
use XRPLWin\XRPLNFTTxMutatationParser\NFTTxMutationParser;

final class URITokenMint extends XRPLParserBase
{
private array $acceptedParsedTypes = ['SET','RECEIVED','REGULARKEYSIGNER','UNKNOWN'];

  /**
   * Parses URITokenMint type fields and maps them to $this->data
   * @see https://docs.xahau.network/technical/protocol-reference/transactions/transaction-types/uritokenmint
   * @see mint to different owner: D636ABADC71096929861F24261210F17A8BD8505A24AF74F614C9057D7C83B42 xahau
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on URITokenMint with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
    
    if($parsedType == 'REGULARKEYSIGNER') {
      $this->persist = false;
    }

    $nftparser = new NFTTxMutationParser($this->reference_address, $this->tx);
    $nftparserResult = $nftparser->result();

    $this->data['nft'] = $nftparserResult['nft'];

    //dd($nftparserResult['ref']['roles']);
    if(\in_array(NFTTxMutationParser::ROLE_OWNER,$nftparserResult['ref']['roles'])) { //minter is always first owner
      $this->data['In'] = true;
      $this->data['Counterparty'] = $this->tx->Account;
    } else {
      //Probably not applicable for URITokenMint, catch this and fix later:
      //throw new \Exception('Unhandled case in URITokenMint with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

      $this->data['In'] = false;
      $this->persist = false;
      $this->data['Counterparty'] = $this->tx->Account;

      if(isset($this->tx->Destination)) {
        //This NFT is minted in behalf of another account
        $this->data['Counterparty'] = $this->tx->Destination;
      }

      if(\in_array(NFTTxMutationParser::ROLE_MINTER,$nftparserResult['ref']['roles'])) {
        //minter
        $this->persist = true;
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
      'nft' => (string)$this->data['nft'],
      'nftoffers' => [],
      'hooks' => $this->data['hooks'],
    ];

    if(\array_key_exists('Amount', $this->data))
      $r['a'] = $this->data['Amount'];
    
    if(\array_key_exists('Issuer', $this->data))
      $r['i'] = $this->data['Issuer'];

    if(\array_key_exists('Currency', $this->data))
      $r['c'] = $this->data['Currency'];

    if(\array_key_exists('Fee', $this->data))
      $r['fee'] = $this->data['Fee'];

    return $r;
  }
}