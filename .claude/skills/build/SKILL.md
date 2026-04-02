---
name: build
description: Run the full production build (vue-tsc + Vite) and report results
user_invocable: true
---

# /build — Production Build

Run the full production build and report results.

## Steps

1. Run `npm run build` (which runs `vue-tsc && vite build`)
2. Report success or any errors
3. If TypeScript errors occur, read the affected files and fix them
4. If Vite build errors occur, analyze and suggest fixes
5. Re-run build to confirm everything passes
