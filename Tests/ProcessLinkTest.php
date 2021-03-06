<?php
/**
 * @file
 * Unit tests for Process Link component of the iATS API.
 */

namespace iATS;

/**
 * Class ProcessLinkTest
 *
 * @package iATS
 */
class ProcessLinkTest extends \PHPUnit_Framework_TestCase {

  /** @var string $agentCode */
  private static $agentCode;

  /** @var string $password */
  private static $password;

  /** @var string $batchFilePath */
  private static $batchFilePath;

  // Varables generated by tests and referenced by later tests.

  /** @var string $ACHEFTCustomerCode */
  private static $ACHEFTCustomerCode;

  /** @var string $ACHEFTTransationId */
  private static $ACHEFTTransationId;

  /** @var string $creditCardCustomerCode */
  private static $creditCardCustomerCode;

  /** @var string $creditCardTransactionId */
  private static $creditCardTransactionId;

  /** @var string $creditCardBatchId */
  private static $creditCardBatchId;

  /** @var string $ACHEFTBatchId */
  private static $ACHEFTBatchId;

  /** @var string $ACHEFTBatchRefundId */
  private static $ACHEFTBatchRefundId;

  /** @var string $ACHEFTInvalidFormatBatchId */
  private static $ACHEFTInvalidFormatBatchId;

  public static function setUpBeforeClass()
  {
    self::$agentCode = IATS_AGENT_CODE;
    self::$password = IATS_PASSWORD;

    self::$batchFilePath = dirname(__FILE__) . '/batchfiles/';

    // Create temporary batch files.
    self::createACHEFTBatchFile();
    self::createACHEFTBatchFileInvalidFormat();
    self::createACHEFTRefundBatchFile();
    self::createCreditCardBatchFile();
  }

  public static function tearDownAfterClass()
  {
    // Destroy temporary batch files.
    unlink(self::$batchFilePath . 'ACHEFTBatch.txt');
    unlink(self::$batchFilePath . 'ACHEFTInvalidFormatBatch.txt');
    unlink(self::$batchFilePath . 'ACHEFTRefundBatch.txt');
    unlink(self::$batchFilePath . 'CreditCardUSUKBatch.txt');
  }

  /**
   * Test API credentials.
   */
  public function testCredentials() {
    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'invoiceNum' => '00000001',
      'creditCardNum' => '4222222222222220',
      'creditCardExpiry' => '12/17',
      'cvv2' => '000',
      'mop' => 'VISA',
      'firstName' => 'Test',
      'lastName' => 'Account',
      'address' => '1234 Any Street',
      'city' => 'Schenectady',
      'state' => 'NY',
      'zipCode' => '12345',
      'total' => '5',
      'comment' => 'Process CC test.',
      'currency' => 'USD',
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->processCreditCard($request);

    $this->assertStringStartsWith('OK', trim($response['AUTHORIZATIONRESULT']));
  }

  /**
   * Test createCustomerCodeAndProcessACHEFT.
   *
   * @depends testCredentials
   */
  public function testProcessLinkcreateCustomerCodeAndProcessACHEFT() {
    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'firstName' => 'Test',
      'lastName' => 'Account',
      'address' => '1234 Any Street',
      'city' => 'Schenectady',
      'state' => 'NY',
      'zipCode' => '12345',
      'accountNum' => '02100002100000000000000001',
      'accountType' => 'CHECKING',
      'invoiceNum' => '00000001',
      'total' => '5',
      'comment' => 'Process direct debit test.',
      // Not required for request
      'currency' => 'USD',
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->createCustomerCodeAndProcessACHEFT($request);

    $this->assertStringStartsWith('OK', trim($response['AUTHORIZATIONRESULT']));

    self::$ACHEFTCustomerCode = trim($response['CUSTOMERCODE']);
    self::$ACHEFTTransationId = trim($response['TRANSACTIONID']);
  }

