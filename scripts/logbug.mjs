#!/usr/bin/env node
// logbug — capture a housekeeping item into sessions/housekeeping-incoming.md
// without disrupting an in-flight session.
//
// Usage:
//   npm run logbug -- "hero buttons can't be right-aligned"
//   npm run logbug -- hero buttons cant be right aligned   (quotes optional)
//
// Appends one stamped line to the incoming scratch file. The stamp carries the
// repo-root VERSION marker + today's date so that when the item is later walked
// into a session, its age is visible and the premise can be re-verified against
// current code before any work is scheduled. The incoming file is digested into
// sessions/housekeeping-inbox.md at the next session close (see the close gate
// in sessions/template-base-prompt.md), so capture never edits the canonical
// inbox mid-session.

import { readFileSync, existsSync, appendFileSync, writeFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..')
const incomingPath = join(repoRoot, 'sessions', 'housekeeping-incoming.md')
const versionPath = join(repoRoot, 'VERSION')

const description = process.argv.slice(2).join(' ').trim()
if (!description) {
  console.error('Usage: npm run logbug -- "what you noticed"')
  process.exit(1)
}

const version = existsSync(versionPath)
  ? readFileSync(versionPath, 'utf8').trim()
  : 'unknown'
const date = new Date().toISOString().slice(0, 10)

const header = `# Housekeeping Incoming

Capture buffer for items noticed mid-session via \`npm run logbug -- "…"\`. Each
line is stamped with the VERSION marker + date at capture time. This file is
NOT the canonical inbox — at the next session close the close gate digests these
items, verifies each against current code, surfaces anything questionable, and
folds the survivors into \`sessions/housekeeping-inbox.md\`, then clears this
file back to this header. Do not hand-curate here; capture and move on.

---
`

if (!existsSync(incomingPath)) {
  writeFileSync(incomingPath, header)
}

appendFileSync(incomingPath, `\n- [${version} · ${date}] ${description}`)
console.log(`Logged to housekeeping-incoming.md [${version} · ${date}]: ${description}`)
