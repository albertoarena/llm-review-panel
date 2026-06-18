# website

Astro Starlight site that documents `llm-review-panel`. Deployed to GitHub
Pages on every push to `main` via `.github/workflows/deploy-docs.yml`.

## Develop locally

```bash
cd website
npm install
npm run dev
```

Open the URL Astro prints (defaults to `http://localhost:4321/llm-review-panel/`).

## Build

```bash
npm run build
```

Produces `dist/`. The deploy workflow uses this output.

## Adding pages

- Pages live under `src/content/docs/`.
- File path becomes the URL: `src/content/docs/getting-started/installation.md` serves at `/getting-started/installation/`.
- Sidebar groups are configured in `astro.config.mjs`. Add a new page to its
  group there so it appears in navigation.
- Use Starlight components (`<Tabs>`, `<Card>`, `<Aside>`, `<Steps>`,
  `<LinkCard>`) by switching the file extension to `.mdx` and importing
  them.
