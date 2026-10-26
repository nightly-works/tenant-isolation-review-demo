# Multi-tenant Laravel review: tenant isolation (demo)

A proof piece for reviews of multi-tenant Laravel SaaS (several client companies in one database, kept apart by `tenant_id`). A fictional invoicing app written for this demo (Laravel 13, PHP 8.3) with four leaks between tenants, each written as a test with two agencies on the same instance that reproduces on the app as delivered and is refused after the fix.

| # | Leak | Severity | Fix |
|---|---|---|---|
| 1 | `Payment` added without the tenant trait: every agency's payments and bank references | high | trait on the model, plus a test that every `tenant_id` model is scoped |
| 2 | The scope switches off when nobody is signed in: the queued reminder job emails every agency's late customers | high | explicit tenant context, scope fails closed, job runs inside its tenant |
| 3 | Dashboard sums cached under one key for all agencies | medium | tenant id in the cache key |
| 4 | `exists:customers,id` ignores the scope: invoices attached to another agency's customer | medium | `Rule::exists(...)->where('tenant_id', ...)` |

Full write-up, in the format a client would receive: [docs/security-review.md](docs/security-review.md).

## History

1. `as-delivered` (tag): the app as written for the demo, flaws included.
2. The four findings as tests: all fail on that code.
3. One commit per fix, then normal-use tests.

## Run

PHP 8.3 and Composer:

```
composer install
cp .env.example .env && php artisan key:generate
php artisan test               # fixed app: no leak, normal use works
./scripts/prove-flaws.sh       # app as delivered: every leak reproduces
```

CI runs both, plus `composer audit`.

[![checks](https://github.com/nightly-works/tenant-isolation-review-demo/actions/workflows/checks.yml/badge.svg)](https://github.com/nightly-works/tenant-isolation-review-demo/actions/workflows/checks.yml)

Built by an AI agent (Claude) at [Nightly Works](https://github.com/nightly-works). MIT licence.
