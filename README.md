# FoodOrder

An online food ordering system built in PHP for a Web and Cloud Technology final project. One application serves three roles from the same database: customers ordering food, kitchen staff managing the order queue, and admins running the menu and tracking revenue.

## Features

**Customer**
- Register and log in
- Browse a categorized menu
- Add items to a cart that persists across sessions (capped at 20 per item)
- Check out with a choice of delivery or pickup and payment method
- Track an order's status live
- Change their own password, or reach an admin on Telegram if they've forgotten it

**Staff**
- View a live queue of active orders, oldest first
- Update an order's status in one tap
- Change their own password, or reach an admin on Telegram if they've forgotten it

**Admin**
- Dashboard with revenue, order volume, and top-selling items
- Add, edit, and disable menu items and categories
- Manage user accounts and roles
- Activate, deactivate, or unlock user accounts
- Change their own password

## Tech stack

- **Backend:** PHP 8, procedural style, session-based authentication
- **Database:** MySQL / MariaDB via the `mysqli` extension
- **Frontend:** Bootstrap 5, server-rendered HTML
- **Environment:** Apache (tested locally with XAMPP/WAMP-style setups)

## Project structure

```
foodorder/
├── README.md
├── docs/
│   ├── FoodOrder_Report.docx
│   ├── FoodOrder_Presentation.pptx
│   ├── FoodOrder_Presentation_Script.docx
│   └── FoodOrder_QnA.docx
├── index.php               # Landing page
├── auth/                   # Login, registration, logout
├── account/                  # Change password (any logged-in role)
├── customer/                # Menu, cart, checkout, order tracking
├── staff/                   # Order queue and status updates
├── admin/                   # Dashboard, menu/category/user management
├── includes/
│   ├── functions.php        # Session hardening, DB connection, shared helpers
│   ├── auth_check.php       # Role guard included on every protected page
│   ├── header.php / navbar.php / footer.php
├── config/
│   └── db.php                # Database connection settings
└── database/
    └── schema.sql             # Table definitions and seed data
```

Every protected page includes `auth_check.php` before any other code runs. It checks the visitor's session and role, and redirects them if they don't belong on that page. `functions.php` runs on every page, protected or not, and sets up the hardened session and database connection.

## Database

Eight tables, split between live operational data and an audit trail:

| Table | Purpose |
|---|---|
| `users` | Accounts for all three roles, including active/inactive status |
| `categories` | Menu groupings |
| `menu_items` | Orderable items, each in a category |
| `cart` | What a logged-in customer currently intends to order |
| `orders` | One row per placed order |
| `order_items` | A snapshot of what was in an order, decoupled from the live menu |
| `order_status_log` | Audit trail of every status change on an order |
| `login_attempts` | Failed login tracking, used for lockout |

`order_items` stores the item's name and price at the time of the order rather than a live reference to `menu_items`. That way, past orders stay accurate even if a price changes or the item is later removed.

## Setup

1. Copy the project folder into your web server's document root, for example `htdocs/foodorder` for XAMPP.
2. Create the database and load the schema:
   ```
   mysql -u root -p < database/schema.sql
   ```
   This creates the `food_ordering_system` database and seeds it with sample categories, menu items, and three demo accounts.
3. Check `config/db.php` and update the host, username, and password if your MySQL setup differs from the defaults (`localhost`, `root`, no password).
4. Start Apache and MySQL, then visit `http://localhost/foodorder/` in a browser.

## Demo accounts

Seeded by `schema.sql`, all with the password `password123`:

| Username | Role |
|---|---|
| `admin1` | admin |
| `staff1` | staff |
| `customer1` | customer |

## Security

- Session cookies are set `HttpOnly`, `SameSite=Lax`, and `Secure` over HTTPS.
- Every form that changes data carries a CSRF token, checked with a constant-time comparison before the request is processed.
- Failed logins are rate-limited: five failed attempts locks a username for five minutes.
- Uploaded images are validated by their actual file content, not just the file extension.
- Every response carries `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`, and `Referrer-Policy` headers.

## Known limitations

- **Passwords are stored in plaintext**, not hashed. This was not addressed before the project deadline. The fix is to hash on registration with `password_hash` and verify with `password_verify` instead of a direct comparison.
- SQL injection protection relies on escaping user input with `mysqli_real_escape_string` rather than prepared statements. Prepared statements are the safer approach and would be the next thing to change.
- There's no stock quantity tracking, only an available/unavailable flag on each menu item. Two customers could order the same item at the same moment with nothing to prevent it.
- Order tracking and the staff queue are not real-time. Both are server-rendered pages that show the current state on load or refresh, not a live push.

## Team

Built by a team of five for a Web and Cloud Technology course project.

- Tiengchiev Taing – Full Development: backend development, database design, API integration, debugging, testing, and overall system implementation
- Kimchheang Chhon – Introduction, Objectives & System Architecture: project overview, objectives, and system architecture
- Sok Heng Kim – Database Design & Core Features: database schema, relationships, and core system features
- Chhayheng Kong – Access Control & Security: role-based access control, authentication, authorization, and security measures
- Chhoyrothnak Ly – Technology Stack, Challenges & Conclusion: technology stack, project challenges, solutions, and overall conclusion