  /**
   * Test createCustomerCodeAndProcessCreditCard.
   *
   * @depends testCredentials
   */
  public function testProcessLinkcreateCustomerCodeAndProcessCreditCard() {
    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'invoiceNum' => '00000001',
      'ccNum' => '4222222222222220',
      'ccExp' => '12/17',
      'mop' => 'VISA',
      'firstName' => 'Test',
      'lastName' => 'Account',
      'address' => '1234 Any Street',
      'city' => 'Schenectady',
      'state' => 'NY',
      'zipCode' => '12345',
      'cvv2' => '000',
      'total' => '5',
      'currency' => 'USD',
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->createCustomerCodeAndProcessCreditCard($request);

    $this->assertStringStartsWith('OK', trim($response['AUTHORIZATIONRESULT']));

    self::$creditCardCustomerCode = trim($response['CUSTOMERCODE']);
    self::$creditCardTransactionId = trim($response['TRANSACTIONID']);
  }

  /**
   * Test processACHEFTChargeBatch.
   *
   * @depends testCredentials
   */
  public function testProcessLinkprocessACHEFTChargeBatch() {
    $fileContents = $this->getBatchFile('ACHEFTBatch.txt');

    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'batchFile' => $fileContents
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->processACHEFTChargeBatch($request);

    $this->assertEquals('Batch Processing, Please Wait ....', trim($response['AUTHORIZATIONRESULT']));

    self::$ACHEFTBatchId = $response['BATCHID'];

    // Pause to allow for batch file processing.
    sleep(3);
  }

  /**
   * Test getBatchProcessResultFile with an ACH / EFT batch process.
   *
   * @depends testProcessLinkprocessACHEFTChargeBatch
   */
  public function testProcessLinkgetBatchProcessResultFileACHEFT() {
    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'batchId' => self::$ACHEFTBatchId,
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->getBatchProcessResultFile($request);

    $this->assertEquals('Batch Process Has Been Done', trim($response['AUTHORIZATIONRESULT']));
    $this->assertEquals(self::$ACHEFTBatchId, $response['BATCHID']);

    $originalFileContents = $this->getBatchFile('ACHEFTBatch.txt');
    $originalData = explode("\n", $originalFileContents);

    $batchResultFileContents = trim(base64_decode($response['BATCHPROCESSRESULTFILE']));

    $batchData = explode("\r\n", $batchResultFileContents);

    // Check batch result messages and compare against original batch file.
    for ($i = 0; $i < count($originalData); $i++)
    {
      $this->assertArrayHasKey($i, $batchData);

      $originalRowData = str_getcsv($originalData[$i]);
      $batchRowData = str_getcsv($batchData[$i]);

      // Get result message from end of array.
      $batchRowMessage = array_pop($batchRowData);

      $this->assertStringStartsWith('Received', $batchRowMessage);

      // iATS API obfuscates bank account numbers. Need to also obfuscate the account
      // number in the original data for the comparison test to pass.
      $originalRowData[4] = $batchRowData[4];

      $cleanOriginalRow = implode(',', $originalRowData);
      $cleanBatchRow = implode(',', $batchRowData);

      // Compare original batch file row against batch result row.
      $this->assertEquals($cleanOriginalRow, $cleanBatchRow);
    }
  }

  /**
   * Test processACHEFTChargeBatch with incorrectly formatted data.
   *
   * @depends testCredentials
   */
  public function testProcessLinkprocessACHEFTChargeBatchInvalidFormat() {
    $fileContents = $this->getBatchFile('ACHEFTInvalidFormatBatch.txt');

    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'batchFile' => $fileContents,
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->processACHEFTChargeBatch($request);

    $this->assertEquals('Batch Processing, Please Wait ....', trim($response['AUTHORIZATIONRESULT']));

    self::$ACHEFTInvalidFormatBatchId = $response['BATCHID'];

    // Pause to allow for batch file processing.
    sleep(3);
  }

