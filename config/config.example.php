<?php
// TEMPLATE. Copy this file to config.php and fill in the password.
// config.php is in .gitignore and must never be pushed to GitHub.
return [
    // Unified Azure MySQL database shared by all 10 modules
    "host"    => "kamaera-ph-mysql.mysql.database.azure.com",
    "port"    => 3306,
    "dbname"  => "kph-database",
    "user"    => "kph_app",
    "pass"    => "PUT_THE_PASSWORD_FROM_YOUR_LEADER_HERE",
    "charset" => "utf8mb4",
    // "ssl_ca" => "C:/xampp/certs/DigiCertGlobalRootG2.crt.pem",   // only if Azure refuses the connection

    // Local XAMPP fallback (uncomment only to test offline):
    // "host" => "localhost", "dbname" => "kph-database", "user" => "root", "pass" => "",

    // Secret for cron/auto_release.php?key=... Leave empty here. Set it only on the host.
    "cron_key" => "",

    "modules" => [
        "accounts"    => "http://localhost/kph_user_account_identity/api",
        "sellers"     => "http://localhost/kph_seller_verification/api",
        "catalog"     => "http://localhost/kph_catalog_specification/api",
        "engagement"  => "http://localhost/kph_marketing_engagement/api",
        "shopping"    => "http://localhost/kph_shopping_promotions/api",
        "orders"      => "http://localhost/kph_order_payment/api",
        "fulfillment" => "http://localhost/kph_fulfillment_logistics/api",
        "shipping"    => "http://localhost/kph_shipping_delivery/api",
        "support"     => "http://localhost/kph_post_purchase_support/api",
        "admin"       => "http://localhost/kph_administration_analytics/api",
    ],
];
