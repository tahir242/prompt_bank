# System Prompt Bank

A modern web application for managing system prompts with version history, sharing controls, and collaborative editing.

![License](https://img.shields.io/badge/license-MIT-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)
![SQLite](https://img.shields.io/badge/SQLite-3-blue.svg)

## Overview

System Prompt Bank helps teams organize and share system prompts with a clean SPA interface, rich markdown editing, and a full audit trail of prompt changes.

## Features

- Prompt CRUD with versioning and diff comparison
- Markdown editor with live preview
- Category organization and search
- Role-based access control (admin/editor/viewer)
- Prompt sharing with user/team permissions
- Access request workflow and collaboration presence
- Public prompt access with optional anonymous sharing

## Quick Start

### Prerequisites

- PHP 7.4+ with SQLite enabled
- Web server (Apache/Nginx) or PHP built-in server
- Modern browser

### Setup

1. Place the project under your web root, for example:

```
c:\xampp\htdocs\prompt_bank\
```

2. Initialize the database:

```
http://localhost/prompt_bank/database/init_db.php
```

3. Open the app:

```
http://localhost/prompt_bank/
```

4. Default login:

```
Username: admin
Password: admin123
```

Change the default credentials after first login.

## Configuration

- Update [config.php](config.php) for environment-specific settings.
- The SQLite database is created at `database/prompts.db` after initialization.

## Project Structure

```
prompt_bank/
├── api/                 # REST endpoints
├── assets/              # CSS and JS
├── database/            # DB setup and migrations
├── templates/           # HTML fragments
├── tests/               # PHP tests
├── index.php            # App entry
└── config.php           # Configuration
```

## API Overview

Authentication

- POST /api/login.php
- POST /api/register.php
- GET /api/logout.php

Prompts

- GET /api/prompts.php
- GET /api/prompts.php?id={id}
- POST /api/prompts.php
- PUT /api/prompts.php
- DELETE /api/prompts.php?id={id}

Sharing and collaboration

- GET /api/shares.php?prompt_id={id}
- POST /api/shares.php
- DELETE /api/shares.php?id={id}
- GET /api/access_requests.php
- POST /api/access_requests.php
- PUT /api/access_requests.php
- GET /api/collaborators.php?prompt_id={id}

## Development

Run tests:

```
php tests/test_*.php
```

## Security

Please report vulnerabilities privately. See [SECURITY.md](SECURITY.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for workflow, style, and testing guidance.

## Roadmap

- Import/export functionality
- WebSocket-based collaboration updates
- Activity feed and audit logging

## License

Licensed under the MIT License. See [LICENSE](LICENSE).