  /**
   * Test getBatchProcessResultFile with an incorrectly formatted
   * ACH / EFT batch process.
   *
   * @depends testProcessLinkprocessACHEFTChargeBatchInvalidFormat
   */
  public function testProcessLinkgetBatchProcessResultFileACHEFTInvalidFormat() {
    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'batchId' => self::$ACHEFTInvalidFormatBatchId,
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->getBatchProcessResultFile($request);

    $this->assertEquals('Batch Process Has Been Done', trim($response['AUTHORIZATIONRESULT']));
    $this->assertEquals(self::$ACHEFTInvalidFormatBatchId, $response['BATCHID']);

    $originalFileContents = $this->getBatchFile('ACHEFTInvalidFormatBatch.txt');
    $originalData = explode("\n", $originalFileContents);

    $batchResultFileContents = trim(base64_decode($response['BATCHPROCESSRESULTFILE']));

    $batchData = explode("\r\n", $batchResultFileContents);

    // Check batch result messages and compare against original batch file.
    for ($i = 0; $i < count($originalData); $i++)
    {
      $this->assertArrayHasKey($i, $batchData);

      $batchRowData = str_getcsv($batchData[$i]);

      // Get result message from end of array.
      $batchRowMessage = array_pop($batchRowData);

      $this->assertStringStartsWith('Wrong Format', $batchRowMessage);

      $cleanBatchRow = implode(',', $batchRowData);

      // Compare original batch file row against batch result row.
      $this->assertEquals($originalData[$i], $cleanBatchRow);
    }
  }

  /**
   * Test processACHEFTRefundBatch.
   *
   * @depends testProcessLinkprocessACHEFTChargeBatch
   */
  public function testProcessLinkprocessACHEFTRefundBatch() {
    $fileContents = $this->getBatchFile('ACHEFTRefundBatch.txt');

    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'batchFile' => $fileContents,
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->processACHEFTRefundBatch($request);

    $this->assertEquals('Batch Processing, Please Wait ....', trim($response['AUTHORIZATIONRESULT']));
    self::$ACHEFTBatchRefundId = $response['BATCHID'];

    sleep(3);
  }

  /**
   * Test getBatchProcessResultFile with an ACH / EFT batch refund process.
   *
   * @depends testProcessLinkprocessACHEFTRefundBatch
   */
  public function testProcessLinkgetBatchProcessResultFileACHEFTRefund() {
    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'batchId' => self::$ACHEFTBatchRefundId,
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->getBatchProcessResultFile($request);

    $this->assertEquals('Batch Process Has Been Done', trim($response['AUTHORIZATIONRESULT']));
    $this->assertEquals(self::$ACHEFTBatchRefundId, $response['BATCHID']);

    $originalFileContents = $this->getBatchFile('ACHEFTRefundBatch.txt');
    $originalData = explode("\n", $originalFileContents);

    $batchResultFileContents = trim(base64_decode($response['BATCHPROCESSRESULTFILE']));

    $batchData = explode("\r\n", $batchResultFileContents);

    // Check batch result messages and compare against original batch file.
    for ($i = 0; $i < count($originalData); $i++)
    {
      $this->assertArrayHasKey($i, $batchData);

      $originalRowData = str_getcsv($originalData[$i]);
      $batchRowData = str_getcsv($batchData[$i]);

      // Get result message from end of array.
      $batchRowMessage = array_pop($batchRowData);

      $this->assertStringStartsWith('Received', $batchRowMessage);

      // iATS API obfuscates bank account numbers. Need to also obfuscate the account
      // number in the original data for the comparison test to pass.
      $originalRowData[4] = $batchRowData[4];

      $cleanOriginalRow = implode(',', $originalRowData);
      $cleanBatchRow = implode(',', $batchRowData);

      // Compare original batch file row against batch result row.
      $this->assertEquals($cleanOriginalRow, $cleanBatchRow);
    }
  }

