<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MtnHostIfService
{
    private string $baseUrl;
    private ?string $username;
    private ?string $password;
    private ?string $origMsisdn;
    private int $timeout;
    private bool $verifySsl;
    private string $destMsisdnFormat;
    private ?string $xmlNamespace;

    private const RESULT_CODES = [
        0 => 'Successful',
        3 => 'Server Error',
        106 => 'Sequence Number Check Failed',
        201 => 'Invalid Originator MSISDN',
        202 => 'Invalid Destination MSISDN',
        301 => 'Insufficient Airtime',
        302 => 'Invalid Airtime Amount',
        306 => 'Product Out Of Stock',
        401 => 'Maximum Transaction Limit Exceeded',
        540 => 'Product Not Available',
        1001 => 'Topup Failed',
        1002 => 'No Connection To AIRCS3',
        1003 => 'Subscriber Barred',
        1004 => 'Invalid MSISDN',
        1007 => 'Temporary Invalid MSISDN',
        1008 => 'Invalid Transaction',
        1070 => 'Authentication Error',
    ];

    public function __construct()
    {
        $this->baseUrl = (string) config('services.mtn_hostif.base_url');
        $this->username = config('services.mtn_hostif.username');
        $this->password = config('services.mtn_hostif.password');
        $this->origMsisdn = config('services.mtn_hostif.orig_msisdn');
        $this->timeout = (int) config('services.mtn_hostif.timeout', 30);
        $this->verifySsl = (bool) config('services.mtn_hostif.verify_ssl', true);
        $this->destMsisdnFormat = (string) config('services.mtn_hostif.dest_msisdn_format', 'international');
        $this->xmlNamespace = config('services.mtn_hostif.xml_namespace');

        Log::info('MtnHostIfService Initialized', [
            'base_url_set' => $this->baseUrl !== '',
            'username_set' => !empty($this->username),
            'orig_msisdn_set' => !empty($this->origMsisdn),
            'verify_ssl' => $this->verifySsl,
        ]);
    }

    public function purchaseAirtime(string $phone, float $amount, string $network): array
    {
        if (strtolower($network) !== 'mtn') {
            return [
                'success' => false,
                'message' => 'MTN HostIF only supports MTN airtime transactions',
            ];
        }

        return $this->vend($phone, $amount, 1);
    }

    public function purchaseData(string $phone, float $amount, string $network, string $tariffTypeId): array
    {
        if (strtolower($network) !== 'mtn') {
            return [
                'success' => false,
                'message' => 'MTN HostIF only supports MTN data transactions',
            ];
        }

        if (!is_numeric($tariffTypeId)) {
            return [
                'success' => false,
                'message' => 'MTN data tariffTypeId must be numeric',
            ];
        }

        return $this->vend($phone, $amount, (int) $tariffTypeId);
    }

    public function getBalance(): array
    {
        return [
            'success' => false,
            'message' => 'MTN HostIF balance check is not available from the provided API spec',
            'balance' => 0,
        ];
    }

    private function vend(string $phone, float $amount, int $tariffTypeId): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'MTN HostIF is not configured',
            ];
        }

        $sequence = $this->generateSequence();
        $payload = $this->buildVendEnvelope($phone, $amount, $sequence, $tariffTypeId);

        try {
            Log::info('MTN HostIF Vend Request', [
                'sequence' => $sequence,
                'amount' => $amount,
                'tariff_type_id' => $tariffTypeId,
                'dest_msisdn' => $this->formatPhone($phone),
            ]);

            $response = Http::withBasicAuth((string) $this->username, (string) $this->password)
                ->withHeaders([
                    'Content-Type' => 'application/xml; charset=UTF-8',
                    'SOAPAction' => 'urn:Vend',
                ])
                ->timeout($this->timeout)
                ->withOptions(['verify' => $this->verifySsl])
                ->send('POST', $this->baseUrl, ['body' => $payload]);

            $parsed = $this->parseXmlResponse($response->body());
            $responseCode = (int) ($parsed['responseCode'] ?? -1);
            $statusId = (int) ($parsed['statusId'] ?? -1);
            $success = $response->successful() && $responseCode === 0 && $statusId === 0;

            Log::info('MTN HostIF Vend Response', [
                'sequence' => $sequence,
                'status_code' => $response->status(),
                'response_code' => $responseCode,
                'status_id' => $statusId,
                'tx_ref_id' => $parsed['txRefId'] ?? null,
                'response' => $parsed,
            ]);

            return [
                'success' => $success,
                'message' => $this->responseMessage($parsed, $responseCode),
                'reference' => (string) $sequence,
                'provider_reference' => $parsed['txRefId'] ?? null,
                'data' => [
                    'provider' => 'mtn_hostif',
                    'sequence' => $sequence,
                    'response' => $parsed,
                    'raw_body' => $response->body(),
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('MTN HostIF Vend Exception', [
                'sequence' => $sequence,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'MTN HostIF service temporarily unavailable',
                'reference' => (string) $sequence,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function buildVendEnvelope(string $phone, float $amount, int $sequence, int $tariffTypeId): string
    {
        $origMsisdn = htmlspecialchars((string) $this->origMsisdn, ENT_XML1);
        $destMsisdn = htmlspecialchars($this->formatPhone($phone), ENT_XML1);
        $amountValue = htmlspecialchars((string) $this->formatAmount($amount), ENT_XML1);

        $namespace = $this->xmlNamespace
            ? ' xmlns:xsd="' . htmlspecialchars($this->xmlNamespace, ENT_XML1) . '"'
            : '';
        $openVend = $this->xmlNamespace ? '<xsd:vend>' : '<vend>';
        $closeVend = $this->xmlNamespace ? '</xsd:vend>' : '</vend>';
        $tagPrefix = $this->xmlNamespace ? 'xsd:' : '';

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"{$namespace}>
    <soapenv:Header/>
    <soapenv:Body>
        {$openVend}
            <{$tagPrefix}origMsisdn>{$origMsisdn}</{$tagPrefix}origMsisdn>
            <{$tagPrefix}destMsisdn>{$destMsisdn}</{$tagPrefix}destMsisdn>
            <{$tagPrefix}amount>{$amountValue}</{$tagPrefix}amount>
            <{$tagPrefix}sequence>{$sequence}</{$tagPrefix}sequence>
            <{$tagPrefix}tariffTypeId>{$tariffTypeId}</{$tagPrefix}tariffTypeId>
            <{$tagPrefix}serviceproviderId>1</{$tagPrefix}serviceproviderId>
        {$closeVend}
    </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    private function parseXmlResponse(string $body): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_use_internal_errors($previous);

        if (!$xml) {
            return ['raw' => $body];
        }

        $result = [];
        foreach ($xml->xpath('//*[local-name()="vendResponse"]/*') ?: [] as $node) {
            $result[$node->getName()] = (string) $node;
        }

        if ($result !== []) {
            return $result;
        }

        foreach ($xml->xpath('//*[local-name()="Body"]//*') ?: [] as $node) {
                if (count($node->children()) === 0) {
                $result[$node->getName()] = (string) $node;
            }
        }

        return $result ?: ['raw' => $body];
    }

    private function responseMessage(array $parsed, int $responseCode): string
    {
        return $parsed['responseMessage']
            ?? self::RESULT_CODES[$responseCode]
            ?? 'MTN HostIF transaction failed';
    }

    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone) ?? '';

        if ($this->destMsisdnFormat === 'local') {
            return str_starts_with($phone, '234') ? '0' . substr($phone, 3) : $phone;
        }

        if (str_starts_with($phone, '0')) {
            return '234' . substr($phone, 1);
        }

        return $phone;
    }

    private function formatAmount(float $amount): string
    {
        return fmod($amount, 1.0) === 0.0
            ? (string) (int) $amount
            : number_format($amount, 2, '.', '');
    }

    private function generateSequence(): int
    {
        return (int) (now()->format('ymdHis') . random_int(100, 999));
    }

    private function isConfigured(): bool
    {
        return $this->baseUrl !== ''
            && !empty($this->username)
            && !empty($this->password)
            && !empty($this->origMsisdn);
    }
}
