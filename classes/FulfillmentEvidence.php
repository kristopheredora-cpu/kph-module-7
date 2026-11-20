<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/Validate.php';

class FulfillmentEvidence {
    private PDO $pdo;

    // Proposed list. Agree on the final words with your group and Groups 8 and 9.
    public const TYPES = ['Packing Photo', 'Packing Video', 'Handover Receipt', 'Other'];

    public function __construct() {
        $db = new Database();
        $this->pdo = $db->getConnection();
    }

    public function getByFulfillmentId($fulfillmentId): array {
        $fulfillmentId = Validate::positiveInt($fulfillmentId, 'FulfillmentID');
        $stmt = $this->pdo->prepare("SELECT * FROM fulfillment_evidence WHERE FulfillmentID = ? ORDER BY UploadedAt");
        $stmt->execute([$fulfillmentId]);
        return $stmt->fetchAll();
    }

    // Only http(s) links or a safe /uploads/... path. Blocks javascript: and similar links,
    // which become a security problem once a web page shows the link or image.
    private function validUrl($url): string {
        $url = Validate::text($url, 'FileURL', 500);
        $isWebLink = filter_var($url, FILTER_VALIDATE_URL) !== false
                     && preg_match('#^https?://#i', $url) === 1;
        $isUploadPath = preg_match('#^/uploads/[A-Za-z0-9._/-]+$#', $url) === 1 && strpos($url, '..') === false;
        if (!$isWebLink && !$isUploadPath) {
            throw new InvalidArgumentException("FileURL must be an http(s) link or a path starting with /uploads/.");
        }
        return $url;
    }

    public function create(array $data): int {
        $fulfillmentId = Validate::positiveInt($data['FulfillmentID'] ?? null, 'FulfillmentID');
        $type = Validate::text($data['EvidenceType'] ?? null, 'EvidenceType', 100);
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("EvidenceType must be one of: " . implode(', ', self::TYPES));
        }
        $url = $this->validUrl($data['FileURL'] ?? null);

        $check = $this->pdo->prepare("SELECT 1 FROM fulfillment WHERE FulfillmentID = ?");
        $check->execute([$fulfillmentId]);
        if (!$check->fetch()) {
            throw new OutOfBoundsException("Fulfillment not found");
        }

        $stmt = $this->pdo->prepare("INSERT INTO fulfillment_evidence (FulfillmentID, EvidenceType, FileURL) VALUES (?, ?, ?)");
        $stmt->execute([$fulfillmentId, $type, $url]);
        return (int)$this->pdo->lastInsertId();
    }
}
