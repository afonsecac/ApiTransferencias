<?php

namespace App\Tests\Service;

use App\Service\IpMatcherService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\IpMatcherService
 */
class IpMatcherServiceTest extends TestCase
{
    private IpMatcherService $service;

    protected function setUp(): void
    {
        $this->service = new IpMatcherService();
    }

    public function testExactIpMatch(): void
    {
        $this->assertTrue($this->service->isIpAllowed('10.0.0.1', '10.0.0.1'));
    }

    public function testExactIpNoMatch(): void
    {
        $this->assertFalse($this->service->isIpAllowed('10.0.0.2', '10.0.0.1'));
    }

    public function testMultipleAllowedExactMatch(): void
    {
        $this->assertTrue($this->service->isIpAllowed('192.168.1.5', '10.0.0.1,192.168.1.5,172.16.0.1'));
    }

    public function testMultipleClientIpsOneMatches(): void
    {
        $this->assertTrue($this->service->isIpAllowed('10.0.0.5,192.168.1.1', '192.168.1.1'));
    }

    public function testMultipleClientIpsNoneMatch(): void
    {
        $this->assertFalse($this->service->isIpAllowed('10.0.0.5,172.16.0.1', '192.168.1.1'));
    }

    public function testCidr32ExactMatch(): void
    {
        $this->assertTrue($this->service->isIpAllowed('10.0.0.1', '10.0.0.1/32'));
    }

    public function testCidr32NoMatch(): void
    {
        $this->assertFalse($this->service->isIpAllowed('10.0.0.2', '10.0.0.1/32'));
    }

    public function testCidr24Match(): void
    {
        $this->assertTrue($this->service->isIpAllowed('192.168.1.50', '192.168.1.0/24'));
    }

    public function testCidr24NoMatch(): void
    {
        $this->assertFalse($this->service->isIpAllowed('192.168.2.1', '192.168.1.0/24'));
    }

    public function testCidr25Match(): void
    {
        // /25 = 192.168.26.0 - 192.168.26.127
        $this->assertTrue($this->service->isIpAllowed('192.168.26.50', '192.168.26.0/25'));
    }

    public function testCidr25NoMatch(): void
    {
        // /25 = 192.168.26.0 - 192.168.26.127, so .200 is out
        $this->assertFalse($this->service->isIpAllowed('192.168.26.200', '192.168.26.0/25'));
    }

    public function testCidr16Match(): void
    {
        $this->assertTrue($this->service->isIpAllowed('10.15.200.1', '10.15.0.0/16'));
    }

    public function testCidr16NoMatch(): void
    {
        $this->assertFalse($this->service->isIpAllowed('10.16.0.1', '10.15.0.0/16'));
    }

    public function testMixedRulesExactAndCidr(): void
    {
        $origin = '10.0.0.1/32,10.15.16.50,192.168.26.0/25';

        // Exact match
        $this->assertTrue($this->service->isIpAllowed('10.15.16.50', $origin));
        // CIDR /32 match
        $this->assertTrue($this->service->isIpAllowed('10.0.0.1', $origin));
        // CIDR /25 match (in range 192.168.26.0-127)
        $this->assertTrue($this->service->isIpAllowed('192.168.26.50', $origin));
        // Out of all ranges
        $this->assertFalse($this->service->isIpAllowed('192.168.26.200', $origin));
        // Completely different IP
        $this->assertFalse($this->service->isIpAllowed('8.8.8.8', $origin));
    }

    public function testWhitespaceHandling(): void
    {
        $this->assertTrue($this->service->isIpAllowed(' 10.0.0.1 ', ' 10.0.0.1 '));
        $this->assertTrue($this->service->isIpAllowed('10.0.0.1', '192.168.1.0/24 , 10.0.0.1'));
    }

    public function testEmptyClientIp(): void
    {
        $this->assertFalse($this->service->isIpAllowed('', '10.0.0.1'));
    }

    public function testEmptyAllowedOrigins(): void
    {
        $this->assertFalse($this->service->isIpAllowed('10.0.0.1', ''));
    }

    public function testInvalidCidrReturnsFalse(): void
    {
        $this->assertFalse($this->service->isIpAllowed('10.0.0.1', 'invalid/24'));
    }

    public function testInvalidPrefixLengthReturnsFalse(): void
    {
        $this->assertFalse($this->service->isIpAllowed('10.0.0.1', '10.0.0.0/33'));
    }

    public function testIpv6ExactMatch(): void
    {
        $this->assertTrue($this->service->isIpAllowed('::1', '::1'));
    }

    public function testIpv6CidrMatch(): void
    {
        $this->assertTrue($this->service->isIpAllowed('2001:db8::1', '2001:db8::/32'));
    }

    public function testIpv6CidrNoMatch(): void
    {
        $this->assertFalse($this->service->isIpAllowed('2001:db9::1', '2001:db8::/32'));
    }

    public function testIpv4AndIpv6MixedRules(): void
    {
        $origin = '192.168.1.0/24,2001:db8::/32';
        $this->assertTrue($this->service->isIpAllowed('192.168.1.100', $origin));
        $this->assertTrue($this->service->isIpAllowed('2001:db8::ff', $origin));
        $this->assertFalse($this->service->isIpAllowed('10.0.0.1', $origin));
    }
}
