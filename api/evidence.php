<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../classes/FulfillmentEvidence.php';

$method = $_SERVER["REQUEST_METHOD"];

try {
    $evidence = new FulfillmentEvidence();

    // GET api/evidence.php?fulfillment_id=12  (Seller, Admin)
    if ($method === "GET") {
        requireUser();
        if (!isset($_GET["fulfillment_id"])) {
            respond(400, ["error" => "fulfillment_id is required"]);
        }
        respond(200, $evidence->getByFulfillmentId($_GET["fulfillment_id"]));
    }

    // POST api/evidence.php  (add a packing photo or video link)
    if ($method === "POST") {
        requireUser();
        respond(201, ["EvidenceID" => $evidence->create(jsonBody())]);
    }

    respond(405, ["error" => "Method not allowed"]);

} catch (InvalidArgumentException $e) {
    respond(400, ["error" => $e->getMessage()]);
} catch (OutOfBoundsException $e) {
    respond(404, ["error" => $e->getMessage()]);
} catch (PDOException $e) {
    if ((int)($e->errorInfo[1] ?? 0) === 1452) {
        respond(400, ["error" => "Referenced record does not exist."]);
    }
    error_log($e->getMessage());
    respond(500, ["error" => "Server error"]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    respond(500, ["error" => "Server error"]);
}
