<?php

/**
 * @license AGPL-3.0
 *
 * This code is a free software: You can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 */

namespace OCA\TwoFactor_privacyIDEA\Util;

/**
 * Matches a client IP address against the list of excluded addresses from the admin settings.
 * The list is comma separated. An entry is a single address or a range "first-last".
 * IPv4 and IPv6 are supported. The code must run on PHP 7.4.
 */
class IpMatcher
{
    /**
     * @param string $clientIp The address of the client as ownCloud sees it.
     * @param string $list Comma separated addresses and ranges, for example "10.0.1.12,10.0.1.20-10.0.1.30".
     * @return bool True if the client address is in the list. Invalid entries never match.
     */
    public static function matches(string $clientIp, string $list): bool
    {
        $client = self::toBinary($clientIp);
        if ($client === null)
        {
            return false;
        }

        foreach (explode(',', $list) as $entry)
        {
            $entry = trim($entry);
            if ($entry === '')
            {
                continue;
            }

            if (strpos($entry, '-') !== false)
            {
                $parts = explode('-', $entry, 2);
                $first = self::toBinary(trim($parts[0]));
                $last = self::toBinary(trim($parts[1]));
                // Both ends must be valid and of the same IP version.
                if ($first === null || $last === null || strlen($first) !== strlen($last))
                {
                    continue;
                }
                if (strlen($client) === strlen($first) && strcmp($client, $first) >= 0 && strcmp($client, $last) <= 0)
                {
                    return true;
                }
            }
            else
            {
                $single = self::toBinary($entry);
                if ($single !== null && $single === $client)
                {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Convert an address to its binary form (4 bytes for IPv4, 16 bytes for IPv6).
     * An IPv4-mapped IPv6 address (::ffff:a.b.c.d) is converted to IPv4.
     * Binary strings of the same length compare in numeric order with strcmp().
     *
     * @param string $ip
     * @return string|null Null if the string is not an IP address.
     */
    private static function toBinary(string $ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false)
        {
            return null;
        }
        $binary = inet_pton($ip);
        if ($binary === false)
        {
            return null;
        }
        if (strlen($binary) === 16 && strncmp($binary, str_repeat("\0", 10) . "\xff\xff", 12) === 0)
        {
            return substr($binary, 12);
        }
        return $binary;
    }
}
