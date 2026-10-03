# User Guide — Classy Fashion Hub Online Shop

Live locally at `http://localhost:8000` (shop) and `http://localhost:8000/admin`.

## Customer

### Browse and search
- Open the shop. Categories: Mens (`/mens`), Womens (`/womens`), Footwear
  (`/footwear` — under Mens). Use search for names like “jacket”.
- Prices are fixed in Uganda Shillings (UGX) — no bargaining.

### Order
1. Open a product, pick **size and colour**, add to cart.
2. Register (`/customer/register`) or log in
   (`customer@classy.local / customer123` for the demo account).
3. At checkout, fill the billing address. For delivery, fill the shipping
   address: location (address, city), contact phone, and **delivery
   instructions** (e.g. gate, landmark — shipping form only).
4. Choose shipping (Flat Rate) and payment:
   - **Cash on Delivery** — order is created immediately as Pending.
   - **Mobile Money (MTN / Airtel)** — choose network, enter your mobile
     money number, then **approve the prompt on your phone**. The order
     appears only after the network confirms payment.

### Pay with mobile money (demo)
1. Sandbox keys must be in `store/.env` (see README / .env.example).
2. Check out with Mobile Money → approve on the phone → use
   **“I Have Approved — Check Status”**. The order shows up with status
   **Paid** once confirmed. Returning from any payment page without
   approving creates **no order** — your cart is kept.

### Track an order
- Account → Orders shows every order with its live status:
  Pending → Confirmed → Paid → Processing → Dispatched → Delivered.
- Click an order for items, totals and delivery details.

### Shop assistant (`/assistant`)
- Ask about products (“green dresses under 100000”), prices, sizes,
  delivery, MTN/Airtel payments, returns, or “where is my order?”
  (logged in). Works without any setup; with a free Groq key in `.env`
  (`AI_LLM_*`, see `.env.example`) it also handles open questions.

## Worker (`worker@classy.local / worker123`)

- Dashboard, catalogue, sales orders, customer list, sales reports.
- **Confirm** new orders (Pending → Confirmed) with the “Mark as …” buttons
  on the order view; mark **Paid** when mobile money confirms; create the
  invoice when packing (→ Processing); create the shipment (→ Dispatched);
  mark **Delivered** on delivery.
- Update stock on the product edit page (inventories). Every change is
  logged with your name. Low-stock products (≤ 5 units) appear red on the
  dashboard threshold widget.
- On the order view, optionally set the **rider / delivery partner** in
  the Save Rider box (recorded in the audit trail).
- You cannot open Settings, Roles/Users or Configuration (401), and you
  cannot delete products — deactivate them instead (Status toggle).

## Admin (`admin@example.com / admin123` — change after handover)

- Everything the worker can do, plus: users and roles
  (`/admin/settings/roles`), taxes, configuration
  (Configuration → Catalog → Inventory → threshold; Sales → Payment Methods
  → Mobile Money display settings), reports with date ranges and CSV export
  (`/admin/reporting/sales`), dashboard statistics.
- Order view shows per-item and total **profit** (cost = 60% of price demo
  data — enter real costs on each product), delivery instructions, payment
  attempts (pending/success/failed with gateway references are stored per
  transaction), and the audit trail records who changed stock and statuses.
