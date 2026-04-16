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
 * Uses EnchiladaHTTP for HTTP transport. Handles authentication,
 * JSON encoding/decoding, and error handling for the OPNsense API.
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
     * EnchiladaHTTP instance for real HTTP requests.
     *
     * @var \EnchiladaHTTP
     */
    private \EnchiladaHTTP $http;

    /**
     * Whether to verify SSL certificates.
     *
     * @var bool
     */
    private bool $verifySsl;

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
        $this->verifySsl = $verifySsl;
        $this->httpClient = $httpClient;

        // Configure EnchiladaHTTP for the OPNsense API endpoint
        $this->http = new \EnchiladaHTTP($this->baseUrl);
        $this->http->setPlaintextAuth($apiKey, $apiSecret);
        $this->http->setTimeout($timeout);
        $this->http->setVerifySsl($verifySsl);
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

        return $this->enchiladaRequest($method, $endpoint, $data, $headers);
    }

    /**
     * Execute an HTTP request via EnchiladaHTTP.
     *
     * @param  string     $method   HTTP method
     * @param  string     $endpoint API endpoint relative to /api/
     * @param  array|null $data     Request body data
     * @param  array      $headers  Extra HTTP headers
     * @return array                 Decoded JSON response
     * @throws ClientException       On connection or HTTP errors
     */
    private function enchiladaRequest(string $method, string $endpoint, ?array $data, array $headers): array
    {
        $apiPath = 'api/' . ltrim($endpoint, '/');

        try {
            $result = $this->http->call(
                $apiPath,
                $data,
                $method,
                $headers,
                null,
                'json'
            );
        } catch (\Exception $e) {
            throw new ClientException("HTTP error: " . $e->getMessage(), 0);
        }

        if ($result === false) {
            throw new ClientException(
                "Request failed for {$this->baseUrl}/{$apiPath}",
                0
            );
        }

        return $result ?? [];
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
