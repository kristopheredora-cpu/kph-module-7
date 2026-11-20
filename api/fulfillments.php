<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../classes/Fulfillment.php';

$method = $_SERVER["REQUEST_METHOD"];
$id = isset($_GET["id"]) ? (int)$_GET["id"] : null;
$action = $_GET["action"] ?? null;

try {
    // Created INSIDE try so a database problem becomes a clean 500, not a PHP error page.
    $fulfillment = new Fulfillment();

    // 1. GET late fulfillments (past ship-by, not handed over)
    if ($method === "GET" && isset($_GET["late"]) && $_GET["late"] === "1") {
        requireUser();
        respond(200, $fulfillment->listLate());
    }

    // 2. GET a seller's work list: ?seller_id=2&status=To Pack
    if ($method === "GET" && isset($_GET["seller_id"])) {
        requireUser();
        respond(200, $fulfillment->listBySeller($_GET["seller_id"], $_GET["status"] ?? null));
    }

    // 3. GET one fulfillment (with items and history)
    if ($method === "GET" && $id !== null) {
        respond(200, $fulfillment->getDetails($id));
    }

    // 4. SPECIAL JOB: Buyer confirms receipt
    if ($method === "POST" && $action === "confirm-receipt" && $id !== null) {
        requireUser();
        $fulfillment->confirmReceipt($id);
        respond(200, ["message" => "Receipt confirmed, funds released"]);
    }

    // 5. SPECIAL JOB: Hold funds for dispute (called by Module 9)
    if ($method === "POST" && $action === "hold" && $id !== null) {
        requireUser();
        $fulfillment->holdForDispute($id);
        respond(200, ["message" => "Funds held for dispute"]);
    }

    // 6. SPECIAL JOB: Auto-release everything that is due
    if ($method === "POST" && $action === "auto-release") {
        requireUser();
        $count = $fulfillment->autoReleaseFunds();
        respond(200, ["message" => "$count fulfillments auto-released", "released" => $count]);
    }

    // 7. PUT: change package status. Module 8 sends "Delivered" with ChangedByRole "Courier".
    //    TODO: when the login token exists, take the role from the logged-in user instead of the body.
    if ($method === "PUT" && $action === "status" && $id !== null) {
        requireUser();
        $data = jsonBody();
        $fulfillment->changePackageStatus(
            $id,
            $data['PackageStatus'] ?? "",
            $data['ChangedByRole'] ?? 'Seller',
            $data['Notes'] ?? null
        );
        respond(200, ["message" => "Package status updated"]);
    }

    // 8. POST: create a fulfillment (called by Module 6)
    if ($method === "POST" && $action === null && $id === null) {
        requireUser();
        $newId = $fulfillment->create(jsonBody());
        respond(201, ["FulfillmentID" => $newId]);
    }

    respond(405, ["error" => "Method not allowed"]);

} catch (InvalidArgumentException $e) {
    respond(400, ["error" => $e->getMessage()]);
} catch (OutOfBoundsException $e) {
    respond(404, ["error" => $e->getMessage()]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    respond(500, ["error" => "Server error"]);
}