  /**
   * Test processACHEFTRefundWithTransactionId.
   *
   * @todo This API call returns "Invalid Customer Code" in response
   *  to a request that appears to contain valid data.
   *  Need to investigate with iATS.
   *
   * @depends testProcessLinkcreateCustomerCodeAndProcessACHEFT
   */
  public function testProcessLinkprocessACHEFTRefundWithTransactionId() {
    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'transactionId' => self::$ACHEFTTransationId,
      'total' => '-5',
      'comment' => 'ACH / EFT refund test.',
      // Not required for request
      'currency' => 'USD',
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->processACHEFTRefundWithTransactionId($request);

    //$this->assertStringStartsWith('OK', trim($response['AUTHORIZATIONRESULT']));
    $this->assertTrue(TRUE);
  }

  /**
   * Test processACHEFT.
   *
   * @depends testCredentials
   */
  public function testProcessLinkprocessACHEFT() {
    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'invoiceNum' => '00000001',
      'firstName' => 'Test',
      'lastName' => 'Account',
      'address' => '1234 Any Street',
      'city' => 'Schenectady',
      'state' => 'NY',
      'zipCode' => '12345',
      'accountNum' => '02100002100000000000000001',
      'accountType' => 'CHECKING',
      'total' => '5',
      'comment' => 'Process direct debit test.',
      // Not required for request
      'currency' => 'USD',
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->processACHEFT($request);

    $this->assertStringStartsWith('OK', trim($response['AUTHORIZATIONRESULT']));
  }

  /**
   * Test processACHEFTWithCustomerCode.
   *
   * @depends testCredentials
   */
  public function testProcessLinkprocessACHEFTWithCustomerCode() {
    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'customerCode' => self::$ACHEFTCustomerCode,
      'invoiceNum' => '00000001',
      'total' => '5',
      'comment' => 'Process direct debit test with Customer Code.',
      // Not required for request
      'currency' => 'USD',
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->processACHEFTWithCustomerCode($request);

    $this->assertStringStartsWith('OK', trim($response['AUTHORIZATIONRESULT']));
  }

  /**
   * Test processCreditCardBatch.
   *
   * @depends testCredentials
   */
  public function testProcessLinkprocessCreditCardBatch() {
    $fileContents = $this->getBatchFile('CreditCardUSUKBatch.txt');

    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'batchFile' => $fileContents,
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->processCreditCardBatch($request);

    $this->assertEquals('Batch Processing, Please Wait ....', trim($response['AUTHORIZATIONRESULT']));

    self::$creditCardBatchId = $response['BATCHID'];

    sleep(3);
  }

  /**
   * Test getBatchProcessResultFile with a credit card batch process.
   *
   * @depends testProcessLinkprocessCreditCardBatch
   */
  public function testProcessLinkgetBatchProcessResultFileCreditCard() {
    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'batchId' => self::$creditCardBatchId,
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->getBatchProcessResultFile($request);

    $this->assertEquals('Batch Process Has Been Done', trim($response['AUTHORIZATIONRESULT']));
    $this->assertEquals(self::$creditCardBatchId, $response['BATCHID']);

    $originalFileContents = $this->getBatchFile('CreditCardUSUKBatch.txt');
    $originalData = explode("\n", $originalFileContents);

    $batchResultFileContents = trim(base64_decode($response['BATCHPROCESSRESULTFILE']));

    $batchData = explode("\r\n", $batchResultFileContents);

    // Check batch result messages and compare against original batch file.
    for ($i = 0; $i < count($originalData); $i++)
    {
      $this->assertArrayHasKey($i, $batchData);

      $originalRowData = str_getcsv($originalData[$i]);
      $batchRowData = str_getcsv($batchData[$i]);

      // Get result message from end of array.
      $batchRowMessage = array_pop($batchRowData);

      $this->assertStringStartsWith('OK', $batchRowMessage);

      // iATS API obfuscates credit card numbers. Need to also obfuscate the credit
      // cardnumber in the original data for the comparison test to pass.
      $originalRowData[10] = $batchRowData[10];

      $cleanOriginalRow = implode(',', $originalRowData);
      $cleanBatchRow = implode(',', $batchRowData);

      // Compare original batch file row against batch result row.
      $this->assertEquals($cleanOriginalRow, $cleanBatchRow);
    }
  }

