<?php
/**
 * OPNsense MCP Server - API Client
 *
 * HTTP client for the OPNsense REST API.
 *
 * @package    OPNsenseMCP\OPNsense
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

namespace OPNsense;

/**
 * Client - OPNsense REST API HTTP client.
 *
 * Thin cURL wrapper that handles authentication, JSON encoding/decoding,
 * and error handling for the OPNsense API.
 *
 * Example usage:
 * ```php
 * $client = new Client('https://192.168.1.1', 'api_key', 'api_secret');
 * $status = $client->get('core/firmware/status');
 * $result = $client->post('firewall/filter/apply');
 * ```
 */
class Client
{
    /**
     * Base URL of the OPNsense instance (e.g., "https://192.168.1.1").
     *
     * @var string
     */
    private string $baseUrl;

    /**
     * API key for authentication.
     *
     * @var string
     */
    private string $apiKey;

    /**
     * API secret for authentication.
     *
     * @var string
     */
    private string $apiSecret;

    /**
     * Whether to verify SSL certificates.
     *
     * @var bool
     */
    private bool $verifySsl;

    /**
     * Request timeout in seconds.
     *
     * @var int
     */
    private int $timeout;

    /**
     * Optional HTTP client callable for dependency injection in tests.
     *
     * Signature: function(string $method, string $url, array $headers, ?string $body): array{code:int,body:string}
     *
     * @var callable|null
     */
    private $httpClient;

    /**
     * Create a new OPNsense API client.
     *
     * @param string        $baseUrl    Base URL (e.g., "https://192.168.1.1")
     * @param string        $apiKey     API key
     * @param string        $apiSecret  API secret
     * @param bool          $verifySsl  Verify SSL certificates (default: false)
     * @param int           $timeout    Request timeout in seconds (default: 30)
     * @param callable|null $httpClient Optional HTTP callable for testing
     */
    public function __construct(
        string $baseUrl,
        string $apiKey,
        string $apiSecret,
        bool $verifySsl = false,
        int $timeout = 30,
        ?callable $httpClient = null
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->verifySsl = $verifySsl;
        $this->timeout = $timeout;
        $this->httpClient = $httpClient;
    }

    /**
     * Perform a GET request to the OPNsense API.
     *
     * @param  string $endpoint API endpoint (e.g., "core/firmware/status")
     * @return array             Decoded JSON response
     * @throws ClientException   On HTTP or parsing errors
     */
    public function get(string $endpoint): array
    {
        return $this->request('GET', $endpoint);
    }

    /**
     * Perform a POST request to the OPNsense API.
     *
     * @param  string $endpoint API endpoint (e.g., "firewall/filter/apply")
     * @param  array  $data     Request body data (JSON-encoded)
     * @return array             Decoded JSON response
     * @throws ClientException   On HTTP or parsing errors
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * Get the base URL of this client.
     *
     * @return string Base URL
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Perform an HTTP request to the OPNsense API.
     *
     * @param  string     $method   HTTP method (GET or POST)
     * @param  string     $endpoint API endpoint relative to /api/
     * @param  array|null $data     Optional POST body data
     * @return array                 Decoded JSON response
     * @throws ClientException       On HTTP or parsing errors
     */
    private function request(string $method, string $endpoint, ?array $data = null): array
    {
        $url = $this->baseUrl . '/api/' . ltrim($endpoint, '/');
        $headers = ['Accept: application/json'];
        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/json';
        }
        $body = ($data !== null) ? json_encode($data) : null;

        // Use injected HTTP client if available (for testing)
        if ($this->httpClient !== null) {
            $response = ($this->httpClient)($method, $url, $headers, $body);
            return $this->handleResponse($response['code'], $response['body'], $url);
        }

        return $this->curlRequest($method, $url, $headers, $body);
    }

    /**
     * Execute an HTTP request using cURL.
     *
     * @param  string      $method  HTTP method
     * @param  string      $url     Full URL
     * @param  array       $headers HTTP headers
     * @param  string|null $body    Request body
     * @return array                 Decoded JSON response
     * @throws ClientException       On connection or HTTP errors
     */
    private function curlRequest(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERPWD => $this->apiKey . ':' . $this->apiSecret,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno !== 0) {
            throw new ClientException("cURL error ({$errno}): {$error}", $errno);
        }

        return $this->handleResponse($httpCode, $responseBody, $url);
    }

    /**
     * Handle an HTTP response: check status code and decode JSON.
     *
     * @param  int    $httpCode     HTTP status code
     * @param  string $responseBody Raw response body
     * @param  string $url          Request URL (for error messages)
     * @return array                 Decoded JSON response
     * @throws ClientException       On HTTP errors or invalid JSON
     */
    private function handleResponse(int $httpCode, string $responseBody, string $url): array
    {
        if ($httpCode === 401) {
            throw new ClientException("Authentication failed (401) for {$url}. Check API key/secret.", 401);
        }

        if ($httpCode === 403) {
            throw new ClientException("Access denied (403) for {$url}. Insufficient API permissions.", 403);
        }

        if ($httpCode >= 500) {
            throw new ClientException("Server error ({$httpCode}) for {$url}: {$responseBody}", $httpCode);
        }

        if ($httpCode >= 400) {
            throw new ClientException("Client error ({$httpCode}) for {$url}: {$responseBody}", $httpCode);
        }

        $decoded = json_decode($responseBody, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new ClientException(
                "Invalid JSON response from {$url}: " . json_last_error_msg(),
                0
            );
        }

        return $decoded ?? [];
    }
}
