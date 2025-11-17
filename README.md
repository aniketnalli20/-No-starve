# No Starve

No Starve helps people quickly find available meals in their area — working professionals, bachelors, and students alike. Users can check nearby food availability, connect safely to access meals, and discover affordable or free options while reducing waste.

## Features
- Nearby meal discovery: search and browse food listings and campaigns
- Create campaigns: coordinate local distribution with areas and closing times
- Listings and claims: donors post surplus items, NGOs/volunteers claim them
- Endorsements: track campaign and contributor support
- Wallet and Karma Coins: transparent reward tracking with admin awards
- KYC workflow: secure details collection with an autofill helper for faster entry
- Admin tools: manage Users, Campaigns, Endorsements, Rewards, Contributors, and KYC

## Tech Stack
- PHP with PDO
- MySQL (default) or PostgreSQL via `DB_DRIVER`
- Minimal frontend (HTML/CSS) with Material Symbols

## Quick Start
1. Requirements
   - PHP 8.x
   - MySQL or MariaDB (default) or PostgreSQL
   - Windows users can use XAMPP
2. Clone
   - Place the project under `htdocs` or any folder
   - Example: `C:\xampp\htdocs\No-starve`
3. Configure (optional)
   - Edit `config.php` or set environment variables for database and API keys
4. Run the server on port 8080
   - From the project root:
     ```bash
     php -S 0.0.0.0:8080
     ```
   - Open `http://localhost:8080/`
5. Seeded demo users (auto-created if `users` is empty)
   - `Donor` — `donor@nostrv.com`
   - `NGO Lead` — `ngo@nostrv.com`
   - `Volunteer` — `volunteer@nostrv.com`
   - Password: `demo1234` for all
6. Promote an admin (CLI)
   - Run from the project root:
     ```bash
     php scripts/promote_admin_cli.php --email donor@nostrv.com
     # or
     php scripts/promote_admin_cli.php --username Donor
     ```
   - Then visit `http://localhost:8080/admin/index.php`

## Configuration
Adjust via environment variables or directly in `config.php`:
- `DB_DRIVER` — `mysql` (default) or `pgsql`
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`
- `GEO_PROVIDER` — `locationiq` (default) or `nominatim`
- `GEO_API_KEY` — API key for geolocation when `locationiq` is used
- `PGADMIN_PORT` — optional port hint for local pgAdmin
- `HERO_IMAGE_PATH` — optional path to a local hero image

Notes:
- On first run, the database schema is created automatically and non-breaking migrations are applied.
- Default MySQL settings match typical XAMPP installs: `host=localhost`, `user=root`, empty password, db `foodwastemgmt`.

## KYC and Wallet Access
Wallet access is gated to keep rewards consistent and abuse-resistant:
- Verified user status
- 10k or more followers
- 100k or more Karma Coins
- Approved KYC request

Users not meeting thresholds are redirected to the KYC page. After approval, users who still lack thresholds see a gentle notice to complete the remaining requirements.

## Admin Tools
Accessible at `/admin/index.php` for admin users:
- Users: export, delete, batch cleanup, award coins
- Campaigns: create, update status, generate demo campaigns, map links
- Endorsements: add campaign or contributor endorsements
- Rewards: view wallets and award Karma Coins
- Contributors: mark verified status
- KYC: review and update KYC status and notes

## Development
- Codebase uses plain PHP + PDO (no frameworks, no Composer)
- Styles are in `style.css`; admin sections use the same spacing and full-width layout patterns
- Homepage and dev server run on port `8080`
- Avoid storing secrets in code; prefer environment variables

## Troubleshooting
- Database connection errors list available PDO drivers in the error output
- If using PostgreSQL, ensure the `pgsql` PDO driver is installed; otherwise the app falls back to MySQL
- If XAMPP’s Apache is on port 80, prefer the built-in PHP server on `8080` for development

## License
- Add your chosen license to the repository (e.g., MIT). If unspecified, treat the project as proprietary.