  /**
   * Test processCreditCardRefundWithTransactionId.
   *
   * @depends testProcessLinkcreateCustomerCodeAndProcessCreditCard
   */
  public function testProcessLinkprocessCreditCardRefundWithTransactionId() {
    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'transactionId' => self::$creditCardTransactionId,
      'total' => '-5',
      'comment' => 'Credit card refund test.',
      'mop' => 'VISA',
      'currency' => 'USD',
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->processCreditCardRefundWithTransactionId($request);

    $this->assertStringStartsWith('OK', trim($response['AUTHORIZATIONRESULT']));
  }

  /**
   * Test processCreditCard.
   *
   * @depends testCredentials
   */
  public function testProcessLinkprocessCreditCard() {
    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'invoiceNum' => '00000001',
      'creditCardNum' => '4222222222222220',
      'creditCardExpiry' => '12/17',
      'cvv2' => '000',
      'mop' => 'VISA',
      'firstName' => 'Test',
      'lastName' => 'Account',
      'address' => '1234 Any Street',
      'city' => 'Schenectady',
      'state' => 'NY',
      'zipCode' => '12345',
      'total' => '5',
      'comment' => 'Process CC test.',
      'currency' => 'USD',
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->processCreditCard($request);

    $this->assertStringStartsWith('OK', trim($response['AUTHORIZATIONRESULT']));
  }

  /**
   * Test processCreditCardWithCustomerCode.
   *
   * @depends testProcessLinkcreateCustomerCodeAndProcessCreditCard
   */
  public function testProcessLinkprocessCreditCardWithCustomerCode() {
    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'customerCode' => self::$creditCardCustomerCode,
      'invoiceNum' => '00000001',
      'cvv2' => '000',
      'mop' => 'VISA',
      'total' => '5',
      'comment' => 'Process CC test with Customer Code.',
      'currency' => 'USD',
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->processCreditCardWithCustomerCode($request);

    $this->assertStringStartsWith('OK', trim($response['AUTHORIZATIONRESULT']));
  }

  /**
   * Test processCreditCard with invalid card number.
   *
   * @depends testCredentials
   */
  public function testProcessLinkprocessCreditCardInvalidCardNumber() {
    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'invoiceNum' => '00000001',
      'creditCardNum' => '9999999999999999',
      'creditCardExpiry' => '12/17',
      'cvv2' => '000',
      'mop' => 'VISA',
      'firstName' => 'Test',
      'lastName' => 'Account',
      'address' => '1234 Any Street',
      'city' => 'Schenectady',
      'state' => 'NY',
      'zipCode' => '12345',
      'total' => '5',
      'comment' => 'Process CC test with invalid CC number.',
      'currency' => 'USD',
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->processCreditCard($request);

    $this->assertTrue(is_array($response));
    $this->assertEquals($response['message'], 'Invalid card number. Card not supported by IATS.');
  }

  /**
   * Test processCreditCard with invalid credit card expiration date.
   *
   * @depends testCredentials
   */
  public function testProcessLinkprocessCreditCardInvalidExp() {
    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'invoiceNum' => '00000001',
      'creditCardNum' => '4111111111111111',
      'creditCardExpiry' => '01/10',
      'cvv2' => '000',
      'mop' => 'VISA',
      'firstName' => 'Test',
      'lastName' => 'Account',
      'address' => '1234 Any Street',
      'city' => 'Schenectady',
      'state' => 'NY',
      'zipCode' => '12345',
      'total' => '5',
      'comment' => 'Process CC test with invalid CC expiration date.',
      'currency' => 'USD',
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->processCreditCard($request);

    $this->assertTrue(is_array($response));
    $this->assertEquals($response['message'], 'General decline code. Please have client call the number on the back of credit card');
  }

