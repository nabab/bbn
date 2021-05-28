<?php
/**
 * @package api
 */
namespace bbn\Api;
use bbn;
/**
 * A class for Paybox
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Utilities
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.2r89
 */
class Paybox
{
	/**
	 * @var string
	 */
	private static
		$servers = ['tpeweb.paybox.com', 'tpeweb1.paybox.com'],
		//	$servers = array('preprod-tpeweb.paybox.com'),
		$url_reponse = 'http://www.atlantica.fr/paybox',	
		$url = '/cgi/MYchoix_pagepaiement.cgi',
		$currencies = [
			'EUR' => '978',
			'USD' => '840',
			'CFA' => '952'
		],
		$encryptions = ['SHA512','SHA256','RIPEMD160','SHA224','SHA384'];
	private $cfg, $server, $price, $enc, $time, $form, $params, $query, $binkey, $currency, $hmac, $email, $ref, $processed = false;
	public $online = false;

	/**
	 * @return void 
	 */
	public function __construct(array $cfg, $price, $email, $ref, $currency = 'EUR')
	{
		if ( isset($cfg['site'],$cfg['rang'],$cfg['id'],$cfg['key'],$cfg['pass'])
		&& is_numeric($price)
		&& $price > 0
		&& bbn\Str::isEmail($email)
		&& $this->checkServer() )
		{
			if ( isset(self::$currencies[$currency]) )
			{
				$this->currency = self::$currencies[$currency];
				$this->price = $price * 100;
				$this->email = $email;
				$this->binkey = pack("H*",$cfg['key']);
				$this->ref = $ref;
				$this->cfg = $cfg;
				$this->enc = self::$encryptions[array_rand(self::$encryptions)];
				$this->time = date('c');
				$this->params = [
					'PBX_SITE' => $this->cfg['site'],
					'PBX_RANG' => $this->cfg['rang'],
					'PBX_IDENTIFIANT' => $this->cfg['id'],
					'PBX_TOTAL' => $this->price,
					'PBX_DEVISE' => $this->currency,
					'PBX_CMD' => $this->ref,
					'PBX_PORTEUR' => $this->email,
					'PBX_RETOUR' => 'Total:M;nomSession:R;NumAutorisation:A;NumTransaction:T;TypeCarte:C;Erreur:E',
					'PBX_REPONDRE_A' => self::$url_reponse,
					'PBX_HASH' => $this->enc,
					'PBX_TIME' => $this->time
				];
				$this->process();
				$this->online = 1;
			}
		}
	}

	/**
	 * @return void 
	 */
	private function checkServer()
	{
		if ( !isset($this->server) )
		{
			foreach( self::$servers as $s )
			{
				$doc = new \DOMDocument();
				$doc->loadHTMLFile('https://'.$s.'/load.html');
				$element = $doc->getElementById('server_status');
				if ( $element && $element->textContent === 'OK' )
				{
					$this->server = $s;
					return 1;
					break;
				}
			}
		}
		return false;
	}
	private function process()
	{
		if ( !isset($this->query) )
		{
			$this->query = urldecode(http_build_query($this->params));
			$this->hmac = strtoupper(hash_hmac(strtolower($this->enc),$this->query,$this->binkey));
		}
	}
	public function getForm($button_title='Paiement Paybox')
	{
		$form = '';
		if ( isset($this->server, $this->hmac) )
		{
			$form .= '<form method="post" action="https://'.$this->server.self::$url.'">'.PHP_EOL;
			foreach ( $this->params as $k => $p ){
				$form .= '<input type="hidden" name="'.$k.'" value="'.$p.'">'.PHP_EOL;
			}
			$form .= '<input type="hidden" name="PBX_HMAC" value="'.$this->hmac.'">'.PHP_EOL;
			$form .= '<input type="submit" value="'.$button_title.'">'.PHP_EOL.'</form>';
			
		}
		return $form;
	}
}