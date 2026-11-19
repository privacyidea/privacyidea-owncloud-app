<?php

namespace Tests\Unit\Util;

use OCA\TwoFactor_privacyIDEA\Util\IpMatcher;
use PHPUnit\Framework\TestCase;

class IpMatcherTest extends TestCase
{
    /**
     * @dataProvider matchProvider
     */
    public function testMatches(bool $expected, string $client, string $list): void
    {
        $this->assertSame($expected, IpMatcher::matches($client, $list));
    }

    public function matchProvider(): array
    {
        return [
            'single address' => [true, '10.0.1.12', '10.0.1.12'],
            'other address' => [false, '10.0.1.13', '10.0.1.12'],
            'one of a list' => [true, '10.0.1.13', '10.0.1.12,10.0.1.13'],
            'spaces around the entries' => [true, '10.0.1.13', ' 10.0.1.12 , 10.0.1.13 '],
            'range, inside' => [true, '10.0.1.50', '10.0.1.12-10.0.1.113'],
            'range, first address' => [true, '10.0.1.12', '10.0.1.12-10.0.1.113'],
            'range, last address' => [true, '10.0.1.113', '10.0.1.12-10.0.1.113'],
            'range, below' => [false, '10.0.1.11', '10.0.1.12-10.0.1.113'],
            'range, above' => [false, '10.0.1.114', '10.0.1.12-10.0.1.113'],
            'range, numeric and not text order' => [true, '10.0.1.100', '10.0.1.9-10.0.1.200'],
            'range with spaces' => [true, '10.0.1.50', '10.0.1.12 - 10.0.1.113'],
            'range and address' => [true, '192.168.0.15', '10.0.1.12-10.0.1.113,192.168.0.15'],
            'empty list' => [false, '10.0.1.12', ''],
            'only separators' => [false, '10.0.1.12', ', ,'],
            'invalid entry is ignored' => [false, '10.0.1.12', 'not-an-ip,10.0.1.99'],
            'invalid entry does not hide a valid one' => [true, '10.0.1.99', 'not-an-ip,10.0.1.99'],
            'range with an invalid end never matches' => [false, '10.0.1.50', '10.0.1.12-x'],
            'range from IPv4 to IPv6 never matches' => [false, '10.0.1.50', '10.0.1.12-::ffff:ffff'],
            'range with a reversed order never matches' => [false, '10.0.1.50', '10.0.1.113-10.0.1.12'],
            'IPv6 address' => [true, '2001:db8::1', '2001:db8::1'],
            'IPv6 address, other spelling' => [true, '2001:0db8:0:0:0:0:0:1', '2001:db8::1'],
            'IPv6 address, other' => [false, '2001:db8::2', '2001:db8::1'],
            'IPv6 range' => [true, '2001:db8::50', '2001:db8::1-2001:db8::ff'],
            'IPv6 range, above' => [false, '2001:db8::100', '2001:db8::1-2001:db8::ff'],
            'IPv4 client and IPv6 range' => [false, '10.0.1.12', '::-ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff'],
            'IPv6 client and IPv4 range' => [false, '2001:db8::1', '0.0.0.0-255.255.255.255'],
            'IPv4-mapped IPv6 client and IPv4 entry' => [true, '::ffff:10.0.1.12', '10.0.1.12'],
            'IPv4-mapped IPv6 client and IPv4 range' => [true, '::ffff:10.0.1.50', '10.0.1.12-10.0.1.113'],
            'a header with a list of addresses is not an address' => [false, '10.0.1.12, 10.9.9.9', '10.0.1.12,10.9.9.9'],
            'empty client' => [false, '', '10.0.1.12'],
            'client with text' => [false, 'localhost', 'localhost'],
            'leading zeros are not accepted' => [false, '010.0.1.12', '10.0.1.12'],
        ];
    }
}
