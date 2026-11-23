<?php
// Tells Module 8 (Shipping and Delivery) to create the shipment record.
// It never throws: a Module 8 problem must not undo the seller's handover.
class ShippingNotifier {
    public static function createShipment(array $fulfillment, ?array $quote): void {
        $fid = (int)($fulfillment['FulfillmentID'] ?? 0);
        try {
            // Module 8 needs a CourierID, and the only place it comes from is the selected quote.
            if (!$quote) {
                error_log("Module 8 shipment skipped for Fulfillment #$fid: no courier quote is selected.");
                return;
            }

            require_once __DIR__ . '/../config/loader.php';
            $config = kph_config();
            $baseUrl = rtrim($config['modules']['shipping'] ?? '', '/');
            if ($baseUrl === '') {
                return;
            }

            // Ship date is the real handover date. The estimate uses the selected quote's days.
            $shipDate = substr((string)($fulfillment['HandoverToCourierAt'] ?? date('Y-m-d')), 0, 10);
            $days = max(1, (int)$quote['EstimatedDeliveryDays']);

            $payload = [
                'FulfillmentID'         => $fid,
                'CourierID'             => (int)$quote['CourierID'],
                'TrackingNumber'        => 'KPH-' . date('Y') . '-' . str_pad((string)$fid, 4, '0', STR_PAD_LEFT),
                'ShipDate'              => $shipDate,
                'EstimatedDeliveryDate' => date('Y-m-d', strtotime($shipDate . " +$days days")),
            ];

            $headers = [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Test-User-Id: 1',
            ];
            if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
                $headers[] = 'Authorization: ' . $_SERVER['HTTP_AUTHORIZATION'];
            }

            $ch = curl_init($baseUrl . '/shipments.php');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $res = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 201) {
                error_log("Failed to create shipment in Module 8 for Fulfillment #$fid (HTTP $httpCode): " . (string)$res);
            }
        } catch (Throwable $e) {
            error_log("Module 8 shipment trigger error for Fulfillment #$fid: " . $e->getMessage());
        }
    }
}
