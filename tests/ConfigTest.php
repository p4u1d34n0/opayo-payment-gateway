<?php

namespace Opayo\Tests;

use Opayo\Config;
use Opayo\Exception\OpayoConfigException;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for Config class
 */
class ConfigTest extends TestCase
{
    public function testConstructorWithAllParameters(): void
    {
        $config = new Config('TestVendor', 'test-password-16b', Config::ENDPOINT_TEST);

        $this->assertSame('TestVendor', $config->vendor);
        $this->assertSame('test-password-16b', $config->encryptionPassword);
        $this->assertSame(Config::ENDPOINT_TEST, $config->endpoint);
    }

    public function testConstructorWithLiveEndpoint(): void
    {
        $config = new Config('LiveVendor', 'live-password-16b', Config::ENDPOINT_LIVE);

        $this->assertSame('LiveVendor', $config->vendor);
        $this->assertSame('live-password-16b', $config->encryptionPassword);
        $this->assertSame(Config::ENDPOINT_LIVE, $config->endpoint);
    }

    public function testConstructorValidatesEmptyVendor(): void
    {
        $this->expectException(OpayoConfigException::class);
        $this->expectExceptionMessage('Vendor name is required');
        $this->expectExceptionCode(OpayoConfigException::MISSING_VENDOR);

        new Config('', 'test-password-16b', Config::ENDPOINT_TEST);
    }

    public function testConstructorValidatesEmptyPassword(): void
    {
        $this->expectException(OpayoConfigException::class);
        $this->expectExceptionMessage('Encryption password is required');
        $this->expectExceptionCode(OpayoConfigException::MISSING_PASSWORD);

        new Config('TestVendor', '', Config::ENDPOINT_TEST);
    }

    public function testConstructorValidatesEmptyEndpoint(): void
    {
        $this->expectException(OpayoConfigException::class);
        $this->expectExceptionMessage('Endpoint URL is required');
        $this->expectExceptionCode(OpayoConfigException::MISSING_ENDPOINT);

        new Config('TestVendor', 'test-password-16b', '');
    }

    public function testFromArrayWithValidData(): void
    {
        $data = [
            'vendor' => 'ArrayVendor',
            'encryption_password' => 'array-password16',
            'endpoint' => Config::ENDPOINT_TEST,
        ];

        $config = Config::fromArray($data);

        $this->assertSame('ArrayVendor', $config->vendor);
        $this->assertSame('array-password16', $config->encryptionPassword);
        $this->assertSame(Config::ENDPOINT_TEST, $config->endpoint);
    }

    public function testFromArrayWithMissingVendor(): void
    {
        $this->expectException(OpayoConfigException::class);
        $this->expectExceptionCode(OpayoConfigException::MISSING_VENDOR);

        Config::fromArray([
            'encryption_password' => 'test-password-16b',
            'endpoint' => Config::ENDPOINT_TEST,
        ]);
    }

    public function testFromArrayWithMissingPassword(): void
    {
        $this->expectException(OpayoConfigException::class);
        $this->expectExceptionCode(OpayoConfigException::MISSING_PASSWORD);

        Config::fromArray([
            'vendor' => 'TestVendor',
            'endpoint' => Config::ENDPOINT_TEST,
        ]);
    }

    public function testFromArrayWithMissingEndpoint(): void
    {
        $this->expectException(OpayoConfigException::class);
        $this->expectExceptionCode(OpayoConfigException::MISSING_ENDPOINT);

        Config::fromArray([
            'vendor' => 'TestVendor',
            'encryption_password' => 'test-password-16b',
        ]);
    }

    public function testFromEnvironmentWithSandboxEnvironment(): void
    {
        $_ENV['OPAYO_VENDOR'] = 'EnvVendor';
        $_ENV['OPAYO_ENCRYPTION_PASSWORD'] = 'env-password-16b';
        $_ENV['OPAYO_ENVIRONMENT'] = 'sandbox';

        $config = Config::fromEnvironment();

        $this->assertSame('EnvVendor', $config->vendor);
        $this->assertSame('env-password-16b', $config->encryptionPassword);
        $this->assertSame(Config::ENDPOINT_TEST, $config->endpoint);

        unset($_ENV['OPAYO_VENDOR'], $_ENV['OPAYO_ENCRYPTION_PASSWORD'], $_ENV['OPAYO_ENVIRONMENT']);
    }

    public function testFromEnvironmentWithLiveEnvironment(): void
    {
        $_ENV['OPAYO_VENDOR'] = 'EnvVendor';
        $_ENV['OPAYO_ENCRYPTION_PASSWORD'] = 'env-password-16b';
        $_ENV['OPAYO_ENVIRONMENT'] = 'live';

        $config = Config::fromEnvironment();

        $this->assertSame(Config::ENDPOINT_LIVE, $config->endpoint);

        unset($_ENV['OPAYO_VENDOR'], $_ENV['OPAYO_ENCRYPTION_PASSWORD'], $_ENV['OPAYO_ENVIRONMENT']);
    }

