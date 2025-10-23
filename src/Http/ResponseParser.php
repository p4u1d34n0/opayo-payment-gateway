<?php

namespace Opayo\Http;

use Opayo\Exception\OpayoNetworkException;
use Opayo\TransactionResponse;

/**
 * Parses Opayo API responses
 *
 * Responsible for converting raw API responses into TransactionResponse objects
 */
class ResponseParser
{
    /**
     * Parse raw response body into TransactionResponse
     *
     * @param string $body Raw response body
     * @return TransactionResponse
     * @throws OpayoNetworkException
     */
    public function parse(string $body): TransactionResponse
    {
        $data = $this->parseQueryString($body);
        $this->validateResponse($data);

        return new TransactionResponse($data);
    }

    /**
     * Parse query string format response
     *
     * @param string $body
     * @return array<string, string>
     */
    private function parseQueryString(string $body): array
    {
        $result = [];
        parse_str($body, $result);

        return $result;
    }

    /**
     * Validate response has required structure
     *
     * @param array<string, string> $data
     * @return void
     * @throws OpayoNetworkException
     */
    private function validateResponse(array $data): void
    {
        if (empty($data)) {
            throw new OpayoNetworkException(
                'Invalid response from Opayo: empty or malformed',
                OpayoNetworkException::INVALID_RESPONSE
            );
        }

        if (!isset($data['Status'])) {
            throw new OpayoNetworkException(
                'Invalid response from Opayo: missing Status field',
                OpayoNetworkException::INVALID_RESPONSE
            );
        }
    }
}
