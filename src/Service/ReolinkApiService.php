<?php

namespace App\Service;

use App\Entity\CameraApiSettings;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ReolinkApiService implements CameraApiInterface
{

    protected CameraApiSettings $apiSettings;

    private ?HttpClientInterface $httpClient = null;

    /**
     * Default Reolink camera channel. Most single cameras use channel 0.
     */
    private int $channel = 0;

    public function __construct(CameraApiSettings $apiSettings)
    {
        $this->apiSettings = $apiSettings;
    }

    public function enableRecording(): bool
    {
        return $this->setRecording(true);
    }

    public function disableRecording(): bool
    {
        return $this->setRecording(false);
    }

    public function enableSiren(): bool
    {
        return $this->setSiren(true);
    }

    public function disableSiren(): bool
    {
        return $this->setSiren(false);
    }

    private function setRecording(bool $enabled): bool
    {
        try {
            $token = $this->getToken();

            $payload = [[
                'cmd' => 'SetRecV20',
                'action' => 0,
                'param' => [
                    'Rec' => [
                        'channel' => $this->channel,
                        'enable' => $enabled ? 1 : 0,
                    ],
                ],
            ]];

            $resp = $this->post('SetRecV20', $payload, $token);

            return $this->isCmdOk($resp, 'SetRecV20');
        } catch (\Throwable) {
            return false;
        }
    }

    private function setSiren(bool $enabled): bool
    {
        try {
            $token = $this->getToken();

            // Some firmwares expect "manul" instead of "manual".
            $param = [
                'alarm_mode' => 'manul',
                'manual_switch' => $enabled ? 1 : 0,
                'channel' => $this->channel,
            ];

            // Optional; some examples include it. Keep it only when turning on.
            if ($enabled) {
                $param['times'] = 1;
            }

            $payload = [[
                'cmd' => 'AudioAlarmPlay',
                'action' => 0,
                'param' => $param,
            ]];

            $resp = $this->post('AudioAlarmPlay', $payload, $token);

            return $this->isCmdOk($resp, 'AudioAlarmPlay');
        } catch (\Throwable) {
            return false;
        }
    }

    private function getHttpClient(): HttpClientInterface
    {
        if ($this->httpClient === null) {
            // Cameras often use self-signed TLS; disable verification by default.
            $this->httpClient = HttpClient::create([
                'verify_peer' => false,
                'verify_host' => false,
                'timeout' => 5.0,
            ]);
        }

        return $this->httpClient;
    }

    private function getBaseUrl(): string
    {
        $path = trim($this->apiSettings->getPath());

        // Allow passing either "http(s)://ip" or "http(s)://ip/cgi-bin/api.cgi"
        $path = preg_replace('~/cgi-bin/api\.cgi.*$~i', '', $path) ?? $path;

        return rtrim($path, '/');
    }

    private function getApiCgiUrl(): string
    {
        return $this->getBaseUrl() . '/cgi-bin/api.cgi';
    }

    /**
     * @return array<mixed>
     */
    private function post(string $cmd, array $payload, ?string $token = null): array
    {
        $url = $this->getApiCgiUrl() . '?cmd=' . rawurlencode($cmd);
        if ($token !== null && $token !== '') {
            $url .= '&token=' . rawurlencode($token);
        }

        $response = $this->getHttpClient()->request('POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        $content = $response->getContent(false);
        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('Unexpected Reolink API response.');
        }

        return $decoded;
    }

    private function isCmdOk(array $resp, string $cmd): bool
    {
        foreach ($resp as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (($entry['cmd'] ?? null) !== $cmd) {
                continue;
            }
            // Reolink usually uses code=0 for success.
            return (int)($entry['code'] ?? -1) === 0;
        }

        return false;
    }

    private function getToken(): string
    {
        $cacheFile = $this->getTokenCacheFile();

        // Try cached token first
        if (is_file($cacheFile)) {
            $raw = @file_get_contents($cacheFile);
            if (is_string($raw) && $raw !== '') {
                $data = json_decode($raw, true);
                if (is_array($data)) {
                    $token = (string)($data['token'] ?? '');
                    $exp = (int)($data['expires_at'] ?? 0);
                    if ($token !== '' && $exp > time()) {
                        return $token;
                    }
                }
            }
        }

        // Login and cache token
        $payload = [[
            'cmd' => 'Login',
            'action' => 0,
            'param' => [
                'User' => [
                    'userName' => $this->apiSettings->getUsername(),
                    'password' => $this->apiSettings->getPassword(),
                ],
            ],
        ]];

        $resp = $this->post('Login', $payload, null);

        if (!$this->isCmdOk($resp, 'Login')) {
            throw new \RuntimeException('Reolink Login failed.');
        }

        $first = is_array($resp[0] ?? null) ? $resp[0] : [];
        $token = (string)($first['value']['Token']['name'] ?? '');
        $lease = (int)($first['value']['Token']['leaseTime'] ?? 0);

        if ($token === '') {
            throw new \RuntimeException('Reolink token missing in response.');
        }

        // Keep a safety margin to avoid edge-expiry.
        $ttl = max(60, $lease - 60);
        $data = [
            'token' => $token,
            'expires_at' => time() + $ttl,
        ];

        @file_put_contents($cacheFile, json_encode($data, JSON_THROW_ON_ERROR));

        return $token;
    }

    private function getTokenCacheFile(): string
    {
        $key = sha1($this->getBaseUrl() . '|' . $this->apiSettings->getUsername());
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'reolink_token_' . $key . '.json';
    }
}