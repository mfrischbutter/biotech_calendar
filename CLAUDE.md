# CLAUDE.md

## Project Overview

Biotech — appointment and client management app for a biotech/service company. Built with Laravel + Inertia.js + Vue 3 + TypeScript. Manages clients, appointments (with recurring series), employees with granular permissions, and a calendar with day/week/month views.

## Tech Stack

- **Laravel 13** — PHP backend (Eloquent ORM, migrations, controllers)
- **Inertia.js v2** — SPA-like page transitions without a separate API
- **Vue 3** (Composition API + `<script setup>`) — frontend framework
- **TypeScript** — type-safe frontend code
- **Tailwind CSS v3** — utility-first styling with HSL CSS variables
- **Reka UI** — headless UI component library (Dialog, Dropdown, Select, etc.)
- **shadcn-vue** — styled component layer on top of Reka UI (`Components/ui/`)
- **date-fns** — date manipulation (German locale)
- **Vite** — build tool with HMR
- **Laravel Sail** — Docker dev environment
- **Laravel Pint** — PHP code formatting
- **PHPUnit** — PHP testing (SQLite in-memory for tests)

## Architecture

```
app/
├── Http/
│   ├── Controllers/       # Request handling (AppointmentController, ClientController, etc.)
│   ├── Middleware/         # EnsureUserIsOwner, HandleInertiaRequests
│   └── Requests/          # Form request validation
├── Models/                # Eloquent models (User, Client, Appointment, Permission, Setting)
└── Providers/

resources/js/
├── Components/
│   ├── ui/                # shadcn-vue components (button, dialog, select, etc.)
│   └── *.vue              # Shared components
├── Layouts/               # AuthenticatedLayout, GuestLayout
├── Pages/                 # Inertia pages, organized by feature
│   ├── Calendar/          # Index.vue + partials/ (TimeGrid, MonthGrid, AppointmentFormDialog)
│   ├── Clients/           # Index.vue + partials/ (ClientFormDialog)
│   ├── Employees/         # Index.vue + partials/ (AddEmployeeDialog, PermissionToggles)
│   ├── Profile/           # Edit.vue + Partials/
│   └── Auth/              # Login, Register, etc.
├── lib/                   # Composables and helpers
│   ├── use-trans.ts       # Translation composable (reads from Laravel lang/de.json)
│   ├── use-calendar-drag.ts  # Drag-to-create/move/resize calendar logic
│   ├── appointment-types.ts  # Type → color/label mapping
│   └── utils.ts           # cn() for Tailwind class merging
└── types/
    └── index.d.ts         # TypeScript interfaces (User, Client, Appointment, etc.)

database/migrations/       # Chronological schema changes
routes/web.php             # All route definitions
lang/de.json               # German translations
tests/
├── Feature/               # HTTP/integration tests
└── Unit/                  # Isolated unit tests
```

### Key Patterns

- **Inertia page props**: Controllers return data via `Inertia::render('Page', [...props])`
- **Shared props**: `HandleInertiaRequests` shares `auth.user`, `locale`, `translations` on every request
- **Translation**: `useTrans()` composable reads `lang/de.json` via shared Inertia props; use `t('key')` in Vue components
- **UI Components**: All in `Components/ui/` — import from `@/Components/ui/button` etc.
- **Page structure**: Each feature has `Pages/Feature/Index.vue` + `Pages/Feature/partials/*.vue`
- **Forms**: Use `useForm()` from `@inertiajs/vue3` for form handling with validation errors
- **Roles**: `owner` (full access) and `employee` (granular permissions via `Permission` model)

## Commands

```bash
# Development
npm run dev                          # Vite dev server with HMR
./vendor/bin/sail up -d              # Start Docker containers
./vendor/bin/sail artisan migrate    # Run migrations

# Building
npm run build                        # vue-tsc type check + Vite production build

# Testing
php artisan test                     # Run PHPUnit tests
./vendor/bin/sail artisan test       # Run tests via Sail

# Code Quality
npx vue-tsc --noEmit                 # TypeScript type checking (no output)
./vendor/bin/pint                    # Format PHP code (Laravel Pint)
./vendor/bin/pint --test             # Check PHP formatting without fixing
```

