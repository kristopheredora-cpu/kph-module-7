<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../classes/ShippingQuote.php';

$method = $_SERVER["REQUEST_METHOD"];
$id = isset($_GET["id"]) ? (int)$_GET["id"] : null;
$action = $_GET["action"] ?? null;

try {
    $quote = new ShippingQuote();

    // GET api/quotes.php?fulfillment_id=12
    if ($method === "GET") {
        if (!isset($_GET["fulfillment_id"])) {
            respond(400, ["error" => "fulfillment_id is required"]);
        }
        respond(200, $quote->getQuotesByFulfillment($_GET["fulfillment_id"]));
    }

    // POST api/quotes.php?action=request
    // Body: {"FulfillmentID":12,"Quotes":[{"CourierID":1,"QuotedFee":150,"EstimatedDeliveryDays":3}, ...]}
    if ($method === "POST" && $action === "request") {
        requireUser();
        $data = jsonBody();
        $ids = $quote->createMany($data['FulfillmentID'] ?? null, $data['Quotes'] ?? null);
        respond(201, ["QuoteIDs" => $ids]);
    }

    // PUT api/quotes.php?id=3&action=select  (the fulfillment is found from the quote itself)
    if ($method === "PUT" && $action === "select" && $id !== null) {
        requireUser();
        $quote->selectQuote($id, jsonBody()['FulfillmentID'] ?? null);
        respond(200, ["message" => "Quote selected and shipping fee updated"]);
    }

    // POST api/quotes.php  (save one quote)
    if ($method === "POST" && $action === null) {
        requireUser();
        respond(201, ["QuoteID" => $quote->create(jsonBody())]);
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
