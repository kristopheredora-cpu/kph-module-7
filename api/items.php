<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../classes/FulfillmentItem.php';

$method = $_SERVER["REQUEST_METHOD"];

try {
    $item = new FulfillmentItem();

    // GET api/items.php?fulfillment_id=12
    if ($method === "GET") {
        if (!isset($_GET["fulfillment_id"])) {
            respond(400, ["error" => "fulfillment_id is required"]);
        }
        respond(200, $item->getByFulfillmentId($_GET["fulfillment_id"]));
    }

    // POST api/items.php  (called by Module 6 when it builds the fulfillment)
    if ($method === "POST") {
        requireUser();
        respond(201, ["FulfillmentItemID" => $item->create(jsonBody())]);
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
