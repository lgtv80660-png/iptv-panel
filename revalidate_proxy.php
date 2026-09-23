<?php
// revalidate_proxy.php
header('Content-Type: application/json');

$revalidateUrl = "https://gpanel.up.railway.app/api/revalidate?secret=mon_secret_super_securise";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $revalidateUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 5,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200 && $response !== false) {
    echo $response;
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Impossible de contacter l\'application G-TV']);
}
