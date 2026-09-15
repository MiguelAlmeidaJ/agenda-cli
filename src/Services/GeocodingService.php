<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class GeocodingService
{
    private const PROVIDER = 'nominatim';

    public function geocode(array $address): ?array
    {
        $query = $this->query($address);
        if ($query === null) {
            return null;
        }

        $queryText = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $hash = hash('sha256', $queryText);
        $pdo = Database::connection();

        $cached = $pdo->prepare(
            'SELECT latitude, longitude, found FROM geocoding_cache '
            . 'WHERE provider = :provider AND query_hash = :hash LIMIT 1'
        );
        $cached->execute(['provider' => self::PROVIDER, 'hash' => $hash]);
        $row = $cached->fetch();
        if ($row) {
            if (!(bool) $row['found']) {
                return null;
            }
            return [
                'latitude' => (float) $row['latitude'],
                'longitude' => (float) $row['longitude'],
                'provider' => self::PROVIDER,
            ];
        }

        $this->throttle();
        $result = $this->request($queryText);

        $insert = $pdo->prepare(
            'INSERT INTO geocoding_cache (provider, query_hash, query_text, latitude, longitude, found) '
            . 'VALUES (:provider, :hash, :query, :latitude, :longitude, :found) '
            . 'ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude), found = VALUES(found)'
        );
        $insert->execute([
            'provider' => self::PROVIDER,
            'hash' => $hash,
            'query' => substr($queryText, 0, 500),
            'latitude' => $result['latitude'] ?? null,
            'longitude' => $result['longitude'] ?? null,
            'found' => $result === null ? 0 : 1,
        ]);

        return $result;
    }

    private function query(array $address): ?array
    {
        $street = trim((string) ($address['street'] ?? ''));
        $number = trim((string) ($address['address_number'] ?? ''));
        $city = trim((string) ($address['city'] ?? ''));
        $state = trim((string) ($address['state'] ?? ''));
        $postalCode = preg_replace('/\D+/', '', (string) ($address['postal_code'] ?? '')) ?? '';

        if ($street === '' || $city === '' || $state === '') {
            return null;
        }

        return array_filter([
            'format' => 'jsonv2',
            'limit' => '1',
            'countrycodes' => 'br',
            'street' => trim(($number !== '' ? $number . ' ' : '') . $street),
            'city' => $city,
            'state' => $state,
            'postalcode' => $postalCode !== '' ? $postalCode : null,
            'country' => 'Brasil',
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function request(string $queryText): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $baseUrl = rtrim((string) env('GEOCODING_BASE_URL', 'https://nominatim.openstreetmap.org'), '/');
        if (!str_starts_with($baseUrl, 'https://')) {
            return null;
        }

        $appUrl = rtrim((string) env('APP_URL', ''), '/');
        $defaultAgent = $appUrl !== ''
            ? 'AgendaCLI/1.0 (+' . $appUrl . ')'
            : 'AgendaCLI/1.0';
        $userAgent = trim((string) env('GEOCODING_USER_AGENT', $defaultAgent));

        $curl = curl_init($baseUrl . '/search?' . $queryText);
        if ($curl === false) {
            return null;
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if (!is_string($body) || $status < 200 || $status >= 300) {
            return null;
        }

        $payload = json_decode($body, true);
        if (!is_array($payload) || !isset($payload[0]['lat'], $payload[0]['lon'])) {
            return null;
        }

        $latitude = filter_var($payload[0]['lat'], FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($payload[0]['lon'], FILTER_VALIDATE_FLOAT);
        if ($latitude === false || $longitude === false || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }

        return [
            'latitude' => round((float) $latitude, 7),
            'longitude' => round((float) $longitude, 7),
            'provider' => self::PROVIDER,
        ];
    }

    private function throttle(): void
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'agenda-cli-nominatim.lock';
        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            usleep(1_000_000);
            return;
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            usleep(1_000_000);
            return;
        }

        rewind($handle);
        $last = (float) trim((string) stream_get_contents($handle));
        $elapsed = microtime(true) - $last;
        if ($last > 0 && $elapsed < 1.05) {
            usleep((int) ((1.05 - $elapsed) * 1_000_000));
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, sprintf('%.6F', microtime(true)));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
