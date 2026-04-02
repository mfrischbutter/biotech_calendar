---
name: feature
description: Scaffold a new feature with migration, model, controller, routes, Vue page, and TypeScript types
user_invocable: true
argument: feature name in snake_case (e.g., "invoices")
---

# /feature — Scaffold New Feature

Scaffold a complete new feature for the biotech app. Follow these steps exactly:

## Steps

1. **Parse the feature name** from `$ARGUMENTS`. Convert to snake_case if needed. Derive:
   - Model name (PascalCase singular): e.g., `Invoice`
   - Controller name: e.g., `InvoiceController`
   - Table name (snake_case plural): e.g., `invoices`
   - Page directory: e.g., `Pages/Invoices/`
   - Route prefix: e.g., `invoices`

2. **Create migration** at `database/migrations/`:
   - Use `php artisan make:migration create_${table}_table` naming convention
   - Include `id`, basic columns, `user_id` FK, timestamps

3. **Create Eloquent model** at `app/Models/${Model}.php`:
   - Define `$fillable` array
   - Define relationships (belongsTo User, etc.)
   - Add type constants if applicable

4. **Create controller** at `app/Http/Controllers/${Model}Controller.php`:
   - `index(Request)` — render Inertia page with data
   - `store(Request)` — validate and create
   - `update(Request, $Model)` — validate and update
   - `destroy($Model)` — delete
   - Use `$request->validate([...])` for validation
   - Return `Inertia::render(...)` for index, `redirect()->back()` for mutations

5. **Add routes** to `routes/web.php`:
   - Inside the `auth` + `verified` middleware group
   - RESTful routes for CRUD operations

6. **Create TypeScript interface** in `resources/js/types/index.d.ts`:
   - Add interface matching the model's JSON shape

7. **Create Vue page** at `resources/js/Pages/${Feature}/Index.vue`:
   - Use `<script setup lang="ts">` with typed props
   - Use `AuthenticatedLayout` with header slot
   - Use shadcn-vue components (Button, Table, Dialog, etc.)
   - Use `t()` for all user-facing strings

8. **Create form dialog** at `resources/js/Pages/${Feature}/partials/${Model}FormDialog.vue`:
   - Reusable for create and edit (optional `$model` prop)
   - Use `useForm()` from Inertia
   - Include validation error display

9. **Add nav link** in `AuthenticatedLayout.vue` (if appropriate)

10. **Verify**:
    - `npx vue-tsc --noEmit` passes
    - `./vendor/bin/pint --test` passes
    - Report created files

## Conventions to Follow
- German labels in UI (e.g., "Neu erstellen", "Bearbeiten", "Löschen")
- Use `t()` translation composable for all strings
- Use shadcn-vue components from `@/Components/ui/`
- TypeScript strict typing for all props and emits
- Eloquent relationships, not raw queries
