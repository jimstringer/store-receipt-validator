<?php
namespace ReceiptValidator\iTunes;

use ReceiptValidator\RunTimeException;
use \GuzzleHttp\Client as HttpClient;

class Validator
{

  const ENDPOINT_SANDBOX = 'https://sandbox.itunes.apple.com/verifyReceipt';
  const ENDPOINT_PRODUCTION = 'https://buy.itunes.apple.com/verifyReceipt';

  /**
   * endpoint url
   *
   * @var string
   */
  protected $_endpoint;

  /**
   * itunes receipt data, in base64 format
   *
   * @var string
   */
  protected $_receiptData;


  /**
   * The shared secret is a unique code to receive your In-App Purchase receipts.
   * Without a shared secret, you will not be able to test or offer your automatically
   * renewable In-App Purchase subscriptions.
   *
   * @var string
   */
  protected $_sharedSecret = null;

  /**
   * exclude-old-transactions 
   * (Only used for iOS7  auto-renewable or non-renewing subscriptions. If value is true, response includes the latest renewal transaction for any subscriptions.)
   *
   * @var bool
   */
  protected $_exclude_old_transactions = false;


  /**
   * Guzzle http client
   *
   * @var \GuzzleHttp\Client
   */
  protected $_client = null;

  public function __construct($endpoint = self::ENDPOINT_PRODUCTION)
  {
    if ($endpoint != self::ENDPOINT_PRODUCTION && $endpoint != self::ENDPOINT_SANDBOX) {
      throw new RunTimeException("Invalid endpoint '{$endpoint}'");
    }

    $this->_endpoint = $endpoint;
  }

  /**
   * Set _exclude_old_transactions
   *
   * @return $this
   */
  public function setExcludeOldTransactions($exclude)
  {
      $this->_exclude_old_transactions = $exclude;
      return $this;
  }

  /**
   * Get  _exclude_old_transactions
   *
   * @return bool
   */
  public function getExcludeOldTransactions()
  {
      return $this->_exclude_old_transactions;
  }

  /**
   * get receipt data
   *
   * @return string
   */
  public function getReceiptData()
  {
    return $this->_receiptData;
  }

  /**
   * set receipt data, either in base64, or in json
   *
   * @param string $receiptData
   * @return \ReceiptValidator\iTunes\Validator;
   */
  function setReceiptData($receiptData)
  {
    if (strpos($receiptData, '{') !== false) {
      $this->_receiptData = base64_encode($receiptData);
    } else {
      $this->_receiptData = $receiptData;
    }

    return $this;
  }

  /**
   * @return string
   */
  public function getSharedSecret()
  {
    return $this->_sharedSecret;
  }

  /**
   * @param string $sharedSecret
   * @return $this
   */
  public function setSharedSecret($sharedSecret)
  {
    $this->_sharedSecret = $sharedSecret;

    return $this;
  }

  /**
   * get endpoint
   *
   * @return string
   */
  public function getEndpoint()
  {
    return $this->_endpoint;
  }

  /**
   * set endpoint
   *
   * @param string $endpoint
   * @return $this
   */
  function setEndpoint($endpoint)
  {
    $this->_endpoint = $endpoint;

    return $this;
  }

  /**
   * returns the Guzzle client
   *
   * @return \GuzzleHttp\Client
   */
  protected function getClient()
  {
    if ($this->_client == null) {
      $this->_client = new \GuzzleHttp\Client(['base_uri' => $this->_endpoint]);
    }

    return $this->_client;
  }

  /**
   * encode the request in json
   *
   * @return string
   */
  private function encodeRequest()
  {
    $request = ['receipt-data' => $this->getReceiptData()];

    if (!is_null($this->_sharedSecret)) {

      $request['password'] = $this->_sharedSecret;
    }

    $request['exclude-old-transactions'] = $this->_exclude_old_transactions;

    return json_encode($request);
  }

  /**
   * validate the receipt data
   *
   * @param string $receiptData
   * @param string $sharedSecret
   *
   * @throws RunTimeException
   * @return Response
   */
  public function validate($receiptData = null, $sharedSecret = null)
  {

    if ($receiptData != null) {
      $this->setReceiptData($receiptData);
    }

    if ($sharedSecret != null) {
      $this->setSharedSecret($sharedSecret);
    }

    $httpResponse = $this->getClient()->request('POST', null, ['body' => $this->encodeRequest()]);

    if ($httpResponse->getStatusCode() != 200) {
      throw new RunTimeException('Unable to get response from itunes server');
    }

    $response = new Response(json_decode($httpResponse->getBody(), true));

    // on a 21007 error retry the request in the sandbox environment (if the current environment is Production)
    // these are receipts from apple review team
    if ($this->_endpoint == self::ENDPOINT_PRODUCTION && $response->getResultCode() == Response::RESULT_SANDBOX_RECEIPT_SENT_TO_PRODUCTION) {
      $client = new HttpClient(['base_uri' => self::ENDPOINT_SANDBOX]);

      $httpResponse = $client->request('POST', null, ['body' => $this->encodeRequest()]);

      if ($httpResponse->getStatusCode() != 200) {
        throw new RunTimeException('Unable to get response from itunes server');
      }

      $response = new Response(json_decode($httpResponse->getBody(), true));
    }

    return $response;
  }
}
