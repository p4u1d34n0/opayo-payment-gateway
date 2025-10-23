<?php

namespace Opayo;

use GuzzleHttp\ClientInterface as HttpClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Opayo\Crypto\CryptoInterface;
use Opayo\Exception\OpayoException;
use Opayo\Exception\OpayoNetworkException;
use Opayo\Exception\OpayoValidationException;
use Opayo\Http\HttpOptions;
use Opayo\Http\ResponseParser;
use Opayo\Http\TransactionRequestBuilder;
use Opayo\Validator\TransactionValidator;
use Psr\Log\LoggerInterface;

/**
 * Opayo API Client
 *
 * Handles transaction registration with Opayo payment gateway
 */
class Client
{
    private Config $config;
    private LoggerInterface $logger;
    private HttpClientInterface $httpClient;
    private TransactionRequestBuilder $requestBuilder;
    private ResponseParser $responseParser;
    private TransactionValidator $validator;
    private HttpOptions $httpOptions;

    /**
     * @param Config $config
     * @param LoggerInterface $logger
     * @param HttpClientInterface $httpClient
     * @param TransactionRequestBuilder $requestBuilder
     * @param ResponseParser $responseParser
     * @param TransactionValidator|null $validator
     * @param HttpOptions|null $httpOptions
     */
    public function __construct(
        Config $config,
        LoggerInterface $logger,
        HttpClientInterface $httpClient,
        TransactionRequestBuilder $requestBuilder,
        ResponseParser $responseParser,
        ?TransactionValidator $validator = null,
        ?HttpOptions $httpOptions = null
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->httpClient = $httpClient;
        $this->requestBuilder = $requestBuilder;
        $this->responseParser = $responseParser;
        $this->validator = $validator ?? new TransactionValidator();
        $this->httpOptions = $httpOptions ?? new HttpOptions();
    }

    /**
     * Register a transaction with Opayo
     *
     * @param array<string, mixed> $fields Transaction fields
     * @return TransactionResponse
     * @throws OpayoValidationException
     * @throws OpayoNetworkException
     * @throws OpayoException
     */
    public function registerTransaction(array $fields): TransactionResponse
    {
        // Validate transaction data
        $this->validator->validate($fields);

        // Log transaction start
        $this->logTransactionStart($fields);

        // Build request payload
        $postFields = $this->requestBuilder->build($fields);

        // Send request and get response body
        $body = $this->sendRequest($postFields);

        // Parse response
        $response = $this->responseParser->parse($body);

        // Log response
        $this->logTransactionResponse($response);

        // Check if transaction was accepted
        if (!$response->isAccepted()) {
            throw new OpayoException(
                'Transaction failed: ' . $response->getStatusDetail(),
                0,
                null,
                ['response' => $response->toArray()]
            );
        }

        return $response;
    }

    /**
     * Log transaction start
     *
     * @param array<string, mixed> $fields
     * @return void
     */
    private function logTransactionStart(array $fields): void
    {
        $this->logger->info('Registering Opayo transaction', [
            'vendor_tx_code' => $this->requestBuilder->getVendorTxCode($fields),
            'amount' => $fields['Amount'] ?? null,
        ]);
    }

    /**
     * Send HTTP request to Opayo
     *
     * @param array<string, string> $postFields
     * @return string Response body
     * @throws OpayoNetworkException
     */
    private function sendRequest(array $postFields): string
    {
        try {
            $options = array_merge(
                ['form_params' => $postFields],
                $this->httpOptions->toArray()
            );

            $response = $this->httpClient->request('POST', $this->config->endpoint, $options);
            $body = (string)$response->getBody();

            $this->logger->debug('Opayo raw response', ['body' => $body]);

            return $body;
        } catch (GuzzleException $e) {
            $this->logger->error('HTTP request failed', [
                'error' => $e->getMessage(),
                'endpoint' => $this->config->endpoint,
            ]);

            throw new OpayoNetworkException(
                'Failed to connect to Opayo: ' . $e->getMessage(),
                OpayoNetworkException::CONNECTION_FAILED,
                $e
            );
        }
    }

    /**
     * Log transaction response
     *
     * @param TransactionResponse $response
     * @return void
     */
    private function logTransactionResponse(TransactionResponse $response): void
    {
        $this->logger->info('Opayo registration response', [
            'status' => $response->getStatus(),
            'status_detail' => $response->getStatusDetail(),
            'vps_tx_id' => $response->getVPSTxId(),
        ]);
    }
}
