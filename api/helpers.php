<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Test-User-Id");

if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    http_response_code(204);
    exit;
}

function respond(int $code, $data): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function jsonBody(): array {
    $data = json_decode(file_get_contents("php://input"), true);
    return is_array($data) ? $data : [];
}

// The ONE place that answers "who is the user?"
function currentUser(): ?array {
    // For now we read a test header. The login token will replace this later.
    $id = $_SERVER["HTTP_X_TEST_USER_ID"] ?? null;
    return $id ? ["UserID" => (int)$id] : null;
}

function requireUser(): array {
    $user = currentUser();
    if (!$user) {
        respond(401, ["error" => "Please log in"]);
    }
    return $user;
}