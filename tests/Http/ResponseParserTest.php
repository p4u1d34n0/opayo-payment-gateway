<?php

namespace Opayo\Tests\Http;

use Opayo\Exception\OpayoNetworkException;
use Opayo\Http\ResponseParser;
use Opayo\TransactionResponse;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for ResponseParser class
 */
class ResponseParserTest extends TestCase
{
    private ResponseParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ResponseParser();
    }

    public function testParseValidResponse(): void
    {
        $body = 'Status=OK&StatusDetail=Success&VPSTxId=12345&SecurityKey=ABCDEF';

        $response = $this->parser->parse($body);

        $this->assertInstanceOf(TransactionResponse::class, $response);
        $this->assertSame('OK', $response->getStatus());
        $this->assertSame('Success', $response->getStatusDetail());
        $this->assertSame('12345', $response->getVPSTxId());
        $this->assertSame('ABCDEF', $response->getSecurityKey());
    }

    public function testParseResponseWithMultipleFields(): void
    {
        $body = 'Status=OK&VPSTxId=TX123&StatusDetail=Transaction approved&VendorTxCode=MYORDER001&SecurityKey=KEY123&NextURL=https://example.com/3d';

        $response = $this->parser->parse($body);

        $this->assertSame('OK', $response->getStatus());
        $this->assertSame('TX123', $response->getVPSTxId());
        $this->assertSame('Transaction approved', $response->getStatusDetail());
        $this->assertSame('https://example.com/3d', $response->getNextURL());
    }

    public function testParseResponseWith3DAuth(): void
    {
        $body = 'Status=3DAUTH&StatusDetail=3D Secure authentication required&NextURL=https://3dsecure.example.com';

        $response = $this->parser->parse($body);

        $this->assertSame('3DAUTH', $response->getStatus());
        $this->assertTrue($response->requires3DSecure());
    }

    public function testParseResponseWithRejectedStatus(): void
    {
        $body = 'Status=REJECTED&StatusDetail=Card declined';

        $response = $this->parser->parse($body);

        $this->assertSame('REJECTED', $response->getStatus());
        $this->assertSame('Card declined', $response->getStatusDetail());
        $this->assertTrue($response->isFailed());
    }

    public function testParseEmptyResponseThrowsException(): void
    {
        $this->expectException(OpayoNetworkException::class);
        $this->expectExceptionCode(OpayoNetworkException::INVALID_RESPONSE);
        $this->expectExceptionMessage('Invalid response from Opayo: empty or malformed');

        $this->parser->parse('');
    }

    public function testParseResponseWithoutStatusThrowsException(): void
    {
        $body = 'VPSTxId=12345&SecurityKey=ABCDEF';

        $this->expectException(OpayoNetworkException::class);
        $this->expectExceptionCode(OpayoNetworkException::INVALID_RESPONSE);
        $this->expectExceptionMessage('Invalid response from Opayo: missing Status field');

        $this->parser->parse($body);
    }

    public function testParseResponseWithEncodedCharacters(): void
    {
        $body = 'Status=OK&StatusDetail=Transaction+approved+successfully&VPSTxId=TX123';

        $response = $this->parser->parse($body);

        $this->assertSame('Transaction approved successfully', $response->getStatusDetail());
    }

    public function testParseResponseWithSpecialCharacters(): void
    {
        $body = 'Status=OK&StatusDetail=Test%20%26%20Special%20%3Cchars%3E&VPSTxId=TX123';

        $response = $this->parser->parse($body);

        $this->assertSame('Test & Special <chars>', $response->getStatusDetail());
    }

    public function testParseResponsePreservesAllFields(): void
    {
        $body = 'Status=OK&Field1=Value1&Field2=Value2&Field3=Value3';

        $response = $this->parser->parse($body);

        $data = $response->toArray();

        $this->assertArrayHasKey('Status', $data);
        $this->assertArrayHasKey('Field1', $data);
        $this->assertArrayHasKey('Field2', $data);
        $this->assertArrayHasKey('Field3', $data);
        $this->assertSame('Value1', $data['Field1']);
        $this->assertSame('Value2', $data['Field2']);
        $this->assertSame('Value3', $data['Field3']);
    }

    public function testParseMalformedQueryStringThrowsException(): void
    {
        $body = 'StatusVPSTxId12345';

        $this->expectException(OpayoNetworkException::class);
        $this->expectExceptionCode(OpayoNetworkException::INVALID_RESPONSE);

        $this->parser->parse($body);
    }

    public function testParseResponseWithError(): void
    {
        $body = 'Status=ERROR&StatusDetail=System error occurred';

        $response = $this->parser->parse($body);

        $this->assertSame('ERROR', $response->getStatus());
        $this->assertTrue($response->isFailed());
    }

    public function testParseResponseWithInvalidStatus(): void
    {
        $body = 'Status=INVALID&StatusDetail=Invalid transaction';

        $response = $this->parser->parse($body);

        $this->assertSame('INVALID', $response->getStatus());
        $this->assertTrue($response->isFailed());
    }
}
