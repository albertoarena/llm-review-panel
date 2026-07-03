# website/CLAUDE.md

Rules for the documentation site. Loaded only when working under `/website`.

## What this is

A static documentation site built with **Astro Starlight** (not bare Astro),
deployed to GitHub Pages on every push to `main`. It is the user-facing front
door. Develop locally with `cd website && npm install && npm run dev`. Deploy is
`.github/workflows/deploy-docs.yml` (see `docs/CI.md`).

## Layout

- `astro.config.mjs` configures the `starlight` integration with site title,
  sidebar groups, and GitHub repo link.
- `src/content.config.ts` declares the `docs` content collection using
  Starlight's `docsLoader` / `docsSchema`.
- `src/content/docs/` holds pages as `.md` / `.mdx`. URLs derive from the file
  path: `src/content/docs/getting-started/installation.md` serves at
  `/getting-started/installation/`.
- `src/styles/` for custom CSS pulled in via Starlight's `customCss`.

## Sidebar groups (use exactly these top-level groups)

1. **Getting started** — installation, quick start, configuration basics.
2. **Concepts** — what a panel is, how synthesis works, why disagreement matters,
   the rubric.
3. **Reviewers** — one page per supported reviewer recipe (Claude Code,
   OpenCode + Ollama, Codex, Gemini, Aider, Qwen).
4. **Reference** — full `config.json` schema, JSON output schema (mirrors
   `docs/SCHEMA.md`), CLI options.
5. **Guides** — practical walkthroughs (running a real review end-to-end, writing
   a good rubric, iterating on the synthesis prompt).
6. **Roadmap / ideas** — open questions and things we'd like to try.

Use Starlight's built-in components (`<Tabs>`, `<Card>`, `<Aside>`, `<LinkCard>`,
`<Steps>`) for callouts, install variants per shell, and stepped walkthroughs
rather than hand-rolling markdown patterns.

## Required coverage

- How to install and run the CLI (mirrors the README quick start, expanded).
- Practical examples: walking through a real review on a sample plan, showing the
  three checkpoints with screenshots or transcripts.
- Reviewer setup recipes (one per supported tool). Each recipe = install link +
  the exact `config.json` entry + any auth gotchas.
- Tips: choosing a panel that disagrees usefully, how to write a good rubric, how
  to iterate on the synthesis prompt.
- An "ideas" / roadmap page.

## The staleness rule

**When a change ships, the docs site must be updated in the same PR.** A PR that
changes user-facing behavior (CLI flags, config schema, reviewer recipes,
supported tools) without a corresponding `/website` change should be sent back.
CI catches build breakage but not staleness; reviewers check for staleness.