## NEVER DO (Critical Rules)

1. **NEVER skip TypeScript types** for component props, emits, or composable return values
   - WRONG: `defineProps({ items: Array })`
   - RIGHT: `defineProps<{ items: Appointment[] }>()`

2. **NEVER use raw SQL in controllers** — use Eloquent queries
   - WRONG: `DB::select('SELECT * FROM clients WHERE ...')`
   - RIGHT: `Client::where(...)->get()`

3. **NEVER put business logic in Vue components** — keep it in controllers/models
   - Vue pages: layout composition, event handling, form submission
   - Controllers: validation, authorization, data fetching, business rules
   - Models: relationships, scopes, computed attributes, constants

4. **NEVER hardcode user-facing strings in Vue** — every visible string MUST use the translation system
   - WRONG: `<Button>Save</Button>` or `placeholder="Kundenname"`
   - RIGHT: `<Button>{{ t('Save') }}</Button>` or `:placeholder="t('Client name')"`
   - This includes: button labels, headings, descriptions, placeholders, select options, empty states, error messages
   - For every new `t('key')` call, add the corresponding German translation to `lang/de.json`
   - The ONLY exception: `email@example.com` style format hints that are language-neutral

5. **NEVER create files over 300 lines** — split into smaller components/partials
   - Extract large template sections into partial components in `partials/` subdirectory
   - Extract complex logic into composables in `lib/`
   - Up to 400 lines acceptable if splitting would hurt readability

6. **NEVER modify `Components/ui/`** files manually — these are shadcn-vue generated
   - Add new components: `npx shadcn-vue@latest add <component>`
   - Custom shared components go in `Components/` (not `Components/ui/`)

7. **NEVER use `any` type** in TypeScript — define proper interfaces in `types/index.d.ts`

8. **NEVER skip validation** in Laravel controllers — always validate request input
   - Use inline `$request->validate([...])` or dedicated FormRequest classes

9. **NEVER duplicate constants** across files
   - Appointment types: defined once in `Appointment::TYPES` (PHP) and `lib/appointment-types.ts` (TS)
   - Permissions: defined once in `Permission::ALL`
   - Before adding a new constant, search the codebase for existing definitions

## Clean Code Rules

### File Organization
- **Max 300 lines per file** (400 if splitting hurts readability)
- One Vue page/component per file
- Extract reusable logic into `lib/` composables
- Page-specific sub-components go in `Pages/Feature/partials/`

### Vue Component Structure
- Always use `<script setup lang="ts">` with TypeScript
- Props: `defineProps<{ ... }>()` with explicit types
- Emits: `defineEmits<{ ... }>()` with explicit types
- Order in `<script setup>`: imports → props/emits → reactive state → computed → methods → lifecycle hooks

### Laravel Controller Structure
- Thin controllers: validate → authorize → execute → respond
- Use Eloquent relationships and scopes, not raw queries
- Return Inertia responses with only the data the page needs
- Use `preserveState` and `preserveScroll` for partial reloads

### CSS/Styling
- Use Tailwind utility classes exclusively — no custom CSS files
- Use shadcn-vue design tokens: `bg-background`, `text-foreground`, `text-muted-foreground`, `border`, etc.
- Use CSS variables for theme colors: `bg-primary`, `text-primary-foreground`, etc.

## Self-Verification (Before Declaring Done)

1. `npx vue-tsc --noEmit` passes with no type errors
2. `npm run build` completes successfully
3. `./vendor/bin/pint --test` reports no formatting issues
4. All user-facing strings use `t()` translation function
5. No file exceeds 300 lines (check with `wc -l`)
6. TypeScript interfaces are defined for all data structures
7. Laravel validation is present for all form submissions
8. New features have corresponding routes in `routes/web.php`
9. No `any` types in TypeScript code
10. No business logic in Vue components — only in controllers/models
