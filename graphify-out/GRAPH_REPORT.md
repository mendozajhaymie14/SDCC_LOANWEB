# Graph Report - SDCC_LOANWEB  (2026-10-09)

## Corpus Check
- 183 files · ~78,281 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 20 file(s) not represented in the graph (top: (none) 15, .css 2, .example 1)

## Summary
- 640 nodes · 891 edges · 135 communities (27 shown, 108 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS · INFERRED: 3 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Community 0
- Community 1
- Community 2
- Community 3
- Community 4
- Community 5
- Community 6
- Community 7
- Community 8
- Community 9
- Community 10
- Community 11
- Community 12
- Community 13
- Community 14
- Community 15
- Community 16
- Community 17
- Community 18
- Community 19
- Community 20
- Community 21
- Community 22
- Community 23
- Community 24
- Community 25
- Community 26
- Community 27
- Community 28
- Community 31
- Community 32
- Community 33
- Community 34
- Community 35
- Community 36
- Community 37
- Community 38
- Community 39
- Community 40
- Community 41
- Community 42
- Community 43
- Community 44
- Community 45
- Community 46
- Community 48
- Community 49
- Community 50
- Community 51
- Community 52
- Community 53
- Community 54
- Community 55
- Community 56
- Community 57
- Community 58
- Community 59
- Community 60
- Community 61

## God Nodes (most connected - your core abstractions)
1. `User` - 73 edges
2. `AdminController` - 31 edges
3. `TestCase` - 30 edges
4. `CoopMember` - 20 edges
5. `Application` - 18 edges
6. `MemberApplication` - 16 edges
7. `LoanApplication` - 15 edges
8. `AppNotification` - 9 edges
9. `Borrowers` - 9 edges
10. `scripts` - 9 edges

## Surprising Connections (you probably didn't know these)
- `{closure#2}()` --references--> `User`  [EXTRACTED]
  database/factories/UserFactory.php → app/Models/User.php
- `GET /admin/data()` --calls--> `Application`  [EXTRACTED]
  routes/web.php → app/Models/Application.php
- `{closure#1}()` --calls--> `User`  [EXTRACTED]
  app/Actions/Fortify/CreateNewUser.php → app/Models/User.php
- `UserController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/UserController.php → app/Http/Controllers/Controller.php
- `ApiTokenPermissionsTest` --inherits--> `TestCase`  [EXTRACTED]
  tests/Feature/ApiTokenPermissionsTest.php → tests/TestCase.php

## Import Cycles
- None detected.

## Communities (135 total, 108 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.05
Nodes (9): UserController, ProcessLoanDocuments, Application, AppNotification, Borrowers, LoanApplication, CreditScoringService, DocumentScannerService (+1 more)

### Community 1 - "Community 1"
Cohesion: 0.05
Nodes (13): AdminController, Controller, MemberApplicationController, MemberLookupController, CoopMember, MemberApplication, {closure#1}(), {closure#2}() (+5 more)

### Community 2 - "Community 2"
Cohesion: 0.07
Nodes (27): devDependencies, autoprefixer, axios, concurrently, laravel-vite-plugin, postcss, tailwindcss, @tailwindcss/forms (+19 more)

### Community 3 - "Community 3"
Cohesion: 0.09
Nodes (6): {closure#1}(), CreateNewUser, PasswordValidationRules, ResetUserPassword, UpdateUserPassword, UpdateUserProfileInformation

### Community 5 - "Community 5"
Cohesion: 0.16
Nodes (4): ApiTokenPermissionsTest, BrowserSessionsTest, CreateApiTokenTest, DeleteApiTokenTest

### Community 6 - "Community 6"
Cohesion: 0.14
Nodes (4): DeleteUser, AppServiceProvider, FortifyServiceProvider, JetstreamServiceProvider

### Community 8 - "Community 8"
Cohesion: 0.25
Nodes (4): {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}()

### Community 9 - "Community 9"
Cohesion: 0.24
Nodes (3): ExampleTest, PasswordConfirmationTest, TestCase

### Community 10 - "Community 10"
Cohesion: 0.29
Nodes (7): money(), productConfig(), rebuildTermOptions(), renderFilePreviews(), renderRequirements(), updateEstimate(), updateMatrixFields()

### Community 12 - "Community 12"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 13 - "Community 13"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 15 - "Community 15"
Cohesion: 0.25
Nodes (8): require, google-gemini-php/laravel, laravel/framework, laravel/jetstream, laravel/sanctum, laravel/tinker, livewire/livewire, php

### Community 16 - "Community 16"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 20 - "Community 20"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 21 - "Community 21"
Cohesion: 0.29
Nodes (6): confirmApiTokenDeletion({{ $token->id }}), deleteApiToken, manageApiTokenPermissions({{ $token->id }}), $toggle(, $set(, updateApiToken

### Community 23 - "Community 23"
Cohesion: 0.29
Nodes (6): user.css, user.flash, user.header, user.scripts, user.sidebar, user.body

### Community 24 - "Community 24"
Cohesion: 0.29
Nodes (6): user.css, user.flash, user.header, user.scripts, user.sidebar, user.loan-form

### Community 26 - "Community 26"
Cohesion: 0.33
Nodes (5): home.body, home.footer, home.css, home.header, home.sidebar

### Community 28 - "Community 28"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 45 - "Community 45"
Cohesion: 0.50
Nodes (3): confirmLogout, logoutOtherBrowserSessions, $toggle(

### Community 46 - "Community 46"
Cohesion: 0.50
Nodes (3): confirmUserDeletion, deleteUser, $toggle(

### Community 49 - "Community 49"
Cohesion: 0.50
Nodes (3): admin.css, admin.footer, admin.sidebar

### Community 50 - "Community 50"
Cohesion: 0.50
Nodes (3): admin.css, admin.footer, admin.sidebar

### Community 51 - "Community 51"
Cohesion: 0.50
Nodes (3): admin.css, admin.footer, admin.sidebar

### Community 52 - "Community 52"
Cohesion: 0.50
Nodes (3): home.css, home.header, home.sidebar

### Community 53 - "Community 53"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 54 - "Community 54"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

## Knowledge Gaps
- **108 isolated node(s):** `$schema`, `name`, `type`, `description`, `keywords` (+103 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 317 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **108 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `Community 4` to `Community 0`, `Community 1`, `Community 3`, `Community 5`, `Community 6`, `Community 7`, `Community 9`, `Community 11`, `Community 44`, `Community 43`, `Community 18`, `Community 22`, `Community 57`, `Community 27`?**
  _High betweenness centrality (0.185) - this node is a cross-community bridge._
- **What connects `$schema`, `name`, `type` to the rest of the system?**
  _108 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Community 0` be split into smaller, more focused modules?**
  _Cohesion score 0.053613053613053616 - nodes in this community are weakly interconnected._
- **Why does `CoopMember` connect `Community 1` to `Community 0`, `Community 3`?**
  _High betweenness centrality (0.041) - this node is a cross-community bridge._
- **Should `Community 1` be split into smaller, more focused modules?**
  _Cohesion score 0.05023796932839767 - nodes in this community are weakly interconnected._
- **Why does `AdminController` connect `Community 1` to `Community 0`?**
  _High betweenness centrality (0.036) - this node is a cross-community bridge._
- **Should `Community 2` be split into smaller, more focused modules?**
  _Cohesion score 0.06628787878787878 - nodes in this community are weakly interconnected._