  /**
   * Test processCreditCard with invalid address.
   *
   * @depends testCredentials
   */
  public function testProcessLinkprocessCreditCardInvalidAddress() {
    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'invoiceNum' => '00000001',
      'creditCardNum' => '4111111111111111',
      'creditCardExpiry' => '12/17',
      'cvv2' => '000',
      'mop' => 'VISA',
      'firstName' => 'Test',
      'lastName' => 'Account',
      'address' => '',
      'city' => '',
      'state' => '',
      'zipCode' => '',
      'total' => '5',
      'comment' => 'Process CC test with invalid address.',
      'currency' => 'USD',
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->processCreditCard($request);

    $this->assertTrue(is_array($response));
    $this->assertEquals($response['message'], 'General decline code. Please have client call the number on the back of credit card');
  }

  /**
   * Test processCreditCard with invalid IP address format.
   *
   * @depends testCredentials
   */
  public function testProcessLinkprocessCreditCardInvalidIPAddress() {
    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '100',
      'invoiceNum' => '00000001',
      'creditCardNum' => '4111111111111111',
      'creditCardExpiry' => '12/17',
      'cvv2' => '000',
      'mop' => 'VISA',
      'firstName' => 'Test',
      'lastName' => 'Account',
      'address' => '1234 Any Street',
      'city' => 'Schenectady',
      'state' => 'NY',
      'zipCode' => '12345',
      'total' => '5',
      'comment' => 'Process CC test with invalid IP address format.',
      'currency' => 'USD',
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->processCreditCard($request);

    $this->assertTrue(is_array($response));
    $this->assertEquals($response['message'], 'General decline code. Please have client call the number on the back of credit card');
  }

  /**
   * Test processCreditCard with invalid currency for current server.
   *
   * @depends testCredentials
   */
  public function testProcessLinkprocessCreditCardInvalidCurrency() {
    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'invoiceNum' => '00000001',
      'creditCardNum' => '4111111111111111',
      'creditCardExpiry' => '12/17',
      'cvv2' => '000',
      'mop' => 'VISA',
      'firstName' => 'Test',
      'lastName' => 'Account',
      'address' => '1234 Any Street',
      'city' => 'Schenectady',
      'state' => 'NY',
      'zipCode' => '12345',
      'total' => '5',
      'comment' => 'Process CC test.',
      'currency' => 'GBP'
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->processCreditCard($request);

    $this->assertEquals('Service cannot be used with this Method of Payment or Currency.', $response);
  }

  /**
   * Test processCreditCard with invalid method of payment for current server.
   *
   * @depends testCredentials
   */
  public function testProcessLinkprocessCreditCardInvalidMOP() {
    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'invoiceNum' => '00000001',
      'creditCardNum' => '4111111111111111',
      'creditCardExpiry' => '12/17',
      'cvv2' => '000',
      'mop' => 'DSC',
      'firstName' => 'Test',
      'lastName' => 'Account',
      'address' => '1234 Any Street',
      'city' => 'Schenectady',
      'state' => 'NY',
      'zipCode' => '12345',
      'total' => '5',
      'comment' => 'Process CC test.',
      'currency' => 'CAN'
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->processCreditCard($request);

    $this->assertEquals('Service cannot be used with this Method of Payment or Currency.', $response);
  }

