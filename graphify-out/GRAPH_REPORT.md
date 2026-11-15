# Graph Report - hookbytes  (2026-11-15)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 1503 nodes · 2651 edges · 165 communities (38 shown, 127 thin omitted)
- Extraction: 98% EXTRACTED · 2% INFERRED · 0% AMBIGUOUS · INFERRED: 65 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `9cdb29dd`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

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
- Community 29
- Community 30
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
- Community 45
- Community 46
- Community 47
- Community 49
- Community 50
- Community 51
- Community 52
- Community 53
- Community 54
- Community 56
- Community 57
- Community 58
- Community 59
- Community 60
- Community 62
- Community 63
- Community 64
- Community 65
- Community 66
- Community 67
- Community 68
- Community 69
- Community 70
- Community 71
- Community 72
- Community 73
- Community 74
- Community 75
- Community 76
- Community 77
- Community 78
- Community 80
- Community 81
- Community 82
- Community 83
- Community 84
- Community 85
- Community 86
- Community 87
- Community 88
- Community 90
- Community 91
- Community 92
- Community 93
- Community 94
- Community 96
- Community 98
- Community 99
- Community 102
- Community 103
- Community 104
- Community 105
- Community 106
- Community 107
- Community 108
- Community 109
- Community 110
- Community 111
- Community 112
- Community 113
- Community 114
- Community 115
- Community 116
- Community 117
- Community 118
- Community 119
- Community 120
- Community 121
- Community 122
- Community 123
- Community 124
- Community 125
- Community 126
- Community 127
- Community 128
- Community 129

## God Nodes (most connected - your core abstractions)
1. `WebhookEndpoint` - 90 edges
2. `TestCase` - 66 edges
3. `Project` - 54 edges
4. `Event` - 40 edges
5. `User` - 40 edges
6. `ProcessWebhookEvent` - 39 edges
7. `UiInteractionTest` - 39 edges
8. `UserManagementTest` - 34 edges
9. `Controller` - 34 edges
10. `WebhookEndpointTest` - 33 edges

## Surprising Connections (you probably didn't know these)
- `AdminOperationsTest` --references--> `User`  [EXTRACTED]
  tests/Feature/AdminOperationsTest.php → app/Models/User.php
- `WebhookReliabilityIngressTest` --references--> `WebhookEndpoint`  [EXTRACTED]
  tests/Feature/WebhookReliabilityIngressTest.php → app/Models/WebhookEndpoint.php
- `WebhookTransformationTest` --references--> `WebhookEndpoint`  [EXTRACTED]
  tests/Feature/WebhookTransformationTest.php → app/Models/WebhookEndpoint.php
- `WebhookTransformationTest` --references--> `Project`  [EXTRACTED]
  tests/Feature/WebhookTransformationTest.php → app/Models/Project.php
- `WebhookTransformationTest` --references--> `User`  [EXTRACTED]
  tests/Feature/WebhookTransformationTest.php → app/Models/User.php

## Import Cycles
- None detected.

## Communities (165 total, 127 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.07
Nodes (4): SettingsController, ProcessWebhookEvent, AdminOperationsTest, ErrorHandlingTest

