# KameraPH Module 7: Fulfillment and Logistics

**Group 7: Logistics & Fulfillment Preparation**
Edora, Kristopher Angelo E. · Darum, Sean Austin · Dela Rosa, Lheeon Owen

This module covers everything between a paid order and the courier pickup, and it controls when the seller's money is released:

- Fulfillment workflow (one fulfillment per seller per order, package status, history)
- Fulfillment item management
- Shipping quotation (courier quotes, one selected)
- Packing evidence management
- Holding and releasing the payment (buyer confirmation, 3-day auto-release, dispute hold)

Payment and couriers are simulated, as stated in the project proposal. A held payment is a status in the database.

## Links

| What | Local | Live |
| --- | --- | --- |
| Seller work list | http://localhost/kph_fulfillment_logistics/web/seller-orders.html | [add the live address here] |
| Fulfillment detail | http://localhost/kph_fulfillment_logistics/web/fulfillment.html?id=1 | [add the live address here] |
| Admin payment release | http://localhost/kph_fulfillment_logistics/web/admin-release.html | [add the live address here] |
| API base address | http://localhost/kph_fulfillment_logistics/api | [add the live address here] |

## Setup

1. Put the folder `kph_fulfillment_logistics` in `htdocs` (XAMPP) or on the host.
2. Create a database called `kph_fulfillment_logistics`.
3. Import `sql/module_07_fulfillment_and_logistics.sql`. It creates 5 tables and is safe to run twice.
4. Edit `config/config.php`:
   - database host, name, user and password (the only place database settings live)
   - the API addresses of the other modules under `modules` (`orders`, `sellers`, `catalog`, `shipping`)
   - `cron_key`: leave empty in the repository. Set it only on the host (see Scheduled release).
5. Edit `web/js/config.js`: set `api` to this module's API address, and the `modules` list to the live web addresses.
6. Open `web/seller-orders.html`.

On the host: use `https` everywhere, turn off PHP error display, and do not upload any test files.

## Folder structure

```
api/          API files that other modules and our pages call
classes/      Backend classes: all rules and all SQL
config/       config.php (settings) and Database.php (PDO connection)
cron/         auto_release.php (scheduled payment release)
mock/         Sample responses for the other groups
screenshots/  Test evidence
sql/          The 5 tables
web/          Pages (css/module.css holds the extra styles; kph.css is the shared kit, unchanged)
```

## Database (5 tables)

| Table | Purpose |
| --- | --- |
| fulfillment | One seller's share of one order: pickup address, ship-by date, package status, shipping fee, funds status, release timestamps |
| fulfillment_item | Order items and quantities inside a fulfillment |
| shipping_quote | Courier fee and estimated days, with a selected flag |
| fulfillment_evidence | Links to packing photos or videos |
| fulfillment_status_history | Audit trail of every status and funds change, with role and time |

IDs that belong to other modules (OrderID, OrderItemID, SellerID, PickupAddressID, CourierID) are stored as plain numbers with no foreign keys, as the build guide requires.

## Agreed words (please use exactly these)

| Field | Values |
| --- | --- |
| Package status | `To Pack`, `Packed`, `Handed to Courier`, `Delivered` (only the next step is allowed) |
| Funds status | `Held`, `Disputed`, `Released` |
| Roles (`ChangedByRole`) | `Seller`, `Buyer`, `Courier`, `Admin`, `System` |
| Evidence type | `Packing Photo`, `Packing Video`, `Handover Receipt`, `Other` |

## API summary

Full details are in the document **Module 7 API Reference** (PDF). Send the header `X-Test-User-Id` for now.

