<?php

namespace App\Service;

use Exception;
use RuntimeException;
use Symfony\Component\HttpClient\HttpClient;

class DnsService
{
    public function getMtaSts($domain): string|bool
    {
        try {
            $result = $this->checkMtaSts($domain);
        } catch (Exception $e) {
            return false;
        }
        if ($result) {
            $mtasts = "version: STSv1\n"
                . "mode: enforce\n";

            $mx = $this->queryDnsMx($domain);
            foreach ($mx as $record) {
                $mtasts .= "mx: " . $record . "\n";
            }

            // end with max_age - 4 weeks
            // https://support.google.com/a/answer/9276511
            $mtasts .= "max_age: 2419200\n";

            return $mtasts;
        }
        return false;
    }

    public function checkMtaSts(string $domain): bool
    {
        $results = [
            'smtp-tls' => false,
            'mta-sts' => false,
        ];

        $txt = $this->queryDns('_smtp._tls.' . $domain, 'TXT');
        foreach ($txt as $record) {
            if (strpos($record['data'], 'v=TLSRPTv1') !== false) {
                $results['smtp-tls'] = true;
                break;
            }
        }

        $txt = $this->queryDns('_mta-sts.' . $domain, 'TXT');
        foreach ($txt as $record) {
            if (strpos($record['data'], 'v=STSv1') !== false) {
                $results['mta-sts'] = true;
                break;
            }
        }

        return $results['smtp-tls'] && $results['mta-sts'];
    }

    public function queryDns(string $domain, string $recordType): array
    {
        if (!in_array($recordType, ['A', 'AAAA', 'MX', 'CNAME', 'TXT', 'NS', 'SOA', 'SRV', 'PTR', 'DNSKEY', 'DS', 'NSEC', 'RRSIG', 'NSEC3', 'NSEC3PARAM', 'TLSA', 'SMIMEA', 'CAA', 'URI', 'SSHFP', 'OPENPGPKEY', 'CDS', 'CDNSKEY', 'CSYNC', 'DS', 'DLV'])) {
            throw new RuntimeException('Invalid record type');
        }

        $url = 'https://cloudflare-dns.com/dns-query?name=' . $domain . '&type=' . $recordType;
        $options = [
            'headers' => [
                'Accept' => 'application/dns-json',
            ],
        ];

        $client = HttpClient::create($options);
        $response = $client->request('GET', $url);
        $statusCode = $response->getStatusCode();
        if ($statusCode != 200) {
            throw new RuntimeException('Failed to query DNS');
        }
        $content = $response->getContent();
        $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (!isset($json['Answer'])) {
            throw new RuntimeException('No records found for ' . $recordType . ' in ' . $domain);
        }
        return $json['Answer'];
    }

    public function queryDnsMx(string $domain): array
    {
        $json = $this->queryDns($domain, 'MX');
        $result = [];
        foreach ($json as $record) {
            // Extract priority and domain name from the MX record
            if (preg_match('/^([0-9]+) (.*)$/', $record['data'], $matches)) {
                $result[] = $matches[2];
            } else {
                // No priority, just domain name
                $result[] = rtrim($record['data'], '.'); // Remove trailing dot
            }
        }
        return $result;
    }
}
