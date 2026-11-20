<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/Validate.php';

class FulfillmentItem {
    private PDO $pdo;

    public function __construct() {
        $db = new Database();
        $this->pdo = $db->getConnection();
    }

    public function getByFulfillmentId($fulfillmentId): array {
        $fulfillmentId = Validate::positiveInt($fulfillmentId, 'FulfillmentID');
        $stmt = $this->pdo->prepare("SELECT * FROM fulfillment_item WHERE FulfillmentID = ?");
        $stmt->execute([$fulfillmentId]);
        return $stmt->fetchAll();
    }

    // TODO (needs Group 6): ask Module 6 how many units of this order item were ordered.
    // Return null while that endpoint does not exist, so this module still works alone.
    private function orderedQuantity(int $orderItemId): ?int {
        return null;
    }

    public function create(array $data): int {
        $fulfillmentId = Validate::positiveInt($data['FulfillmentID'] ?? null, 'FulfillmentID');
        $orderItemId   = Validate::positiveInt($data['OrderItemID'] ?? null, 'OrderItemID');
        $quantity      = Validate::positiveInt($data['Quantity'] ?? null, 'Quantity');

        // The fulfillment must exist and must not have left the seller yet.
        $stmt = $this->pdo->prepare("SELECT PackageStatus FROM fulfillment WHERE FulfillmentID = ?");
        $stmt->execute([$fulfillmentId]);
        $f = $stmt->fetch();
        if (!$f) {
            throw new OutOfBoundsException("Fulfillment not found");
        }
        if (in_array($f['PackageStatus'], ['Handed to Courier', 'Delivered'], true)) {
            throw new InvalidArgumentException("Items cannot be added after the package is handed to the courier.");
        }

        // The same order item cannot be added twice to the same fulfillment.
        $dup = $this->pdo->prepare("SELECT 1 FROM fulfillment_item WHERE FulfillmentID = ? AND OrderItemID = ?");
        $dup->execute([$fulfillmentId, $orderItemId]);
        if ($dup->fetch()) {
            throw new InvalidArgumentException("This order item is already in the fulfillment.");
        }

        // Quantity cannot be more than what was ordered (all fulfillments of this order item together).
        $ordered = $this->orderedQuantity($orderItemId);
        if ($ordered !== null) {
            $sum = $this->pdo->prepare("SELECT COALESCE(SUM(Quantity), 0) FROM fulfillment_item WHERE OrderItemID = ?");
            $sum->execute([$orderItemId]);
            if ((int)$sum->fetchColumn() + $quantity > $ordered) {
                throw new InvalidArgumentException("Quantity is more than what was ordered ($ordered).");
            }
        }

        $ins = $this->pdo->prepare("INSERT INTO fulfillment_item (FulfillmentID, OrderItemID, Quantity) VALUES (?, ?, ?)");
        $ins->execute([$fulfillmentId, $orderItemId, $quantity]);
        return (int)$this->pdo->lastInsertId();
    }
}
