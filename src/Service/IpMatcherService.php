<?php

namespace App\Service;

class IpMatcherService
{
    /**
     * Verifica si alguna de las IPs del cliente está permitida según las reglas de origen.
     * Soporta IPs individuales y notación CIDR (ej: "10.0.0.1/32,10.15.16.50,192.168.26.54/25").
     */
    public function isIpAllowed(string $clientIps, string $allowedOrigins): bool
    {
        $allowed = array_map('trim', explode(',', $allowedOrigins));
        $clients = array_map('trim', explode(',', $clientIps));

        foreach ($clients as $clientIp) {
            if (empty($clientIp)) {
                continue;
            }
            foreach ($allowed as $rule) {
                if (empty($rule)) {
                    continue;
                }
                if ($this->matchesRule($clientIp, $rule)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function matchesRule(string $ip, string $rule): bool
    {
        if (!str_contains($rule, '/')) {
            return $ip === $rule;
        }

        return $this->matchesCidr($ip, $rule);
    }

    private function matchesCidr(string $ip, string $cidr): bool
    {
        $parts = explode('/', $cidr, 2);
        $subnet = $parts[0];
        $prefix = (int) $parts[1];

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        if (strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $totalBits = strlen($ipBin) * 8;
        if ($prefix < 0 || $prefix > $totalBits) {
            return false;
        }

        $mask = str_repeat("\xff", (int) ($prefix / 8));
        $remainingBits = $prefix % 8;
        if ($remainingBits > 0) {
            $mask .= chr(0xff << (8 - $remainingBits) & 0xff);
        }
        $mask = str_pad($mask, strlen($ipBin), "\x00");

        return ($ipBin & $mask) === ($subnetBin & $mask);
    }
}
