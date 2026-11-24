# Project guardrails

## Explicit confirmation before file changes

- Default to read-only investigation, code review, and explanation.
- Before creating, editing, deleting, moving, or renaming any file, describe the exact files and intended changes and obtain the user's explicit confirmation for that scope.
- Requests to review, inspect, diagnose, explain, or propose a fix are not permission to change files. Present proposed changes in chat until approved.
- Approval applies only to the stated scope. Ask again before expanding it; do not ask again for work already explicitly approved.
- This rule includes application code, configuration, documentation, this AGENTS.md file, generated assets, dependencies, lockfiles, logs, and caches.
- Do not run formatters, installers, builds, tests, WordPress bootstrap commands, or other operations that may write files unless those writes are covered by approval. Use read-only alternatives when investigating.
- Do not change the database, WordPress settings, or external services without explicit authorization. Running WordPress can trigger migrations, cron jobs, or automatic updates; account for those side effects before running it.
- Preserve existing user changes. Do not expose database passwords, authentication keys, or tokens in output.
- After approved work, report which files changed and which checks were performed.

## Codebase orientation

- This is a WordPress installation. The root index.php loads wp-blog-header.php and the WordPress bootstrap.
- wp-config.php holds database configuration; treat its secrets as sensitive.
- wp-admin/ and wp-includes/ contain WordPress core.
- wp-content/themes/hostinger-ai-theme/ contains the Hostinger AI theme, block templates, assets, and custom Gutenberg blocks.
- wp-content/plugins/ and wp-content/mu-plugins/ contain Hostinger integrations.
- Theme templates render database-backed post content. Files alone do not establish the active theme, plugins, page content, or site URLs.
- default.php is a separate coming-soon page; do not assume it is the WordPress homepage.
