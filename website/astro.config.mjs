// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

export default defineConfig({
  site: 'https://albertoarena.github.io',
  base: '/llm-review-panel',
  integrations: [
    starlight({
      title: 'llm-review-panel',
      description:
        'A PHP CLI that runs the same review across multiple LLM command-line tools in parallel and synthesizes their outputs into one consolidated review.',
      social: [
        {
          icon: 'github',
          label: 'GitHub',
          href: 'https://github.com/albertoarena/llm-review-panel',
        },
      ],
      sidebar: [
        {
          label: 'Getting started',
          items: [
            { label: 'Overview', slug: 'index' },
            { label: 'Installation', slug: 'getting-started/installation' },
            { label: 'Quick start', slug: 'getting-started/quick-start' },
            { label: 'Configuration basics', slug: 'getting-started/configuration' },
          ],
        },
        {
          label: 'Concepts',
          items: [
            { label: 'The review panel', slug: 'concepts/panel' },
            { label: 'How synthesis works', slug: 'concepts/synthesis' },
            { label: 'The rubric', slug: 'concepts/rubric' },
          ],
        },
        {
          label: 'Reviewers',
          items: [
            { label: 'Claude Code', slug: 'reviewers/claude' },
            { label: 'OpenCode + Ollama', slug: 'reviewers/opencode' },
            { label: 'Codex CLI', slug: 'reviewers/codex' },
            { label: 'Gemini CLI', slug: 'reviewers/gemini' },
            { label: 'Aider', slug: 'reviewers/aider' },
            { label: 'Qwen Code', slug: 'reviewers/qwen' },
          ],
        },
        {
          label: 'Reference',
          items: [
            { label: 'config.json schema', slug: 'reference/config' },
            { label: 'JSON output schema', slug: 'reference/schema' },
            { label: 'CLI options', slug: 'reference/cli' },
          ],
        },
        {
          label: 'Guides',
          items: [
            { label: 'Running your first review', slug: 'guides/first-review' },
            { label: 'Writing a good rubric', slug: 'guides/writing-rubrics' },
            { label: 'Iterating on the synthesis prompt', slug: 'guides/iterating-synthesis' },
          ],
        },
        {
          label: 'Roadmap',
          slug: 'roadmap',
        },
      ],
      customCss: ['./src/styles/custom.css'],
    }),
  ],
});
