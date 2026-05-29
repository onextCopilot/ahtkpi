<?php

class OdooAPI
{
    private $url;
    private $database;
    private $username;
    private $api_key;
    private $uid = null;

    public function __construct()
    {
        $this->loadSettings();
    }

    private function loadSettings()
    {
        global $conn;
        if (!isset($conn)) {
            require_once __DIR__ . '/../config/config.php';
        }

        $result = $conn->query("SELECT * FROM odoo_settings ORDER BY id DESC LIMIT 1");

        if ($result && $result->num_rows > 0) {
            $settings = $result->fetch_assoc();
        } else {
            $settings = null;
        }

        if (!$settings) {
            throw new Exception("Odoo settings not found. Please configure in Settings > Odoo API.");
        }

        $this->url = rtrim($settings['odoo_url'], '/');
        $this->database = $settings['odoo_database'];
        $this->username = $settings['odoo_username'];
        $this->api_key = $settings['odoo_api_key'];
    }

    private function jsonRpcCall($service, $method, $args)
    {
        $url = $this->url . '/jsonrpc';

        $data = [
            'jsonrpc' => '2.0',
            'method' => 'call',
            'params' => [
                'service' => $service,
                'method' => $method,
                'args' => $args
            ],
            'id' => rand(0, 1000000)
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // curl_close() is deprecated in PHP 8.5

        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: " . $httpCode);
        }

        $result = json_decode($response, true);

        if (isset($result['error'])) {
            throw new Exception("Odoo Error: " . ($result['error']['data']['message'] ?? $result['error']['message'] ?? 'Unknown error'));
        }

        return $result['result'] ?? null;
    }

    private function authenticate()
    {
        if ($this->uid !== null) {
            return $this->uid;
        }

        $this->uid = $this->jsonRpcCall('common', 'authenticate', [
            $this->database,
            $this->username,
            $this->api_key,
            []
        ]);

        if (!$this->uid) {
            throw new Exception("Odoo authentication failed. Please check your credentials.");
        }

        return $this->uid;
    }

    public function searchRead($model, $domain = [], $fields = [], $limit = 0, $offset = 0)
    {
        $this->authenticate();

        $params = [
            $this->database,
            $this->uid,
            $this->api_key,
            $model,
            'search_read',
            [$domain],
            [
                'fields' => $fields,
                'limit' => $limit,
                'offset' => $offset
            ]
        ];

        return $this->jsonRpcCall('object', 'execute_kw', $params);
    }

    // Cache related methods
    private $cacheFile = __DIR__ . '/../cache/customers.cache.php';
    private $cacheDuration = 86400; // 24 hours

    public function getCustomers($limit = 100, $offset = 0, $filters = [])
    {
        try {
            // Check if cache exists and is valid
            if (!file_exists($this->cacheFile) || (time() - filemtime($this->cacheFile) > $this->cacheDuration)) {
                $this->refreshCustomerCache();
            }

            // Load from cache (strip security header)
            $content = file_get_contents($this->cacheFile);
            $json = str_replace('<?php exit; ?>', '', $content);
            $allCustomers = json_decode($json, true);

            if (!is_array($allCustomers)) {
                $allCustomers = [];
            }

            // Apply filters in memory
            $filteredCustomers = $this->filterCustomersInMemory($allCustomers, $filters);

            // Calculate total matching
            $totalCount = count($filteredCustomers);

            // Apply pagination
            $customers = array_slice($filteredCustomers, $offset, $limit);

            return [
                'customers' => $customers,
                'total' => $totalCount
            ];
        } catch (Exception $e) {
            error_log("Odoo API Error (Cache): " . $e->getMessage());
            throw $e;
        }
    }

    public function refreshCustomerCache()
    {
        try {
            $fields = [
                'id',
                'name',
                'email',
                'phone',
                'mobile',
                'street',
                'city',
                'country_id',
                'industry_id',
                'comment',
                'active',
                'is_company',
                'create_date'
            ];

            // Get ALL customers (companies) from Odoo
            $domain = [['is_company', '=', true]];

            // Note: Odoo limits return size by default (usually 80-100), needed to set high limit
            // But getting ALL at once might be heavy. Let's try limit 0 (unlimited) or a very large number.
            // If the database is huge, we should chunk. Assuming < 10k customers for now.
            $allCustomers = $this->searchRead('res.partner', $domain, $fields, 0, 0);

            if (!is_array($allCustomers)) {
                $allCustomers = [];
            }

            // Save to cache with security header (No encryption for speed)
            if (!is_dir(dirname($this->cacheFile))) {
                mkdir(dirname($this->cacheFile), 0777, true);
            }

            // Add security header to prevent direct access
            $content = '<?php exit; ?>' . json_encode($allCustomers);
            file_put_contents($this->cacheFile, $content);

            return count($allCustomers);

        } catch (Exception $e) {
            error_log("Odoo Cache Refresh Failed: " . $e->getMessage());
            throw $e;
        }
    }

