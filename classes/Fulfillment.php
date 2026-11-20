<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/ShippingNotifier.php';

class Fulfillment {
    private PDO $pdo;

    // The agreed package-status flow. Share these EXACT words with Groups 6, 8 and 9.
    public const STATUS_FLOW = [
        'To Pack'           => ['Packed'],
        'Packed'            => ['Handed to Courier'],
        'Handed to Courier' => ['Delivered'],
        'Delivered'         => [],
    ];

    // Funds: Held -> Released, or Held -> Disputed (on hold, waiting for an admin)
    private const ROLES = ['Seller', 'Buyer', 'Courier', 'Admin', 'System'];
    private const AUTO_RELEASE_DAYS = 3;

    public function __construct() {
        $db = new Database();
        $this->pdo = $db->getConnection();
    }

    // ---------- small private helpers ----------

    private function positiveInt($value, string $label): int {
        if (is_bool($value)
            || filter_var($value, FILTER_VALIDATE_INT) === false
            || (int)$value < 1
            || (int)$value > 4294967295) {
            throw new InvalidArgumentException("$label must be a positive whole number.");
        }
        return (int)$value;
    }

    private function validDate($value, string $label): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException("$label is required (format: YYYY-MM-DD HH:MM:SS).");
        }
        $d = DateTime::createFromFormat('Y-m-d H:i:s', $value);
        if (!$d || $d->format('Y-m-d H:i:s') !== $value) {
            throw new InvalidArgumentException("$label must look like YYYY-MM-DD HH:MM:SS.");
        }
        return $value;
    }

    private function cleanNotes($notes): ?string {
        if ($notes === null || $notes === '') {
            return null;
        }
        if (!is_string($notes)) {
            throw new InvalidArgumentException("Notes must be text.");
        }
        if (mb_strlen($notes) > 1000) {
            throw new InvalidArgumentException("Notes must be 1000 characters or fewer.");
        }
        return $notes;
    }

    private function checkRole($role): string {
        if (!is_string($role) || !in_array($role, self::ROLES, true)) {
            throw new InvalidArgumentException("Invalid role. Allowed: " . implode(', ', self::ROLES));
        }
        return $role;
    }

    private function addHistory(int $id, string $status, string $role, ?string $notes = null): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO fulfillment_status_history (FulfillmentID, Status, ChangedByRole, Notes)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$id, $status, $role, $notes]);
    }

    // Must be called inside a transaction. Locks the row so two requests cannot change it at once.
    private function lockRow(int $id): array {
        $stmt = $this->pdo->prepare("SELECT * FROM fulfillment WHERE FulfillmentID = ? FOR UPDATE");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new OutOfBoundsException("Fulfillment not found");
        }
        return $row;
    }

    private function inTransaction(callable $work) {
        $this->pdo->beginTransaction();
        try {
            $result = $work();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    // ---------- reads ----------

    public function getById($id): array {
        $id = $this->positiveInt($id, 'FulfillmentID');
        $stmt = $this->pdo->prepare("SELECT * FROM fulfillment WHERE FulfillmentID = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new OutOfBoundsException("Fulfillment not found");
        }
        return $row;
    }

    // One fulfillment with its items, status history and the selected quote (what GET ?id= returns)
    public function getDetails($id): array {
        $row = $this->getById($id);

        $stmt = $this->pdo->prepare(
            "SELECT FulfillmentItemID, OrderItemID, Quantity FROM fulfillment_item WHERE FulfillmentID = ?"
        );
        $stmt->execute([$row['FulfillmentID']]);
        $row['items'] = $stmt->fetchAll();
        $row['history'] = $this->getHistory((int)$row['FulfillmentID']);
        $q = $this->pdo->prepare("SELECT QuoteID, CourierID, QuotedFee, EstimatedDeliveryDays FROM shipping_quote WHERE FulfillmentID = ? AND IsSelected = 1 LIMIT 1");
        $q->execute([$row['FulfillmentID']]);
        $row['selected_quote'] = $q->fetch() ?: null;
        return $row;
    }

    public function getHistory(int $id): array {
        $stmt = $this->pdo->prepare(
            "SELECT Status, ChangedAt, ChangedByRole, Notes
             FROM fulfillment_status_history WHERE FulfillmentID = ? ORDER BY FulfillmentStatusID"
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    // A seller's work list, optionally filtered by package status
    public function listBySeller($sellerId, ?string $status = null): array {
        $sellerId = $this->positiveInt($sellerId, 'seller_id');
        $sql = "SELECT * FROM fulfillment WHERE SellerID = ?";
        $params = [$sellerId];
        if ($status !== null && $status !== '') {
            if (!array_key_exists($status, self::STATUS_FLOW)) {
                throw new InvalidArgumentException("Invalid package status: " . $status);
            }
            $sql .= " AND PackageStatus = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY ShipByDate ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Past the ship-by date and not yet handed to a courier
    public function listLate(): array {
        $stmt = $this->pdo->query(
            "SELECT * FROM fulfillment
             WHERE ShipByDate < CURRENT_TIMESTAMP
               AND HandoverToCourierAt IS NULL
             ORDER BY ShipByDate ASC"
        );
        return $stmt->fetchAll();
    }

    // ---------- create ----------

    // One fulfillment per seller per order. Funds start as Held.
    public function create(array $data, string $changedByRole = 'System'): int {
        $orderId  = $this->positiveInt($data['OrderID'] ?? null, 'OrderID');
        $sellerId = $this->positiveInt($data['SellerID'] ?? null, 'SellerID');
        $pickupId = $this->positiveInt($data['PickupAddressID'] ?? null, 'PickupAddressID');
        $shipBy   = $this->validDate($data['ShipByDate'] ?? null, 'ShipByDate');
        $role     = $this->checkRole($changedByRole);

        // The shipping fee may start at 0 and be set when a quote is selected.
        $fee = $data['ShippingFee'] ?? 0;
        if (!is_numeric($fee) || (float)$fee < 0 || (float)$fee > 9999999999.99) {
            throw new InvalidArgumentException("ShippingFee must be a number from 0 up.");
        }
        $fee = round((float)$fee, 2);

        return $this->inTransaction(function () use ($orderId, $sellerId, $pickupId, $shipBy, $fee, $role) {
            // Block duplicates: the same seller cannot have two fulfillments for one order.
            $dup = $this->pdo->prepare(
                "SELECT FulfillmentID FROM fulfillment WHERE OrderID = ? AND SellerID = ? LIMIT 1 FOR UPDATE"
            );
            $dup->execute([$orderId, $sellerId]);
            if ($dup->fetch()) {
                throw new InvalidArgumentException("A fulfillment already exists for this order and seller.");
            }

            $stmt = $this->pdo->prepare(
                "INSERT INTO fulfillment
                 (OrderID, SellerID, PickupAddressID, PackageStatus, ShipByDate, ShippingFee, FundsStatus)
                 VALUES (?, ?, ?, 'To Pack', ?, ?, 'Held')"
            );
            $stmt->execute([$orderId, $sellerId, $pickupId, $shipBy, $fee]);
            $newId = (int)$this->pdo->lastInsertId();

            $this->addHistory($newId, 'To Pack', $role, 'Fulfillment created');
            return $newId;
        });
    }

    // ---------- package status ----------

    public function changePackageStatus($id, $newStatus, $changedByRole, $notes = null): void {
        $id    = $this->positiveInt($id, 'FulfillmentID');
        $role  = $this->checkRole($changedByRole);
        $notes = $this->cleanNotes($notes);

        if (!is_string($newStatus) || !array_key_exists($newStatus, self::STATUS_FLOW)) {
            throw new InvalidArgumentException("Invalid package status: " . (is_string($newStatus) ? $newStatus : ''));
        }

        $this->inTransaction(function () use ($id, $newStatus, $role, $notes) {
            $current = $this->lockRow($id);

            // Only the next step in the flow is allowed.
            $allowed = self::STATUS_FLOW[$current['PackageStatus']] ?? [];
            if (!in_array($newStatus, $allowed, true)) {
                $next = $allowed ? implode(' or ', $allowed) : 'nothing (this is the last step)';
                throw new InvalidArgumentException(
                    "Cannot change status from '{$current['PackageStatus']}' to '$newStatus'. Next allowed: $next."
                );
            }

            $sql = "UPDATE fulfillment SET PackageStatus = ?";
            if ($newStatus === 'Packed') {
                $sql .= ", PackedAt = CURRENT_TIMESTAMP";
            } elseif ($newStatus === 'Handed to Courier') {
                $sql .= ", HandoverToCourierAt = CURRENT_TIMESTAMP";
            } elseif ($newStatus === 'Delivered') {
                // Starts the 3-day timer. Without this, auto-release can never happen.
                $sql .= ", AutoReleaseAt = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL " . self::AUTO_RELEASE_DAYS . " DAY)";
            }
            $sql .= " WHERE FulfillmentID = ?";

            $this->pdo->prepare($sql)->execute([$newStatus, $id]);
            $this->addHistory($id, $newStatus, $role, $notes);

            
        });

        if ($newStatus === 'Handed to Courier') {
            $q = $this->pdo->prepare("SELECT CourierID, EstimatedDeliveryDays FROM shipping_quote WHERE FulfillmentID = ? AND IsSelected = 1 LIMIT 1");
            $q->execute([$id]);
            ShippingNotifier::createShipment($this->getById($id), $q->fetch() ?: null);
        }
    }

    // ---------- money ----------

    // Buyer confirms receipt: save BuyerConfirmedAt and release. Never twice.
    public function confirmReceipt($id, string $changedByRole = 'Buyer'): void {
        $id   = $this->positiveInt($id, 'FulfillmentID');
        $role = $this->checkRole($changedByRole);

        $this->inTransaction(function () use ($id, $role) {
            $current = $this->lockRow($id);

            if ($current['FundsStatus'] === 'Released') {
                throw new InvalidArgumentException("Funds have already been released for this fulfillment.");
            }
            if ($current['FundsStatus'] === 'Disputed') {
                throw new InvalidArgumentException("Funds are on hold because of a dispute.");
            }
            if ($current['PackageStatus'] !== 'Delivered') {
                throw new InvalidArgumentException("The package has not been delivered yet.");
            }

            $stmt = $this->pdo->prepare(
                "UPDATE fulfillment
                 SET FundsStatus = 'Released', BuyerConfirmedAt = CURRENT_TIMESTAMP, ReleasedAt = CURRENT_TIMESTAMP
                 WHERE FulfillmentID = ? AND FundsStatus = 'Held'"
            );
            $stmt->execute([$id]);
            if ($stmt->rowCount() !== 1) {
                throw new InvalidArgumentException("Funds could not be released.");
            }
            $this->addHistory($id, 'Funds Released', $role, 'Buyer confirmed receipt');
        });
    }

    // Module 9 calls this when a dispute is opened. Safe to call twice.
    public function holdForDispute($id, string $changedByRole = 'System'): void {
        $id   = $this->positiveInt($id, 'FulfillmentID');
        $role = $this->checkRole($changedByRole);

        $this->inTransaction(function () use ($id, $role) {
            $current = $this->lockRow($id);

            if ($current['FundsStatus'] === 'Released') {
                throw new InvalidArgumentException("Cannot hold for dispute: funds have already been released.");
            }
            if ($current['FundsStatus'] === 'Disputed') {
                return; // already on hold
            }

            $this->pdo->prepare("UPDATE fulfillment SET FundsStatus = 'Disputed' WHERE FulfillmentID = ? AND FundsStatus = 'Held'")
                      ->execute([$id]);
            $this->addHistory($id, 'Funds On Hold', $role, 'Dispute opened');
        });
    }

    // Releases every delivered fulfillment whose 3 days are over, unless it is disputed.
    // Returns how many were released.
    public function autoReleaseFunds(): int {
        return $this->inTransaction(function () {
            $due = $this->pdo->query(
                "SELECT FulfillmentID FROM fulfillment
                 WHERE FundsStatus = 'Held'
                   AND PackageStatus = 'Delivered'
                   AND AutoReleaseAt IS NOT NULL
                   AND AutoReleaseAt <= CURRENT_TIMESTAMP
                 FOR UPDATE"
            )->fetchAll(PDO::FETCH_COLUMN);

            $update = $this->pdo->prepare(
                "UPDATE fulfillment SET FundsStatus = 'Released', ReleasedAt = CURRENT_TIMESTAMP
                 WHERE FulfillmentID = ? AND FundsStatus = 'Held'"
            );

            $released = 0;
            foreach ($due as $fid) {
                $update->execute([$fid]);
                if ($update->rowCount() === 1) {
                    $this->addHistory((int)$fid, 'Funds Released', 'System', 'Auto-released 3 days after delivery');
                    $released++;
                }
            }
            return $released;
        });
    }
}
