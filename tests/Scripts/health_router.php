<?php

// Router del servidor embebido de PHP que simula /health/ready en sus distintos estados.
// Solo lo usa HealthCheckScriptTest; no es un test.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

header('Content-Type: application/json');
switch ($path) {
    case '/ok':
        echo '{"status":"ok","database":"connected"}';
        break;
    case '/redirect':   // lo que hace Traefik con cualquier petición HTTP
        http_response_code(301);
        header('Location: https://localhost/health/ready');
        break;
    case '/down':
        http_response_code(503);
        echo '{"status":"error","database":"disconnected"}';
        break;
    case '/wrong-body': // 200, pero la app dice que no está bien
        echo '{"status":"error","database":"connected"}';
        break;
    case '/empty':
        break;
    case '/flaky':      // 503 las N primeras veces, luego 200
        $file = getenv('FLAKY_STATE_FILE');
        $served = is_file($file) ? (int) file_get_contents($file) : 0;
        file_put_contents($file, (string) ($served + 1));
        if ($served < (int) getenv('FLAKY_FAILURES')) {
            http_response_code(503);
            echo '{"status":"error"}';
        } else {
            echo '{"status":"ok","database":"connected"}';
        }
        break;
    default:
        http_response_code(404);
}
