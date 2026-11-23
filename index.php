<?php
// When this module is hosted at the root of a site (for example an Azure App Service),
// opening the bare address sends the visitor to the seller work list.
header('Location: web/seller-orders.html');
exit;
