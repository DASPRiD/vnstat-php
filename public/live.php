<?php
require dirname(__DIR__) . '/vendor/autoload.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$fail = static function (string $message, int $status = 404): never {
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
};

$configFile = dirname(__DIR__) . '/config/config.php';
$config = file_exists($configFile) ? require $configFile : [];
$configured = $config['interfaces'] ?? [];
$interface = (string) ($_GET['interface'] ?? '');

if (!is_array($configured)) {
    $fail('config/config.php has to give "interfaces" as a list.', 500);
}

try {
    // Without a configured list the page shows the one interface vnStat
    // reports first, and asking vnStat is the only way to learn its name.
    // Serving every interface the kernel exposes would let a visitor read
    // the traffic of ones the page never offers.
    $allowed = [] !== $configured
        ? $configured
        : [(new Vnstat\Database((new Vnstat\Reader())->read()))->getInterface()];
} catch (Throwable $error) {
    $fail($error->getMessage(), 500);
}

if (!in_array($interface, $allowed, true)) {
    $fail(sprintf('Interface "%s" is not one this page reports.', $interface));
}

try {
    $counters = Vnstat\KernelCounters::read($interface);
} catch (Throwable $error) {
    $fail($error->getMessage());
}

echo json_encode([
    'received' => $counters->received,
    'sent' => $counters->sent,
    'at' => microtime(true),
]);
