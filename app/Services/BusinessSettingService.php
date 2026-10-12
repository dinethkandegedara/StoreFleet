<?php

namespace App\Services;

use App\Models\Setting;

class BusinessSettingService
{
    /**
     * Default business configurations.
     */
    protected array $defaults = [
        'business_name' => 'StoreFleet Merchant',
        'business_logo_path' => null,
        'business_email' => 'contact@storefleet.test',
        'business_phone' => '+1 (555) 019-2834',
        'address_line1' => '100 Innovation Boulevard',
        'address_line2' => 'Suite 400',
        'city' => 'San Francisco',
        'state' => 'CA',
        'postal_code' => '94105',
        'country' => 'US',
        'primary_color' => '#2563eb',
        'currency_code' => 'USD',
        'currency_symbol' => '$',
        'currency_precision' => 2,
        'currency_symbol_position' => 'before',
        'decimal_separator' => '.',
        'thousand_separator' => ',',
        'timezone' => 'America/New_York',
        'date_format' => 'Y-m-d',
        'time_format' => 'H:i',
        'tax_enabled' => true,
        'default_tax_rate' => 10.00,
        'tax_id_number' => 'TAX-99201-US',
        'prices_include_tax' => false,
        'receipt_header' => 'Thank you for shopping with us!',
        'receipt_footer' => 'Items may be exchanged within 30 days with original receipt.',
        'receipt_show_logo' => true,
    ];

    /**
     * Retrieve all business settings merged with system defaults.
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        $stored = Setting::allForScope('business', null);

        $result = $this->defaults;
        foreach ($stored as $key => $val) {
            if (array_key_exists($key, $this->defaults)) {
                // Cast booleans, floats, and integers
                if (is_bool($this->defaults[$key])) {
                    $result[$key] = (bool) $val;
                } elseif (is_int($this->defaults[$key])) {
                    $result[$key] = (int) $val;
                } elseif (is_float($this->defaults[$key])) {
                    $result[$key] = (float) $val;
                } else {
                    $result[$key] = $val;
                }
            } else {
                $result[$key] = $val;
            }
        }

        return $result;
    }

    /**
     * Retrieve a specific business setting.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->getAll();

        return $all[$key] ?? $default ?? ($this->defaults[$key] ?? null);
    }

    /**
     * Save multiple business settings atomically.
     *
     * @param  array<string, mixed>  $settings
     */
    public function updateMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            Setting::set($key, $value, 'business', null);
        }
    }
}