### Community 1 - "Community 1"
Cohesion: 0.08
Nodes (5): GatewayPolicyController, WebhookController, WebhookEndpoint, {closure#1}(), GatewayPolicyService

### Community 2 - "Community 2"
Cohesion: 0.04
Nodes (48): build, container_name, depends_on, environment, networks, restart, volumes, working_dir (+40 more)

### Community 3 - "Community 3"
Cohesion: 0.07
Nodes (4): WebhookEndpointPolicy, WebhookPayloadMapping, WebhookRoutingRule, WebhookRoutingService

### Community 4 - "Community 4"
Cohesion: 0.08
Nodes (8): AuthenticationTest, PasswordConfirmationTest, PasswordUpdateTest, RegistrationTest, ExampleTest, ProfileTest, TestCase, TestsWithAuthentication

### Community 5 - "Community 5"
Cohesion: 0.11
Nodes (4): EnvironmentSettingsController, EnvironmentSettingsService, EnvironmentSettingsAdminTest, EnvironmentSettingsServiceTest

### Community 6 - "Community 6"
Cohesion: 0.08
Nodes (9): EmailVerificationNotificationController, EmailVerificationPromptController, {closure#1}(), NewPasswordController, PasswordController, PasswordResetLinkController, RegisteredUserController, VerifyEmailController (+1 more)

### Community 7 - "Community 7"
Cohesion: 0.09
Nodes (3): GenerateShortUrls, WebhookCommand, GenerateShortUrlsCommandTest

### Community 9 - "Community 9"
Cohesion: 0.09
Nodes (27): devDependencies, alpinejs@^3.4.2, autoprefixer@^10.4.2, axios@^1.11.0, concurrently@^9.0.1, laravel-vite-plugin@^2.0.0, postcss@^8.4.31, tailwindcss@^3.1.0 (+19 more)

### Community 10 - "Community 10"
Cohesion: 0.12
Nodes (3): WebhookTransformation, WebhookTransformationService, WebhookTransformationTest

### Community 13 - "Community 13"
Cohesion: 0.13
Nodes (4): AuthenticatedSessionController, ConfirmablePasswordController, ProfileController, WebhookTransformationController

### Community 14 - "Community 14"
Cohesion: 0.11
Nodes (4): EventDelivery, Project, Settings, WebhookReliabilityIngressTest

### Community 18 - "Community 18"
Cohesion: 0.26
Nodes (21): check_root(), check_ubuntu(), configure_nginx(), configure_supervisor(), create_update_script(), get_app_config(), get_database_config(), get_smtp_config() (+13 more)

### Community 25 - "Community 25"
Cohesion: 0.19
Nodes (3): ApiAuthentication, EnsureAdminOwner, ForceHttps

### Community 34 - "Community 34"
Cohesion: 0.23
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}()

### Community 39 - "Community 39"
Cohesion: 0.24
Nodes (3): {closure#1}(), {closure#2}(), CreatesApplication

### Community 42 - "Community 42"
Cohesion: 0.18
Nodes (11): build, container_name, depends_on, environment, networks, restart, volumes, working_dir (+3 more)

### Community 43 - "Community 43"
Cohesion: 0.18
Nodes (11): container_name, environment, image, networks, restart, volumes, MYSQL_DATABASE, MYSQL_PASSWORD (+3 more)

### Community 47 - "Community 47"
Cohesion: 0.22
Nodes (3): {closure#1}(), {closure#2}(), SendDeadLetterAlert

### Community 51 - "Community 51"
Cohesion: 0.49
Nodes (9): main(), print_error(), print_status(), print_success(), print_warning(), run_scenario(), run_test_file(), run_with_coverage() (+1 more)

### Community 53 - "Community 53"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 54 - "Community 54"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/breeze, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision (+1 more)

### Community 58 - "Community 58"
Cohesion: 0.28
Nodes (4): GatewayPolicyAdminTest, {closure#1}(), {closure#2}(), WebhookDeliveryReliabilityTest

### Community 63 - "Community 63"
Cohesion: 0.57
Nodes (8): healthcheck, healthcheck, interval, retries, test, timeout, healthcheck, healthcheck

### Community 64 - "Community 64"
Cohesion: 0.25
Nodes (8): container_name, depends_on, image, networks, ports, restart, volumes, nginx

### Community 68 - "Community 68"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 69 - "Community 69"
Cohesion: 0.29
Nodes (7): require, laravel/framework, laravel/telescope, laravel/tinker, opis/json-schema, php, predis/predis

### Community 70 - "Community 70"
Cohesion: 0.29
Nodes (7): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, test

### Community 71 - "Community 71"
Cohesion: 0.38
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), down(), getConnection(), up()

### Community 72 - "Community 72"
Cohesion: 0.33
Nodes (5): {closure#1}(), {closure#2}(), {closure#3}(), getConnection(), up()

### Community 73 - "Community 73"
Cohesion: 0.33
Nodes (5): {closure#1}(), {closure#2}(), {closure#3}(), getConnection(), up()

### Community 74 - "Community 74"
Cohesion: 0.29
Nodes (7): command, container_name, image, networks, restart, volumes, redis

### Community 75 - "Community 75"
Cohesion: 0.33
Nodes (5): driver, networks, hookbytes-network, services, version

### Community 78 - "Community 78"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 88 - "Community 88"
Cohesion: 0.40
Nodes (5): driver, driver, volumes, hookbytes-db-data, hookbytes-redis-data

### Community 96 - "Community 96"
Cohesion: 0.50
Nodes (3): profile.partials.delete-user-form, profile.partials.update-password-form, profile.partials.update-profile-information-form

### Community 98 - "Community 98"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 99 - "Community 99"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

## Knowledge Gaps
- **29 isolated node(s):** `layouts.navigation`, `layouts.master`, `layouts.master`, `layouts.master`, `layouts.master` (+24 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 716 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **127 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `WebhookEndpoint` connect `Community 1` to `Community 32`, `Community 3`, `Community 7`, `Community 40`, `Community 10`, `Community 13`, `Community 14`, `Community 15`, `Community 47`, `Community 46`, `Community 48`, `Community 17`, `Community 55`, `Community 58`, `Community 59`, `Community 61`, `Community 62`, `Community 31`?**
  _High betweenness centrality (0.119) - this node is a cross-community bridge._
- **What connects `layouts.navigation`, `layouts.master`, `layouts.master` to the rest of the system?**
  _29 weakly-connected nodes found - possible missing edges._
- **Should `Community 0` be split into smaller, more focused modules?**
  _Cohesion score 0.06531204644412192 - nodes in this community are weakly interconnected._
- **Why does `TestCase` connect `Community 4` to `Community 0`, `Community 5`, `Community 7`, `Community 8`, `Community 10`, `Community 11`, `Community 12`, `Community 14`, `Community 16`, `Community 17`, `Community 19`, `Community 21`, `Community 22`, `Community 24`, `Community 26`, `Community 29`, `Community 31`, `Community 32`, `Community 35`, `Community 39`, `Community 44`, `Community 45`, `Community 50`, `Community 52`, `Community 58`, `Community 65`?**
  _High betweenness centrality (0.113) - this node is a cross-community bridge._
- **Should `Community 1` be split into smaller, more focused modules?**
  _Cohesion score 0.0824829931972789 - nodes in this community are weakly interconnected._
- **Why does `Project` connect `Community 14` to `Community 32`, `Community 1`, `Community 33`, `Community 7`, `Community 40`, `Community 10`, `Community 46`, `Community 15`, `Community 48`, `Community 17`, `Community 52`, `Community 55`, `Community 25`, `Community 58`, `Community 60`, `Community 62`, `Community 31`?**
  _High betweenness centrality (0.061) - this node is a cross-community bridge._
- **Should `Community 2` be split into smaller, more focused modules?**
  _Cohesion score 0.04421768707482993 - nodes in this community are weakly interconnected._