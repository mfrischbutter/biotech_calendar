---
name: refactor
description: Analyze and split oversized files, fix concern separation issues
user_invocable: true
argument: file path to refactor
---

# /refactor — Split Oversized Files

Analyze a file for splitting opportunities and execute the refactoring.

## Steps

1. **Read the target file** at `$ARGUMENTS`

2. **Analyze for issues**:
   - File length (>300 lines = should split)
   - Vue template sections >100 lines (extract to partial components)
   - Mixed concerns (business logic in Vue components, or presentation in controllers)
   - Large `<script setup>` blocks with too many responsibilities
   - Composable logic that could be extracted to `lib/`

3. **Create a split plan**:
   - Identify which code blocks to extract
   - Name the new files following project conventions:
     - Page partials → `Pages/Feature/partials/ComponentName.vue`
     - Shared composables → `lib/use-*.ts`
     - Shared components → `Components/ComponentName.vue`
   - Map the import changes needed

4. **Execute the refactoring**:
   - Create new component/composable files
   - Move extracted code to new files
   - Update imports in the original file
   - Ensure TypeScript types are properly exported/imported
   - Use `@/` path aliases (not relative imports)

5. **Verify**:
   - Run `npx vue-tsc --noEmit` on all affected files
   - Confirm no file exceeds 300 lines
   - Check that all imports resolve

6. **Report**:
   - Original file size → new file sizes
   - List of created files
   - Any remaining concerns
