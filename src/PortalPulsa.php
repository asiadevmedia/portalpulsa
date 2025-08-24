<?php
namespace Asiadevmedia\PortalPulsa;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\HandlerStack;
use Asiadevmedia\GuzzleRateLimiter\RateLimiterMiddleware;

class PortalPulsa {

	public static $usernames;
    public static $apiKeys;
    public static $secretKeys;
    public static $apiBases = 'https://portalpulsa.com/api/connect';

    /**
     * Init PortalPulsa
     *
     * @param string $usernames
     * 
     * @param string $apiKeys
     *
     * @return void
     */
    public static function initPortal($usernames, $apiKeys, $secretKeys)
    {
        self::$usernames = $usernames;
        self::$apiKeys = $apiKeys;
        self::$secretKeys = $secretKeys;
    }

    /**
     * Username getter
     *
     * @return string
     */
    public static function getUsername()
    {
        return self::$usernames;
    }

    /**
     * ApiKey getter
     *
     * @return string
     */
    public static function getApiKey()
    {
        return self::$apiKeys;
    }

    /**
     * SecretKey getter
     *
     * @return string
     */
    public static function getSecretKey()
    {
        return self::$secretKeys;
    }
    
    /**
     * ApiBase getter
     *
     * @return string
     */
    public static function getApiBase()
    {
        return self::$apiBases;
    }

    /**
     * ApiBase setter
     *
     * @param string $apiBases
     *
     * @return void
     */
    public static function setApiBase($apiBases)
    {
        self::$apiBases = $apiBases;
    }


	/**
	 * Send request to PortalPulsa Server
	 * @param  Array $data Request Data
	 * @return Array
	 */
	public static function prosess($params = []){
        try {
        	$handlers = HandlerStack::create();
			$handlers->push(RateLimiterMiddleware::perSecond(5));
			$client = new Client([
			    'verify' => false,
			    'handler' => $handlers,
			    'base_uri' => self::$apiBases,
			    'headers'  => array(
			        'content-type' => 'application/json',
			        'portal-userid'=> self::$usernames,
			        'portal-key'=> self::$apiKeys,
			        'portal-secret'=> self::$secretKeys
			    ),
			    'timeout'  => 10.0,
			]);
			$response = $client->requestAsync('POST', self::$apiBases, ['form_params' => $params])
            ->then(function (Response $res) {
                return $res->getBody();
            })->wait();
			$response = json_decode($response);
			return $response;
        } catch (Exception $e) {
        	file_put_contents(LOGPATH . '/errorprosessportalpulsa.json', json_encode($e->getMessage()).PHP_EOL, FILE_APPEND);
        	return $e->getMessage();
        }
	}

	/**
	 * Check Balance / Saldo
	 * @return Array
	 */
	public static function ceksaldo(){
		$result = self::prosess(array('inquiry' => 'S'));
		return $result->balance;
	}

	/**
	 * Checking Price
	 * @param  String $code product code (https://portalpulsa.com/pulsa-murah-all-operator/)
	 * @return Array
	 */
	public static function cekHarga($code, $item = null){
		$result = self::prosess(array('inquiry' => 'HARGA','code' => $code));
		if($item !== null){
			$foundProducts = array_filter($result->message, function($product) use ($item) {
			    return strtoupper($product->code) === strtoupper($item);
			});
			if(!empty($foundProducts)) {
			    $result->message = current($foundProducts);
			} else{
				$result->message = null;
			}
		}
		return $result->message;
	}

	/**
	 * Check Transaction Status
	 * @param  String $uuid transaction id from client
	 * @return Array
	 */
	public static function status($uuid){
		$data = array( 
			'inquiry' => 'STATUS',
			'trxid_api' => $uuid,
		);
		$result = self::prosess($data);
		return $result;
	}

	/**
	 * Transaction Process
	 * @param  String $code  Product Code (https://portalpulsa.com/pulsa-murah-all-operator/)	
	 * @param  String $nomor Customer Phone Number
	 * @param  String $uuid    Transaction Id (like bill number)
	 * @param  String $no    untuk isi lebih dari 1x dlm sehari, isi urutan 1,2,3,4,dst
	 * @return Array
	 */
	public static function prosesPulsa($params){
		$data = array('inquiry' => 'I');
		$params = array_merge($data, $params);
		$result = self::prosess($params);
		return $result;
	}

	/**
	 * Request To Top Up Balance
	 * @param  String $bank  Bank Name
	 * @param  Integer $value Topup Amount
	 * @return Array
	 */
	public static function reqBalance($bank,$value){
		$data = array( 
			'inquiry' => 'D',
			'bank' => $bank, // bank : bca, bni, mandiri, bri, muamalat
			'nominal' => $value,
		);

		$result = self::prosess($data);
		return $result;
	}

	/**
	 * Transaction Process For PLN Token
	 * @param  String $code      Product Code (https://portalpulsa.com/token-pulsa-pln-prabayar-murah/)
	 * @param  String $nomor     Customer Phone Number
	 * @param  String $custNomor Customer PLN Number
	 * @param  String $uuid        Transaction id (like bill number)
	 * @return Array
	 */
	public static function prosesPLN($code, $nomor, $custNomor, $uuid){
		$data = array( 
			'inquiry' => 'PLN',
			'code' => $code, // kode produk
			'phone' => $nomor, // nohp pembeli
			'idcust' => $custNomor, // nomor meter atau id pln
			'trxid_api' => $uuid, // Trxid / Reffid dari sisi client
		);

		$result = self::prosess($data);
		return $result;
	}

}
