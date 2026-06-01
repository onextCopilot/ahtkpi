<?php
/**
 * Notify production system when a pakd deal is won or lost.
 * POST {api_url}/integrations/os/pakd/{pakdId}/result
 */
function notifyPakdResult(mysqli $conn, int $pakdId, string $wonStatus, ?string $lostReason = null): bool
{
    $configFile = __DIR__ . '/../config/arrowhitech_config.json';
    if (!file_exists($configFile)) return false;

    $cfg       = json_decode(file_get_contents($configFile), true);
    $api_url   = rtrim($cfg['api_url']   ?? '', '/');
    $api_token = $cfg['api_token'] ?? '';
    if (!$api_url || !$api_token) return false;

    // Fetch pakd info
    $st = $conn->prepare(
        "SELECT id, opportunity_name, am_name, company_name, odoo_opp_id, pasx_id,
                currency, opp_value, revenue, gross_profit, opp_probability, lost_reason
         FROM pakd WHERE id = ? LIMIT 1"
    );
    $st->bind_param('i', $pakdId);
    $st->execute();
    $pakd = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$pakd) return false;

    // totalAmount: prefer actual revenue, fallback to opportunity value
    $totalAmount = !empty($pakd['revenue']) && (float)$pakd['revenue'] > 0
        ? (float)$pakd['revenue']
        : (float)($pakd['opp_value'] ?? 0);

    $payload = [
        'status'      => $wonStatus,
        'totalAmount' => $totalAmount,
        'currency'    => $pakd['currency'] ?: 'VND',
        'extraData'   => [
            'pakdId'      => $pakdId,
            'odooOppId'   => $pakd['odoo_opp_id']      ? (int)$pakd['odoo_opp_id'] : null,
            'pasxId'      => $pakd['pasx_id']           ?: null,
            'oppName'     => $pakd['opportunity_name']  ?: null,
            'companyName' => $pakd['company_name']      ?: null,
            'amName'      => $pakd['am_name']           ?: null,
            'lostReason'  => $lostReason ?? ($pakd['lost_reason'] ?: null),
            'grossProfit' => (float)($pakd['gross_profit'] ?? 0) ?: null,
            'probability' => (float)($pakd['opp_probability'] ?? 0),
        ],
    ];

    $timestamp  = (int)(microtime(true) * 1000);
    $request_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));

    $ch = curl_init($api_url . '/integrations/os/pakd/' . $pakdId . '/result');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'X-API-Key: '    . $api_token,
            'X-Timestamp: '  . $timestamp,
            'X-Request-Id: ' . $request_id,
            'Content-Type: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 10,
    ]);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $http_code >= 200 && $http_code < 300;
}