    private function filterCustomersInMemory($customers, $filters)
    {
        return array_filter($customers, function ($customer) use ($filters) {
            // Search filter
            if (!empty($filters['search'])) {
                $search = strtolower($filters['search']);
                $found = false;
                foreach (['name', 'email', 'phone', 'mobile'] as $field) {
                    if (isset($customer[$field]) && stripos($customer[$field], $search) !== false) {
                        $found = true;
                        break;
                    }
                }
                if (!$found)
                    return false;
            }

            // City filter
            if (!empty($filters['city'])) {
                if (!isset($customer['city']) || $customer['city'] != $filters['city']) {
                    return false;
                }
            }

            // Country filter
            if (!empty($filters['country'])) {
                // country_id is usually [id, "Name"]
                $countryName = is_array($customer['country_id']) ? $customer['country_id'][1] : '';
                if ($countryName != $filters['country']) {
                    return false;
                }
            }

            // Status filter
            if (!empty($filters['status'])) {
                $isActive = $customer['active'] ?? false;
                if ($filters['status'] === 'active' && !$isActive)
                    return false;
                if ($filters['status'] === 'inactive' && $isActive)
                    return false;
            }

            return true;
        });
    }

    // Keep getCustomerCount for backward compatibility logic (if needed elsewhere) but updated signature
    public function getCustomerCount($domain = null)
    {
        // This is now redundant if we user cache, but maybe needed for live count check?
        // Let's keep it calling Odoo directly for now if specifically asked.
        return 0; // Not used in cached version
    }

    // Invoice related methods
    private $invoiceCacheFile = __DIR__ . '/../cache/invoices.cache.php';
    private $invoiceCacheDuration = 3600; // 1 hour (invoices update more frequently)

