<?php
return [
    "host" => "localhost",
    "dbname" => "kph_fulfillment_logistics",
    "user" => "root",
    "pass" => "",
    "modules" => [
        "cron_key" => "",
        "orders" => "http://localhost/kph_order_payment/api",
        "sellers" => "http://localhost/kph_seller_verification/api",
        "catalog" => "http://localhost/kph_catalog_specification/api",
        "shipping" => "http://localhost/kph_shipping_delivery/api"
    ],
];