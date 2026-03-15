# Command Deck

A self-hosted homelab dashboard with password protection, live status checks, and a built-in network scanner.

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-blue)

## Features

- **Password-protected** — PHP session-based login, no nginx/Apache config needed
- **Live status checks** — Server-side curl pings all your services on page load (no CORS issues)
- **Network scanner** — Scans your subnet for devices with open ports, shows detected services
- **Add from scan** — Click "+ Add" next to any discovered device to track it
- **Edit / Delete** — Every resource card is editable inline
- **JSON storage** — Resources stored in a single JSON file, easy to back up or edit by hand
- **Dark theme** — Clean and functional, not flashy

## Requirements

- PHP 8.0+ with `curl` and `json` extensions
- A web server (Nginx, Apache, etc.) with PHP-FPM
- Works great on HestiaCP, CyberPanel, or any shared hosting with PHP

## Quick Start

1. **Clone or download** this repo into your web root:
   ```bash
   git clone https://github.com/youruser/command-deck.git
   cd command-deck
   ```

2. **Create your config** from the example:
   ```bash
   cp public_html/config.example.php public_html/config.php
   ```

3. **Edit `public_html/config.php`** — set your password, subnet, and branding:
   ```php
   define('SITE_TITLE', 'My Dashboard');
   define('SITE_SUBTITLE', 'Home network control');
   define('AUTH_PASSWORD', 'your-secure-password');
   define('DEFAULT_SUBNET', '192.168.1');
   ```

4. **Create your resource list** from the example:
   ```bash
   mkdir -p public_html/.data
   cp data/resources.example.json public_html/.data/resources.json
   ```

5. **Set permissions** so PHP can read/write the data file:
   ```bash
   chown -R www-data:www-data public_html/.data/
   chmod 755 public_html/.data/
   chmod 644 public_html/.data/resources.json
   ```

6. **Point your web server** at `public_html/` as the document root.

7. **Visit the site** — you'll see the login screen.

## File Structure

```
command-deck/
├── .gitignore
├── README.md
├── data/
│   └── resources.example.json   ← Example data (tracked)
└── public_html/                 ← Web root
    ├── .data/
    │   └── resources.json       ← YOUR data (git-ignored, created at runtime)
    ├── config.example.php       ← Example config (tracked)
    ├── config.php               ← YOUR config (git-ignored)
    ├── index.php                ← Login gate + dashboard HTML
    ├── api.php                  ← REST API (CRUD, status, scanner)
    ├── robots.txt               ← Blocks all crawlers
    └── assets/
        ├── css/styles.css
        └── js/app.js
```

## Configuration

All settings live in `config.php`. Key options:

| Constant | Purpose | Default |
|----------|---------|---------|
| `SITE_TITLE` | Dashboard heading | `Command Deck` |
| `SITE_SUBTITLE` | Tagline under the title | `Your homelab control panel` |
| `AUTH_PASSWORD` | Login password | `changeme` |
| `SESSION_TIMEOUT` | Auto-logout after N seconds | `28800` (8 hrs) |
| `DEFAULT_SUBNET` | Pre-filled subnet for scanner | `192.168.1` |
| `SCAN_PORTS` | Ports the scanner checks | Common homelab ports |
| `CATEGORIES` | Dropdown options for resources | Infrastructure, Media, AI, etc. |

## Adding Resources

Three ways:

1. **Dashboard UI** — Click "+ Add", fill out the form
2. **Network Scanner** — Go to the Scanner tab, scan your subnet, click "+ Add" on discovered devices
3. **Edit JSON** — Open `public_html/.data/resources.json` directly (useful for bulk imports)

## License

MIT — use it however you want.
