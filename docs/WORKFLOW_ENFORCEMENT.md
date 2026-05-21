# Workflow Enforcement

## Mandatory Steps

1. **Before writing any code:** read `CLAUDE.md` and `.sop.md`, plus `inte-playbook/workflow/README.md`
2. **New feature:** create `docs/features/{name}/` docs before implementation
3. **New migration:** create `docs/database/migrations/NNN_{table}.md` (sequential numbering)
4. **Pre-commit:** all four quality gates must pass

## Critical Invariants

| Invariant | Enforcement |
|---|---|
| State transitions via `JobStateMachine` only | `Job.state` not in `$fillable` |
| Audit log via `ApprovalEventService` only | `ApprovalEvent` has no update/delete |
| GCS via `GcsService` only | No direct GCS SDK in controllers |
| Portal writes via token-scoped endpoints only | `portal.token` middleware required |
| Payment amount from approved line items only | `CrmPaymentService` calculates, no override |

## Quality Gates

```bash
docker compose exec php-fpm php artisan config:clear
docker compose exec php-fpm php artisan test
docker compose exec php-fpm ./vendor/bin/pint --dirty
docker compose exec php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
docker compose run --rm npm sh -c "npm run build"
```