    public function testFromEnvironmentWithProductionEnvironment(): void
    {
        $_ENV['OPAYO_VENDOR'] = 'EnvVendor';
        $_ENV['OPAYO_ENCRYPTION_PASSWORD'] = 'env-password-16b';
        $_ENV['OPAYO_ENVIRONMENT'] = 'production';

        $config = Config::fromEnvironment();

        $this->assertSame(Config::ENDPOINT_LIVE, $config->endpoint);

        unset($_ENV['OPAYO_VENDOR'], $_ENV['OPAYO_ENCRYPTION_PASSWORD'], $_ENV['OPAYO_ENVIRONMENT']);
    }

    public function testFromEnvironmentWithTestEnvironment(): void
    {
        $_ENV['OPAYO_VENDOR'] = 'EnvVendor';
        $_ENV['OPAYO_ENCRYPTION_PASSWORD'] = 'env-password-16b';
        $_ENV['OPAYO_ENVIRONMENT'] = 'test';

        $config = Config::fromEnvironment();

        $this->assertSame(Config::ENDPOINT_TEST, $config->endpoint);

        unset($_ENV['OPAYO_VENDOR'], $_ENV['OPAYO_ENCRYPTION_PASSWORD'], $_ENV['OPAYO_ENVIRONMENT']);
    }

    public function testFromEnvironmentWithInvalidEnvironment(): void
    {
        $_ENV['OPAYO_VENDOR'] = 'EnvVendor';
        $_ENV['OPAYO_ENCRYPTION_PASSWORD'] = 'env-password-16b';
        $_ENV['OPAYO_ENVIRONMENT'] = 'invalid';

        $this->expectException(OpayoConfigException::class);
        $this->expectExceptionCode(OpayoConfigException::INVALID_ENVIRONMENT);
        $this->expectExceptionMessage("Invalid environment: invalid. Must be 'sandbox' or 'live'");

        Config::fromEnvironment();

        unset($_ENV['OPAYO_VENDOR'], $_ENV['OPAYO_ENCRYPTION_PASSWORD'], $_ENV['OPAYO_ENVIRONMENT']);
    }

    public function testFromEnvironmentDefaultsToSandbox(): void
    {
        $_ENV['OPAYO_VENDOR'] = 'EnvVendor';
        $_ENV['OPAYO_ENCRYPTION_PASSWORD'] = 'env-password-16b';
        unset($_ENV['OPAYO_ENVIRONMENT']); // Make sure it's not set

        $config = Config::fromEnvironment();

        $this->assertSame(Config::ENDPOINT_TEST, $config->endpoint);

        unset($_ENV['OPAYO_VENDOR'], $_ENV['OPAYO_ENCRYPTION_PASSWORD']);
    }

    public function testSandboxFactoryMethod(): void
    {
        $config = Config::sandbox('SandboxVendor', 'sandbox-pass-16b');

        $this->assertSame('SandboxVendor', $config->vendor);
        $this->assertSame('sandbox-pass-16b', $config->encryptionPassword);
        $this->assertSame(Config::ENDPOINT_TEST, $config->endpoint);
        $this->assertTrue($config->isSandbox());
        $this->assertFalse($config->isLive());
    }

    public function testLiveFactoryMethod(): void
    {
        $config = Config::live('LiveVendor', 'live-password-16');

        $this->assertSame('LiveVendor', $config->vendor);
        $this->assertSame('live-password-16', $config->encryptionPassword);
        $this->assertSame(Config::ENDPOINT_LIVE, $config->endpoint);
        $this->assertTrue($config->isLive());
        $this->assertFalse($config->isSandbox());
    }

    public function testIsSandboxReturnsTrueForTestEndpoint(): void
    {
        $config = new Config('TestVendor', 'test-password-16b', Config::ENDPOINT_TEST);

        $this->assertTrue($config->isSandbox());
    }

    public function testIsSandboxReturnsFalseForLiveEndpoint(): void
    {
        $config = new Config('TestVendor', 'test-password-16b', Config::ENDPOINT_LIVE);

        $this->assertFalse($config->isSandbox());
    }

    public function testIsLiveReturnsTrueForLiveEndpoint(): void
    {
        $config = new Config('TestVendor', 'test-password-16b', Config::ENDPOINT_LIVE);

        $this->assertTrue($config->isLive());
    }

    public function testIsLiveReturnsFalseForTestEndpoint(): void
    {
        $config = new Config('TestVendor', 'test-password-16b', Config::ENDPOINT_TEST);

        $this->assertFalse($config->isLive());
    }

    public function testEndpointConstants(): void
    {
        $this->assertSame(
            'https://test.sagepay.com/gateway/service/vspserver-register.vsp',
            Config::ENDPOINT_TEST
        );
        $this->assertSame(
            'https://live.sagepay.com/gateway/service/vspserver-register.vsp',
            Config::ENDPOINT_LIVE
        );
    }

    public function testReadonlyProperties(): void
    {
        $config = new Config('TestVendor', 'test-password-16b', Config::ENDPOINT_TEST);

        $this->assertSame('TestVendor', $config->vendor);
        $this->assertSame('test-password-16b', $config->encryptionPassword);
        $this->assertSame(Config::ENDPOINT_TEST, $config->endpoint);
    }
}
