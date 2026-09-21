<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class PublicEstablishmentSearchService
{
    public function filters(array $input): array
    {
        $q = $this->limit(trim((string) ($input['q'] ?? '')), 100);
        $city = $this->limit(trim((string) ($input['city'] ?? '')), 100);
        $state = strtoupper(trim((string) ($input['state'] ?? '')));
        $sort = (string) ($input['sort'] ?? 'relevance');

        if (!preg_match('/^[A-Z]{2}$/', $state)) {
            $state = '';
        }
        if (!in_array($sort, ['relevance', 'name', 'price'], true)) {
            $sort = 'relevance';
        }

        return [
            'q' => $q,
            'city' => $city,
            'state' => $state,
            'sort' => $sort,
        ];
    }

    public function search(PDO $pdo, array $filters): array
    {
        $where = ['e.active = 1'];
        $params = [];

        if ($filters['q'] !== '') {
            $term = '%' . $this->escapeLike($filters['q']) . '%';
            $where[] = '('
                . "e.name LIKE :q_name ESCAPE '!' "
                . "OR e.description LIKE :q_description ESCAPE '!' "
                . 'OR EXISTS (SELECT 1 FROM services sq WHERE sq.establishment_id=e.id AND sq.active=1 '
                . "AND (sq.name LIKE :q_service_name ESCAPE '!' OR sq.description LIKE :q_service_description ESCAPE '!'))"
                . ')';

            $params['q_name'] = $term;
            $params['q_description'] = $term;
            $params['q_service_name'] = $term;
            $params['q_service_description'] = $term;
        }

        if ($filters['city'] !== '') {
            $where[] = "e.city LIKE :city ESCAPE '!'";
            $params['city'] = '%' . $this->escapeLike($filters['city']) . '%';
        }

        if ($filters['state'] !== '') {
            $where[] = 'e.state = :state';
            $params['state'] = $filters['state'];
        }

        $sql = "SELECT e.id,e.name,e.slug,e.description,e.city,e.state,e.logo_url,e.cover_url,"
            . "e.latitude,e.longitude,COUNT(DISTINCT s.id) service_count,MIN(s.price) min_price,"
            . "GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR '||') service_names "
            . "FROM establishments e "
            . "LEFT JOIN services s ON s.establishment_id=e.id AND s.active=1 "
            . 'WHERE ' . implode(' AND ', $where)
            . ' GROUP BY e.id';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['service_names'] = ($row['service_names'] ?? '') !== ''
                ? explode('||', (string) $row['service_names'])
                : [];
            $row['search_rank'] = $this->rank($row, (string) $filters['q']);
        }
        unset($row);

        $sort = (string) $filters['sort'];
        usort($rows, function (array $a, array $b) use ($sort): int {
            if ($sort === 'price') {
                $aPrice = $a['min_price'] !== null ? (float) $a['min_price'] : INF;
                $bPrice = $b['min_price'] !== null ? (float) $b['min_price'] : INF;
                $priceOrder = $aPrice <=> $bPrice;
                if ($priceOrder !== 0) return $priceOrder;
            } elseif ($sort === 'relevance') {
                $rankOrder = ((int) $a['search_rank']) <=> ((int) $b['search_rank']);
                if ($rankOrder !== 0) return $rankOrder;
            }

            return strcasecmp((string) $a['name'], (string) $b['name']);
        });

        return $rows;
    }

    public function suggestions(PDO $pdo): array
    {
        $services = $pdo->query(
            'SELECT DISTINCT s.name FROM services s JOIN establishments e ON e.id=s.establishment_id '
            . 'WHERE s.active=1 AND e.active=1 ORDER BY s.name LIMIT 150'
        )->fetchAll(PDO::FETCH_COLUMN);

        $locations = $pdo->query(
            "SELECT DISTINCT city,state FROM establishments "
            . "WHERE active=1 AND city IS NOT NULL AND city<>'' ORDER BY state,city LIMIT 250"
        )->fetchAll();

        $cities = [];
        $states = [];
        foreach ($locations as $location) {
            $city = trim((string) ($location['city'] ?? ''));
            $state = strtoupper(trim((string) ($location['state'] ?? '')));
            if ($city !== '') {
                $cities[$this->lower($city)] = $city;
            }
            if (preg_match('/^[A-Z]{2}$/', $state)) {
                $states[$state] = $state;
            }
        }

        natcasesort($cities);
        ksort($states);

        return [
            'services' => array_values(array_map('strval', $services)),
            'cities' => array_values($cities),
            'states' => array_values($states),
        ];
    }

    public function rank(array $row, string $query): int
    {
        $query = $this->lower(trim($query));
        if ($query === '') return 0;

        $name = $this->lower((string) ($row['name'] ?? ''));
        if ($name === $query) return 0;
        if (str_contains($name, $query)) return 1;

        foreach ((array) ($row['service_names'] ?? []) as $serviceName) {
            if ($this->lower((string) $serviceName) === $query) return 2;
        }
        foreach ((array) ($row['service_names'] ?? []) as $serviceName) {
            if (str_contains($this->lower((string) $serviceName), $query)) return 3;
        }

        $description = $this->lower((string) ($row['description'] ?? ''));
        if ($description !== '' && str_contains($description, $query)) return 4;

        return 5;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    private function limit(string $value, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
    }

    private function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    }
}