| Endpoint | What it does | Used by |
| --- | --- | --- |
| POST `fulfillments.php` | Create a fulfillment for one seller in an order | Module 6 |
| GET `fulfillments.php?id=` | One fulfillment with items, history and `selected_quote` | Modules 2, 8, 9 |
| GET `fulfillments.php?seller_id=&status=` | A seller's work list | Seller page |
| GET `fulfillments.php?late=1` | Past ship-by date and not handed over | Seller, Admin |
| PUT `fulfillments.php?id=&action=status` | Change package status | Seller page, Module 8 |
| POST `fulfillments.php?id=&action=confirm-receipt` | Buyer confirms, funds released (after Delivered only) | Buyer |
| POST `fulfillments.php?id=&action=hold` | Pause the release for a dispute | Module 9 |
| POST `fulfillments.php?action=auto-release` | Release everything due | Admin, scheduler |
| POST `items.php`, GET `items.php?fulfillment_id=` | Add and list items | Module 6 |
| POST `quotes.php?action=request`, GET `quotes.php?fulfillment_id=`, PUT `quotes.php?id=&action=select` | Save, list and select courier quotes | Module 6 |
| POST `evidence.php`, GET `evidence.php?fulfillment_id=` | Add and list packing evidence | Seller page |

Money comes back as text with two decimals (for example `150.00`). Dates are `YYYY-MM-DD HH:MM:SS`. Errors are `{"error": "message"}` with the status codes 400, 401, 404, 405 and 500.

## How this module connects to the others

| Module | Direction | What happens |
| --- | --- | --- |
| 6, Order and Payment | calls us | At checkout: create a fulfillment per seller, add items, request and select a courier quote. |
| 8, Shipping and Delivery | we call them | When the seller clicks Handed to Courier, we POST `shipments.php` (FulfillmentID, CourierID from the selected quote, TrackingNumber, ShipDate, EstimatedDeliveryDate). If no quote is selected, or Module 8 is down, the call is skipped or logged and the handover still works. |
| 8, Shipping and Delivery | they call us | On delivery they send PUT `fulfillments.php?id=&action=status` with `PackageStatus` `Delivered` and `ChangedByRole` `Courier`. This starts the 3-day release timer. |
| 9, Post-Purchase Support | calls us | POST `fulfillments.php?id=&action=hold` when a dispute is opened. |
| 2, Seller and Verification | we should call them | When funds are released, so a payout can be made (not built yet). |
| 1, User Account and Identity | data source | The pickup address (`PickupAddressID`) and, later, login. |

## Scheduled release

Payments are released automatically 3 days after delivery unless disputed.

- Command line: `php cron/auto_release.php`
- Web (hosts without cron): `cron/auto_release.php?key=YOUR_SECRET`, which needs `cron_key` set in `config/config.php`. A wrong or missing key gives 403.
- Run it once an hour or once a day.
- Admins can also use the button on `web/admin-release.html`.

## Testing done

Backend tests (a test page covering saving, bad input, injection text, status order, double release, disputes and auto-release) and every API endpoint with Thunder Client (success and error cases). Evidence is in `screenshots/`. The handover trigger to Module 8 was tested with Module 8 offline and with no quote selected. A live test with Module 8 running is still to be done.

## Not done yet / needs a decision

- Quantity cannot yet be checked against what was ordered. It needs an endpoint from Module 6 (placeholder in `classes/FulfillmentItem.php`).
- Ending a dispute (release to the seller or refund the buyer) needs Group 9.
- Telling Module 2 (payout) and Module 6 (order status) when funds are released is not built.
- Login is a test header. When the session token format is decided, only `api/helpers.php` changes. Ownership checks and admin-only access wait for that.
- There is no Cancelled status.
- Who calculates the courier quotes is undecided. This module only saves the quotes it is given.
- Evidence is stored as links only. There is no file upload.
- **Request for the leader:** please approve a unique index on `OrderID` and `SellerID` in the `fulfillment` table. The code blocks duplicates already, but the database should too.
- **Note on the UI Kit:** the shared top bar says "1 Year Warranty" and "100% Authentic Product", which contradicts the proposal's rule that KameraPH offers no warranty of its own.

## Notes for maintainers

- Only `config/config.php` holds database settings. Never put real passwords in the repository.
- No SQL and no business rules in `api/` files. They belong in `classes/`.
- Delete `web/kit-demo.html`, `web/example-*.html`, `web/partials/` and any test files before uploading to the host.
- If you reset the tables, the IDs restart at 1. Module 8's tracking numbers are built from the fulfillment ID, so clear their shipments too, or they will refuse the repeated numbers.