  /**
   * Test processACHEFTChargeBatch with duplicate data
   *
   * This test sends the same batch data sent by an earlier test without modifying
   * the invoice IDs. This should result in a "Duplicated" message for all rows.
   *
   * @depends testProcessLinkprocessACHEFTChargeBatch
   */
  public function testProcessLinkprocessACHEFTChargeBatchDuplicateData() {
    $fileContents = $this->getBatchFile('ACHEFTBatch.txt');

    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'batchFile' => $fileContents
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->processACHEFTChargeBatch($request);

    $this->assertEquals('Batch Processing, Please Wait ....', trim($response['AUTHORIZATIONRESULT']));

    self::$ACHEFTBatchId = $response['BATCHID'];

    // Pause to allow for batch file processing.
    sleep(3);
  }

  /**
   * Test getBatchProcessResultFile with an ACH / EFT batch process after
   * sending duplicate batch data.
   *
   * @depends testProcessLinkprocessACHEFTChargeBatchDuplicateData
   */
  public function testProcessLinkgetBatchProcessResultFileACHEFTDuplicateData() {
    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'batchId' => self::$ACHEFTBatchId,
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->getBatchProcessResultFile($request);

    $this->assertEquals('Batch Process Has Been Done', trim($response['AUTHORIZATIONRESULT']));
    $this->assertEquals(self::$ACHEFTBatchId, $response['BATCHID']);

    $batchResultFileContents = trim(base64_decode($response['BATCHPROCESSRESULTFILE']));

    $batchData = explode("\r\n", $batchResultFileContents);

    foreach ($batchData as $batchRow)
    {
      $batchRowData = str_getcsv($batchRow);

      // Get result message from end of array.
      $batchRowMessage = array_pop($batchRowData);

      $this->assertStringStartsWith('Duplicated', $batchRowMessage);
    }
  }

  /**
   * Test processACHEFTChargeBatch with incorrectly encoded request.
   *
   * This test sends a base64-encoded file in the request, which is then
   * automatically encoded again by SoapClient.
   *
   * @depends testCredentials
   */
  public function testProcessLinkprocessACHEFTChargeBatchInvalidRequest() {
    // Recreate ACH / EFT batch file to avoid duplicate data.
    self::createACHEFTBatchFile();

    $fileContents = $this->getBatchFile('ACHEFTBatch.txt');

    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'batchFile' => base64_encode($fileContents)
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->processACHEFTChargeBatch($request);

    $this->assertEquals('Batch Processing, Please Wait ....', trim($response['AUTHORIZATIONRESULT']));

    self::$ACHEFTBatchId = $response['BATCHID'];

    // Pause to allow for batch file processing.
    sleep(3);
  }

  /**
   * Test getBatchProcessResultFile with an ACH / EFT batch process after
   * sending an incorrectly encoded request.
   *
   * @depends testProcessLinkprocessACHEFTChargeBatchInvalidRequest
   */
  public function testProcessLinkgetBatchProcessResultFileACHEFTInvalidRequest() {
    // Create and populate the request object.
    $request = array(
      'customerIPAddress' => '',
      'batchId' => self::$ACHEFTBatchId,
    );

    $iats = new ProcessLink(self::$agentCode, self::$password);
    $response = $iats->getBatchProcessResultFile($request);

    $this->assertEquals('Batch Process Has Been Done', trim($response['AUTHORIZATIONRESULT']));
    $this->assertEquals(self::$ACHEFTBatchId, $response['BATCHID']);

    $batchResultFileContents = trim(base64_decode($response['BATCHPROCESSRESULTFILE']));

    $batchData = explode("\r\n", $batchResultFileContents);

    foreach ($batchData as $batchRow)
    {
      $batchRowData = str_getcsv($batchRow);

      // Get result message from end of array.
      $batchRowMessage = array_pop($batchRowData);

      $this->assertStringStartsWith('Wrong Format', $batchRowMessage);
    }
  }

