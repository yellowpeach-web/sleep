<?php

namespace YPTheme;

class StructuredData
{
    private static $added_to_graph = false;

    public static function init()
    {
        add_filter('wpseo_schema_graph', [self::class, 'add_locations_to_graph'], 11, 2);
        add_action('wp_head', [self::class, 'print_fallback'], 20);
    }

    public static function add_locations_to_graph($graph)
    {
        self::$added_to_graph = true;

        if (!is_array($graph)) {
            return $graph;
        }

        foreach ($graph as $index => $piece) {
            if (!is_array($piece) || !self::piece_is_organization($piece)) {
                continue;
            }

            $org_id = !empty($piece['@id']) ? $piece['@id'] : home_url('/#organization');
            $locations = self::locations($org_id);
            $graph[$index]['@id'] = $org_id;

            if ($locations) {
                $graph[$index]['location'] = $locations;

                if (empty($graph[$index]['address']) && !empty($locations[0]['address'])) {
                    $graph[$index]['address'] = $locations[0]['address'];
                }
            }

            return $graph;
        }

        $graph[] = self::organization();

        return $graph;
    }

    public static function print_fallback()
    {
        if (self::$added_to_graph || is_admin() || is_feed()) {
            return;
        }

        $data = ['@context' => 'https://schema.org'] + self::organization();

        echo '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    private static function piece_is_organization(array $piece)
    {
        if (empty($piece['@type'])) {
            return false;
        }

        return in_array('Organization', (array) $piece['@type'], true);
    }

    private static function organization()
    {
        $org_id = home_url('/#organization');
        $locations = self::locations($org_id);
        $data = [
            '@type' => 'Organization',
            '@id' => $org_id,
            'name' => get_bloginfo('name') ?: 'Sleep.ai',
            'url' => home_url('/'),
        ];

        $company = self::option_text('company_name');
        if ($company !== '') {
            $data['legalName'] = $company;
        }

        if ($locations) {
            $data['location'] = $locations;

            if (!empty($locations[0]['address'])) {
                $data['address'] = $locations[0]['address'];
            }
        }

        return $data;
    }

    /**
     * Offices entered in General Settings → Schema locations.
     */
    private static function locations($org_id)
    {
        if (!function_exists('get_field')) {
            return [];
        }

        $rows = get_field('schema_locations', 'options');
        if (!is_array($rows)) {
            return [];
        }

        $locations = [];
        $used_slugs = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = self::row_text($row, 'name');
            $legal_name = self::row_text($row, 'legal_name');
            $address = self::postal_address($row);

            if ($name === '' && $legal_name === '' && !$address) {
                continue;
            }

            $base = sanitize_title($name !== '' ? $name : 'location-' . ($index + 1));
            if ($base === '') {
                $base = 'location-' . ($index + 1);
            }

            $slug = $base;
            $suffix = 2;
            while (isset($used_slugs[$slug])) {
                $slug = $base . '-' . $suffix;
                $suffix++;
            }
            $used_slugs[$slug] = true;

            $location = [
                '@type' => 'LocalBusiness',
                '@id' => home_url('/#location-' . $slug),
                'name' => $name !== '' ? $name : get_bloginfo('name'),
                'parentOrganization' => ['@id' => $org_id],
            ];

            if ($legal_name !== '') {
                $location['legalName'] = $legal_name;
            }

            if ($address) {
                $location['address'] = $address;
            }

            $locations[] = $location;
        }

        return $locations;
    }

    private static function postal_address(array $row)
    {
        $address = ['@type' => 'PostalAddress'];
        $map = [
            'street_address' => 'streetAddress',
            'locality' => 'addressLocality',
            'region' => 'addressRegion',
            'postal_code' => 'postalCode',
        ];

        foreach ($map as $field => $property) {
            $value = self::row_text($row, $field);
            if ($value !== '') {
                $address[$property] = $value;
            }
        }

        $country = strtoupper(self::row_text($row, 'country'));
        if ($country !== '') {
            $address['addressCountry'] = $country;
        }

        return count($address) > 1 ? $address : null;
    }

    private static function row_text(array $row, $key)
    {
        $value = $row[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    private static function option_text($name)
    {
        if (!function_exists('get_field')) {
            return '';
        }

        $value = get_field($name, 'options');

        return is_string($value) ? trim($value) : '';
    }
}
