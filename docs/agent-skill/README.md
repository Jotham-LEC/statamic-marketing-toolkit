# The migration skill for AI coding agents

[SKILL.md](SKILL.md) teaches an AI coding agent how to move an existing Statamic site to Marketing Toolkit. It follows the [migration checklist](../migrating.md): the agent inspects the site, saves a baseline of the current pages, proposes a plan, migrates the fields, redirects and tracking, and compares the result with the baseline. It asks before it changes content or removes anything.

## With Claude Code

Save the skill in your site's project, and ask Claude Code to use it:

```bash
mkdir -p .claude/skills/migrate-to-marketing-toolkit
curl -o .claude/skills/migrate-to-marketing-toolkit/SKILL.md \
  https://raw.githubusercontent.com/Jotham-LEC/statamic-marketing-toolkit/main/docs/agent-skill/SKILL.md
```

Then type `/migrate-to-marketing-toolkit`, or ask "Move this site's SEO to Marketing Toolkit".

## With another agent

Agents that read skills in the same format, such as those that support `SKILL.md` files, can use the file as it is. For any other agent, paste the contents of SKILL.md (without the lines between the `---` markers at the top) at the start of your conversation, followed by "Move this site's SEO to Marketing Toolkit."

## Before you start

Commit or stash your work first, so that the agent starts from a clean branch. The agent works on a local copy of the site; review what it changes before you deploy.
