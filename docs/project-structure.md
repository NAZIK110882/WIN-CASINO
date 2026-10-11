# WIN-CASINO project structure

```text
WIN-CASINO/
├── README.md
├── composer.json
├── phpunit.xml
├── .gitignore
├── .env.example
├── index.html                    # legacy entry point
├── slots.php                     # legacy slots page
├── bonanza.html                 # legacy bonanza page
├── rocket.html                  # legacy rocket page
├── api/                         # legacy backend API
│   ├── user.php
│   ├── spin.php
│   ├── bonanza.php
│   ├── rocket_start.php
│   └── rocket_win.php
├── src/                         # application classes
│   ├── Database.php
│   └── Game.php
├── config/                      # configuration
│   └── database.php
├── public/                      # public web root (recommended)
│   ├── index.php
│   ├── api/
│   │   ├── user.php
│   │   ├── spin.php
│   │   ├── bonanza.php
│   │   ├── rocket_start.php
│   │   └── rocket_win.php
│   ├── games/
│   │   ├── slots.php
│   │   ├── bonanza.php
│   │   └── rocket.php
│   ├── assets/
│   │   ├── css/
│   │   │   └── main.css
│   │   ├── js/
│   │   │   └── app.js
│   │   └── images/
│   └── .htaccess
├── routes/
│   └── api.php
├── tests/
│   └── GameTest.php
└── vendor/
```

This structure keeps the existing app working, while introducing a cleaner and more standard layout for future development.
