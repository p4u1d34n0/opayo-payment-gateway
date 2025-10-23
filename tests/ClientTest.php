<?php

namespace Opayo\Tests;

use GuzzleHttp\ClientInterface as HttpClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Opayo\Client;
use Opayo\Config;
use Opayo\Exception\OpayoException;
use Opayo\Exception\OpayoNetworkException;
use Opayo\Exception\OpayoValidationException;
use Opayo\Http\HttpOptions;
use Opayo\Http\ResponseParser;
use Opayo\Http\TransactionRequestBuilder;
use Opayo\TransactionResponse;
use Opayo\Validator\TransactionValidator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Test suite for Client class with new dependencies
 */
class ClientTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Config $config;
    private LoggerInterface $logger;
    private HttpClientInterface $httpClient;
    private TransactionRequestBuilder $requestBuilder;
    private ResponseParser $responseParser;
    private TransactionValidator $validator;
    private HttpOptions $httpOptions;

    protected function setUp(): void
    {
        $this->config = Config::sandbox('TestVendor', '1234567890123456');
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->httpClient = Mockery::mock(HttpClientInterface::class);
        $this->requestBuilder = Mockery::mock(TransactionRequestBuilder::class);
        $this->responseParser = Mockery::mock(ResponseParser::class);
        $this->validator = Mockery::mock(TransactionValidator::class);
        $this->httpOptions = new HttpOptions();

        // Default logger expectations
        $this->logger->allows('info')->byDefault();
        $this->logger->allows('debug')->byDefault();
        $this->logger->allows('error')->byDefault();
    }

    private function createClient(): Client
    {
        return new Client(
            $this->config,
            $this->logger,
            $this->httpClient,
            $this->requestBuilder,
            $this->responseParser,
            $this->validator,
            $this->httpOptions
        );
    }

    public function testConstructorWithAllParameters(): void
    {
        $client = $this->createClient();

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testConstructorWithoutOptionalParameters(): void
    {
        $client = new Client(
            $this->config,
            $this->logger,
            $this->httpClient,
            $this->requestBuilder,
            $this->responseParser
        );

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testRegisterTransactionSuccess(): void
    {
        $fields = [
            'Amount' => '10.00',
            'Currency' => 'GBP',
            'Description' => 'Test transaction',
        ];

        $postFields = [
            'VPSProtocol' => '3.00',
            'TxType' => 'PAYMENT',
            'Vendor' => 'TestVendor',
            'Crypt' => '@ENCRYPTED',
        ];

        $this->validator->expects('validate')->once()->with($fields);
        $this->requestBuilder->expects('getVendorTxCode')->once()->andReturn('TX-123');
        $this->requestBuilder->expects('build')->once()->andReturn($postFields);

        $responseBody = "Status=OK&StatusDetail=Success&VPSTxId=123&SecurityKey=KEY";
        $httpResponse = new Response(200, [], $responseBody);

        $this->httpClient->expects('request')->once()->andReturn($httpResponse);

        $txResponse = new TransactionResponse([
            'Status' => 'OK',
            'StatusDetail' => 'Success',
            'VPSTxId' => '123',
            'SecurityKey' => 'KEY',
        ]);

        $this->responseParser->expects('parse')->once()->andReturn($txResponse);

        $client = $this->createClient();
        $result = $client->registerTransaction($fields);

        $this->assertInstanceOf(TransactionResponse::class, $result);
        $this->assertTrue($result->isSuccessful());
    }

    public function testRegisterTransactionValidationFailure(): void
    {
        $fields = ['Amount' => 'invalid'];

        $this->validator->expects('validate')
            ->once()
            ->andThrow(new OpayoValidationException('Validation failed'));

        $this->expectException(OpayoValidationException::class);

        $client = $this->createClient();
        $client->registerTransaction($fields);
    }

    public function testRegisterTransactionNetworkFailure(): void
    {
        $fields = ['Amount' => '10.00'];

        $this->validator->allows('validate');
        $this->requestBuilder->allows('getVendorTxCode')->andReturn('TX-123');
        $this->requestBuilder->allows('build')->andReturn([]);

        $request = new Request('POST', $this->config->endpoint);
        $exception = new ConnectException('Connection failed', $request);

        $this->httpClient->expects('request')->once()->andThrow($exception);

        $this->logger->expects('error')->once();

        $this->expectException(OpayoNetworkException::class);
        $this->expectExceptionCode(OpayoNetworkException::CONNECTION_FAILED);

        $client = $this->createClient();
        $client->registerTransaction($fields);
    }

    public function testRegisterTransactionInvalidResponse(): void
    {
        $fields = ['Amount' => '10.00'];

        $this->validator->allows('validate');
        $this->requestBuilder->allows('getVendorTxCode')->andReturn('TX-123');
        $this->requestBuilder->allows('build')->andReturn([]);

        $response = new Response(200, [], '');
        $this->httpClient->expects('request')->once()->andReturn($response);

        $this->responseParser->expects('parse')
            ->once()
            ->andThrow(new OpayoNetworkException(
                'Invalid response from Opayo: empty or malformed',
                OpayoNetworkException::INVALID_RESPONSE
            ));

        $this->expectException(OpayoNetworkException::class);
        $this->expectExceptionCode(OpayoNetworkException::INVALID_RESPONSE);

        $client = $this->createClient();
        $client->registerTransaction($fields);
    }

    public function testRegisterTransactionFailedStatus(): void
    {
        $fields = ['Amount' => '10.00'];

        $this->validator->allows('validate');
        $this->requestBuilder->allows('getVendorTxCode')->andReturn('TX-123');
        $this->requestBuilder->allows('build')->andReturn([]);

        $responseBody = "Status=REJECTED&StatusDetail=Card declined";
        $response = new Response(200, [], $responseBody);

        $this->httpClient->allows('request')->andReturn($response);

        $txResponse = new TransactionResponse([
            'Status' => 'REJECTED',
            'StatusDetail' => 'Card declined',
        ]);

        $this->responseParser->allows('parse')->andReturn($txResponse);

        $this->expectException(OpayoException::class);
        $this->expectExceptionMessage('Transaction failed: Card declined');

        $client = $this->createClient();
        $client->registerTransaction($fields);
    }

    public function testRegisterTransaction3DSecureRequired(): void
    {
        $fields = ['Amount' => '10.00'];

        $this->validator->allows('validate');
        $this->requestBuilder->allows('getVendorTxCode')->andReturn('TX-123');
        $this->requestBuilder->allows('build')->andReturn([]);

        $responseBody = "Status=3DAUTH&StatusDetail=3D required&NextURL=https://3d.test";
        $response = new Response(200, [], $responseBody);

        $this->httpClient->allows('request')->andReturn($response);

        $txResponse = new TransactionResponse([
            'Status' => '3DAUTH',
            'StatusDetail' => '3D required',
            'NextURL' => 'https://3d.test',
        ]);

        $this->responseParser->allows('parse')->andReturn($txResponse);

        $client = $this->createClient();
        $result = $client->registerTransaction($fields);

        $this->assertInstanceOf(TransactionResponse::class, $result);
        $this->assertTrue($result->requires3DSecure());
        $this->assertTrue($result->isAccepted());
    }

    public function testRegisterTransactionLogsCorrectly(): void
    {
        $fields = ['Amount' => '10.00'];

        $this->validator->allows('validate');
        $this->requestBuilder->allows('getVendorTxCode')->andReturn('TX-123');
        $this->requestBuilder->allows('build')->andReturn([]);

        $this->logger->expects('info')->times(2);
        $this->logger->expects('debug')->once();

        $responseBody = "Status=OK&StatusDetail=OK";
        $response = new Response(200, [], $responseBody);

        $this->httpClient->allows('request')->andReturn($response);

        $txResponse = new TransactionResponse(['Status' => 'OK', 'StatusDetail' => 'OK']);
        $this->responseParser->allows('parse')->andReturn($txResponse);

        $client = $this->createClient();
        $client->registerTransaction($fields);
    }

    public function testRegisterTransactionUsesHttpOptions(): void
    {
        $fields = ['Amount' => '10.00'];

        $this->validator->allows('validate');
        $this->requestBuilder->allows('getVendorTxCode')->andReturn('TX-123');
        $this->requestBuilder->allows('build')->andReturn([]);

        $this->httpClient->expects('request')
            ->once()
            ->with('POST', $this->config->endpoint, Mockery::on(function ($options) {
                return $options['timeout'] === 30
                    && $options['connect_timeout'] === 10
                    && $options['verify'] === true;
            }))
            ->andReturn(new Response(200, [], "Status=OK"));

        $txResponse = new TransactionResponse(['Status' => 'OK']);
        $this->responseParser->allows('parse')->andReturn($txResponse);

        $client = $this->createClient();
        $client->registerTransaction($fields);
    }
}