    public function getInvoices($limit = 100, $offset = 0, $filters = [])
    {
        try {
            // Check if cache exists and is valid
            if (!file_exists($this->invoiceCacheFile) || (time() - filemtime($this->invoiceCacheFile) > $this->invoiceCacheDuration)) {
                $this->refreshInvoiceCache();
            }

            // Load from cache (strip security header)
            $content = file_get_contents($this->invoiceCacheFile);
            $json = str_replace('<?php exit; ?>', '', $content);
            $allInvoices = json_decode($json, true);

            if (!is_array($allInvoices)) {
                $allInvoices = [];
            }

            // Apply filters in memory
            $filteredInvoices = $this->filterInvoicesInMemory($allInvoices, $filters);

            // Sort invoices: Recent First (Date DESC, then ID DESC)
            usort($filteredInvoices, function ($a, $b) {
                $dateA = $a['invoice_date'] ?: $a['date'] ?: '0000-00-00';
                $dateB = $b['invoice_date'] ?: $b['date'] ?: '0000-00-00';
                if ($dateA != $dateB) {
                    return strcmp($dateB, $dateA);
                }
                return (int) $b['id'] - (int) $a['id'];
            });

            // Calculate total matching
            $totalCount = count($filteredInvoices);

            // Apply pagination
            $invoices = array_slice($filteredInvoices, $offset, $limit);

            return [
                'invoices' => $invoices,
                'total' => $totalCount
            ];
        } catch (Exception $e) {
            error_log("Odoo API Error (Invoice Cache): " . $e->getMessage());
            throw $e;
        }
    }
    public function getInvoiceMap()
    {
        if (file_exists($this->invoiceCacheFile)) {
            $content = file_get_contents($this->invoiceCacheFile);
            $json = str_replace('<?php exit; ?>', '', $content);
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $map = [];
                foreach ($decoded as $inv) {
                    if (isset($inv['id'])) {
                        $map[$inv['id']] = $inv;
                    }
                }
                return $map;
            }
        }
        return [];
    }

    public function refreshInvoiceCache()
    {
        try {
            $fields = [
                'id',
                'name',
                'invoice_date',
                'date', // accounting date
                'partner_id',
                'commercial_partner_id',
                'amount_total',
                'amount_total_signed', // Amount in company currency
                'amount_tax',          // VAT amount
                'amount_residual', // amount due
                'currency_id',
                'state', // posted, draft, cancel
                'payment_state', // not_paid, in_payment, paid, partial, reversed, invoicing_legacy
                'move_type',
                'invoice_user_id', // Salesperson
                'invoice_origin', // Source Document
                'ref',
                'invoice_date_due',    // Due date
                'write_date', // Last update date (proxy for payment date if state is paid)
                'x_studio_project_code',
                'x_studio_invoice_type_1',
                'x_studio_client_type',
                'company_id',
                'invoice_payments_widget' // JSON blob with payment info
            ];

            // Load existing cache if available
            $existingInvoices = [];
            if (file_exists($this->invoiceCacheFile)) {
                $content = file_get_contents($this->invoiceCacheFile);
                $json = str_replace('<?php exit; ?>', '', $content);
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $inv) {
                        if (isset($inv['id'])) {
                            $existingInvoices[$inv['id']] = $inv;
                        }
                    }
                }
            }

            // Domain to get recent invoices (last 365 days to cover very old draft invoices)
            $dateLimit = gmdate('Y-m-d H:i:s', strtotime('-365 days')); // Odoo returns/expects dates in UTC

            // Get customer invoices
            $domain = [
                ['move_type', '=', 'out_invoice'],
                // Include all states (draft, posted, cancel)
                ['write_date', '>=', $dateLimit]
            ];

            // Fetch high limit recent invoices
            $recentInvoices = $this->searchRead('account.move', $domain, $fields, 20000, 0);

            if (!is_array($recentInvoices)) {
                $recentInvoices = [];
            }

            // Merge into existing cache
            foreach ($recentInvoices as $inv) {
                if (isset($inv['id'])) {
                    $existingInvoices[$inv['id']] = $inv; // Adds new, updates existing
                }
            }

            // Convert back to sequential array
            $allInvoices = array_values($existingInvoices);

            // Save to cache
            if (!is_dir(dirname($this->invoiceCacheFile))) {
                mkdir(dirname($this->invoiceCacheFile), 0777, true);
            }

            $content = '<?php exit; ?>' . json_encode($allInvoices);
            file_put_contents($this->invoiceCacheFile, $content);

            return count($allInvoices);

        } catch (Exception $e) {
            error_log("Odoo Invoice Cache Refresh Failed: " . $e->getMessage());
            throw $e;
        }
    }

    // Helper to map email to Odoo User ID
    private $odooUserMap = [];

    public function getOdooUserId($email)
    {
        if (isset($this->odooUserMap[$email])) {
            return $this->odooUserMap[$email];
        }

        try {
            // Search res.users by login (email)
           // $domain = [['login', '=', $email]];
            $domain = ['|', ['login', '=', $email], ['email', '=', $email]];
            $users = $this->searchRead('res.users', $domain, ['id'], 1);

            if (!empty($users) && isset($users[0]['id'])) {
                $this->odooUserMap[$email] = $users[0]['id'];
                return $users[0]['id'];
            }
        } catch (Exception $e) {
            error_log("Failed to map email to Odoo ID: " . $e->getMessage());
        }

        return null;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    private $currencyCacheFile = __DIR__ . '/../cache/currencies.cache.php';

    public function getCurrencies()
    {
        try {
            // Check cache
            if (file_exists($this->currencyCacheFile) && (time() - filemtime($this->currencyCacheFile) < 86400)) {
                $content = file_get_contents($this->currencyCacheFile);
                $json = str_replace('<?php exit; ?>', '', $content);
                return json_decode($json, true);
            }

            // Fetch from Odoo
            $fields = ['id', 'name', 'rate', 'symbol'];
            $currencies = $this->searchRead('res.currency', [['active', '=', true]], $fields, 0, 0);

            // Re-key by name for easy lookup
            $currencyMap = [];
            foreach ($currencies as $curr) {
                $currencyMap[$curr['name']] = $curr;
            }

            // Save cache
            if (!is_dir(dirname($this->currencyCacheFile))) {
                mkdir(dirname($this->currencyCacheFile), 0777, true);
            }
            file_put_contents($this->currencyCacheFile, '<?php exit; ?>' . json_encode($currencyMap));

            return $currencyMap;
        } catch (Exception $e) {
            error_log("Failed to fetch currencies: " . $e->getMessage());
            return [];
        }
    }

    private $ratesCacheFile = __DIR__ . '/../cache/rates.cache.php';

    public function refreshCurrencyRates()
    {
        try {
            $fields = ['currency_id', 'name', 'rate', 'company_id'];
            // Fetch all rates, ordered by date descending
            $ratesVal = $this->searchRead('res.currency.rate', [], $fields, 0, 0); // Limit 0 for all

            $ratesByCurrency = [];
            foreach ($ratesVal as $r) {
                // currency_id is [id, Name]
                $currencyName = $r['currency_id'][1];
                $company = is_array($r['company_id']) ? $r['company_id'][1] : 'Global';
                
                $ratesByCurrency[$currencyName][] = [
                    'date' => $r['name'],
                    'rate' => $r['rate'],
                    'company' => $company
                ];
            }

            // Sort each currency's rates by date DESC just in case
            foreach ($ratesByCurrency as $k => &$v) {
                usort($v, function ($a, $b) {
                    return strcmp($b['date'], $a['date']);
                });
            }

            // Save to cache
            if (!is_dir(dirname($this->ratesCacheFile))) {
                mkdir(dirname($this->ratesCacheFile), 0777, true);
            }
            file_put_contents($this->ratesCacheFile, '<?php exit; ?>' . json_encode($ratesByCurrency));

            return count($ratesVal);

        } catch (Exception $e) {
            error_log("Failed to refresh currency rates: " . $e->getMessage());
            throw $e;
        }
    }

    public function getRate($currencyName, $date, $companyName = null)
    {
        // Load cache
        static $ratesCache = null;
        if ($ratesCache === null) {
            if (file_exists($this->ratesCacheFile)) {
                $content = file_get_contents($this->ratesCacheFile);
                $json = str_replace('<?php exit; ?>', '', $content);
                $ratesCache = json_decode($json, true);
            }

            // If empty or significantly old, refresh from Odoo
            if ($ratesCache === null || !file_exists($this->ratesCacheFile) || (time() - filemtime($this->ratesCacheFile) > 86400)) {
                try {
                    $this->refreshCurrencyRates();
                    $content = file_get_contents($this->ratesCacheFile);
                    $json = str_replace('<?php exit; ?>', '', $content);
                    $ratesCache = json_decode($json, true);
                } catch (Exception $e) {
                    error_log("Failed to auto-refresh rates: " . $e->getMessage());
                }
            }
        }


        if ($ratesCache === null || !isset($ratesCache[$currencyName])) {
            error_log("Currency rate for {$currencyName} not found in cache and Odoo sync failed/unavailable.");
            return 1.0;
        }

        // Find applicable rate (first one where rate_date <= date)
        // Strictly filter by company if provided
        $entries = $ratesCache[$currencyName];
        if ($companyName) {
            $filtered = [];
            foreach ($entries as $entry) {
                if (($entry['company'] ?? 'Global') === $companyName) {
                    $filtered[] = $entry;
                }
            }
            $entries = $filtered;
        }

        if (empty($entries)) {
            return false;
        }

        foreach ($entries as $entry) {
            if ($entry['date'] <= $date) {
                return (float)$entry['rate'];
            }
        }

        // If no entry matched the date, return the oldest one for this company/global
        $last = end($entries);
        return (float)$last['rate'];
    }

    private function filterInvoicesInMemory($invoices, $filters)
    {
        // Resolve Owner ID if email is provided
        $ownerId = null;
        if (!empty($filters['owner_email'])) {
            $ownerId = $this->getOdooUserId($filters['owner_email']);
        }

        return array_filter($invoices, function ($invoice) use ($filters, $ownerId) {
            // Search filter (Invoice Number, Customer Name, Reference)
            if (!empty($filters['search'])) {
                $search = strtolower($filters['search']);
                $found = false;

                // Check invoice number
                if (isset($invoice['name']) && stripos($invoice['name'], $search) !== false)
                    $found = true;

                // Check customer name (partner_id is [id, name])
                if (!$found && isset($invoice['partner_id']) && is_array($invoice['partner_id']) && stripos($invoice['partner_id'][1] ?? '', $search) !== false)
                    $found = true;

                // Check reference
                if (!$found && isset($invoice['ref']) && stripos($invoice['ref'], $search) !== false)
                    $found = true;

                if (!$found)
                    return false;
            }

            // Status filter
            if (!empty($filters['status'])) {
                if ($filters['status'] === 'paid' && ($invoice['payment_state'] ?? '') !== 'paid')
                    return false;
                if ($filters['status'] === 'open' && ($invoice['state'] ?? '') !== 'posted')
                    return false; // Basic example
                if ($filters['status'] === 'draft' && ($invoice['state'] ?? '') !== 'draft')
                    return false;
            }

            // Owner Filter (Exact Odoo User ID Match)
           // if ($ownerId !== null) {

            if (!empty($filters['owner_email'])) {
                if ($ownerId === null) {
                    // if email was requested but not found in Odoo, return no invoices for them
                    return false;
                }

                    
                // invoice_user_id is [id, "Name"]
                $invoiceUserId = isset($invoice['invoice_user_id']) && is_array($invoice['invoice_user_id']) ? $invoice['invoice_user_id'][0] : null;

                if ($invoiceUserId != $ownerId) {
                    return false;
                }
            }
            // Fallback to name if email lookup failed but name provided (optional, removing for strict correctness as requested)

            return true;
        });
    }
}
