<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/Validate.php';

class ShippingQuote {
    private PDO $pdo;

    public function __construct() {
        $db = new Database();
        $this->pdo = $db->getConnection();
    }

    public function getQuotesByFulfillment($fulfillmentId): array {
        $fulfillmentId = Validate::positiveInt($fulfillmentId, 'FulfillmentID');
        $stmt = $this->pdo->prepare("SELECT * FROM shipping_quote WHERE FulfillmentID = ? ORDER BY QuotedFee ASC");
        $stmt->execute([$fulfillmentId]);
        return $stmt->fetchAll();
    }

    private function checkFulfillmentOpen(int $fulfillmentId): void {
        $stmt = $this->pdo->prepare("SELECT PackageStatus FROM fulfillment WHERE FulfillmentID = ?");
        $stmt->execute([$fulfillmentId]);
        $f = $stmt->fetch();
        if (!$f) {
            throw new OutOfBoundsException("Fulfillment not found");
        }
        if (in_array($f['PackageStatus'], ['Handed to Courier', 'Delivered'], true)) {
            throw new InvalidArgumentException("Quotes cannot change after the package is handed to the courier.");
        }
    }

    // Saves one quote per courier (fee and estimated days).
    public function create(array $data): int {
        $fulfillmentId = Validate::positiveInt($data['FulfillmentID'] ?? null, 'FulfillmentID');
        $courierId     = Validate::positiveInt($data['CourierID'] ?? null, 'CourierID');
        $fee           = Validate::money($data['QuotedFee'] ?? null, 'QuotedFee');
        $days          = Validate::positiveInt($data['EstimatedDeliveryDays'] ?? null, 'EstimatedDeliveryDays');
        if ($days > 365) {
            throw new InvalidArgumentException("EstimatedDeliveryDays is too large.");
        }

        $this->checkFulfillmentOpen($fulfillmentId);

        $stmt = $this->pdo->prepare(
            "INSERT INTO shipping_quote (FulfillmentID, CourierID, QuotedFee, EstimatedDeliveryDays) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$fulfillmentId, $courierId, $fee, $days]);
        return (int)$this->pdo->lastInsertId();
    }

    // Saves several quotes at once (all or none). Used by POST quotes.php?action=request
    public function createMany($fulfillmentId, $quotes): array {
        if (!is_array($quotes) || count($quotes) === 0 || count($quotes) > 20) {
            throw new InvalidArgumentException("Quotes must be a list of 1 to 20 quotes.");
        }
        $this->pdo->beginTransaction();
        try {
            $ids = [];
            foreach ($quotes as $q) {
                if (!is_array($q)) {
                    throw new InvalidArgumentException("Each quote must be an object.");
                }
                $q['FulfillmentID'] = $fulfillmentId;
                $ids[] = $this->create($q);
            }
            $this->pdo->commit();
            return $ids;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    // Only one quote is selected at a time. The chosen fee is copied to fulfillment.ShippingFee.
    // $fulfillmentId is optional: it is looked up from the quote itself.
    public function selectQuote($quoteId, $fulfillmentId = null): void {
        $quoteId = Validate::positiveInt($quoteId, 'QuoteID');
        if ($fulfillmentId !== null) {
            $fulfillmentId = Validate::positiveInt($fulfillmentId, 'FulfillmentID');
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT FulfillmentID, QuotedFee FROM shipping_quote WHERE QuoteID = ? FOR UPDATE");
            $stmt->execute([$quoteId]);
            $quote = $stmt->fetch();
            if (!$quote || ($fulfillmentId !== null && (int)$quote['FulfillmentID'] !== $fulfillmentId)) {
                throw new OutOfBoundsException("Quote not found or does not belong to this fulfillment.");
            }
            $fid = (int)$quote['FulfillmentID'];

            $this->checkFulfillmentOpen($fid);

            $this->pdo->prepare("UPDATE shipping_quote SET IsSelected = 0 WHERE FulfillmentID = ?")->execute([$fid]);
            $this->pdo->prepare("UPDATE shipping_quote SET IsSelected = 1 WHERE QuoteID = ?")->execute([$quoteId]);
            $this->pdo->prepare("UPDATE fulfillment SET ShippingFee = ? WHERE FulfillmentID = ?")
                      ->execute([$quote['QuotedFee'], $fid]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