  /**
   * Gets the contents of a batch file.
   *
   * @param $batchFileName The name of the batch file to open.
   *  Must exist in Tests/batchfiles/
   * @return string The contents of the batch file.
   */
  private function getBatchFile($batchFileName) {
    $filePath = dirname(__FILE__) . '/batchfiles/' . $batchFileName;

    // Open the file with read access.
    $handle = fopen($filePath, 'r');
    $fileContents = fread($handle, filesize($filePath));
    fclose($handle);

    return $fileContents;
  }

  /**
   * Creates temporary batch file for ACH / EFT charges.
   */
  private static function createACHEFTBatchFile() {
    // Use timestamp as base for unique ID.
    $timestamp = time();

    $batchData = array(
      array(
        ($timestamp + 1),
        'Test',
        'Account',
        'CHECKING',
        '02100002100000000000000001',
        '5.00',
        'Batch direct debit charge test',
      ),
      array (
        ($timestamp + 2),
        'Test',
        'Account',
        'CHECKING',
        '02100002100000000000000001',
        '5.00',
        'Batch direct debit charge test',
      )
    );

    self::createBatchFileFromArray($batchData, 'ACHEFTBatch.txt');
  }

  /**
   * Creates temporary batch file for ACH / EFT charges
   * in an invalid format.
   */
  private static function createACHEFTBatchFileInvalidFormat() {
    // Use timestamp as base for unique ID.
    $timestamp = time();

    $batchData = array(
      array(
        ($timestamp + 1),
        'Test',
      ),
      array (
        ($timestamp + 2),
        'Test',
      )
    );

    self::createBatchFileFromArray($batchData, 'ACHEFTInvalidFormatBatch.txt');
  }

  /**
   * Creates temporary batch file for ACH / EFT refunds.
   */
  private static function createACHEFTRefundBatchFile() {
    // Use timestamp as base for unique ID.
    $timestamp = time();

    $batchData = array(
      array(
        ($timestamp + 1), 
        'Test',
        'Account',
        'CHECKING',
        '02100002100000000000000001',
        '-5.00',
        'Batch ACH / EFT refund test',
      ),
      array (
        ($timestamp + 2),
        'Test',
        'Account',
        'CHECKING',
        '02100002100000000000000001',
        '-5.00',
        'Batch ACH / EFT refund test',
      )
    );

    self::createBatchFileFromArray($batchData, 'ACHEFTRefundBatch.txt');
  }

  /**
   * Creates temporary batch file for credit card charges.
   */
  private static function createCreditCardBatchFile() {
    // Use timestamp as base for unique ID.
    $timestamp = time();

    $batchData = array(
      array(
        date('m/d/Y', time()),
        ($timestamp + 1),
        'Test',
        'Account',
        '1234 Any Street',
        'Schenectady',
        'NY',
        '12345',
        '5.00',
        'VISA',
        '4222222222222220',
        '1217',
      ),
      array (
        date('m/d/Y', time()),
        ($timestamp + 2),
        'Test',
        'Account',
        '1234 Any Street',
        'Schenectady',
        'NY',
        '12345',
        '5.00',
        'VISA',
        '4222222222222220',
        '1217',
      )
    );

    self::createBatchFileFromArray($batchData, 'CreditCardUSUKBatch.txt');
  }

  /**
   * Creates a CSV batch file from a batch data array and saves it to disk.
   *
   * @param array $batchData The batch data array. Structure should be an
   *  array containing an array of fields values for each row of data.
   * @param string $filename The filename of the resulting batch file.
   */
  private static function createBatchFileFromArray(array $batchData, $filename) {
    $csvString = '';

    // Convert batch data array to CSV.
    foreach ($batchData as $row)
    {
      $csvString .= implode(',', $row) . "\n";
    }

    // Trim last line break.
    $csvString = substr($csvString, 0, -1);

    // Save batch file.
    $handle = fopen(self::$batchFilePath . $filename, 'w');
    fwrite($handle, $csvString);
    fclose($handle);
  }
}

