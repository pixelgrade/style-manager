# WordPress.org Distribution Handoff Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use $executing-plans to implement this plan task-by-task.

**Goal:** Make WordPress.org the stable update source for Style Manager and retire WUpdates without stranding existing installs.

**Architecture:** Keep WordPress.org as the canonical public distribution channel. Use WUpdates exactly once more to deliver a WordPress.org-clean handoff build to legacy installs, then delete the commercial updater path from source and simplify the release pipeline.

**Tech Stack:** WordPress plugin directory SVN, WordPress.org plugin API, WUpdates admin, Style Manager npm/composer/gulp packaging, Plugin Check, GitHub issues/milestones.

**Tracking Issue:** [#158](https://github.com/pixelgrade/style-manager/issues/158)

---

### Task 0: Create the Tracking Issue

**Files:**
- External: GitHub issue in `pixelgrade/style-manager`

**Step 1: Find the latest open milestone**

Run:
```bash
gh api 'repos/pixelgrade/style-manager/milestones?state=open&sort=due_on&direction=desc'
```

Expected: an open milestone ID/number to assign the handoff issue to. If none exists, create the next release milestone first.

**Step 2: Create the issue**

Create an issue titled `Migrate Style Manager distribution back to WordPress.org` with this scope:

```markdown
Style Manager is live again on WordPress.org. We need to migrate legacy WUpdates installs back to the directory update channel, then retire WUpdates updater code from source.

Acceptance criteria:
- WordPress.org listing/API/download are verified live.
- A stable handoff version is chosen.
- The WordPress.org-clean artifact is released to wp.org SVN.
- The same clean artifact is published once through WUpdates.
- A legacy WUpdates install updates to the handoff version and then checks wp.org for future updates.
- WUpdates source/build complexity is removed after migration.
```

Expected: the issue is assigned to the latest open milestone. This is done as [#158](https://github.com/pixelgrade/style-manager/issues/158), assigned to milestone `2.3.0`. Use `Fixes #158` in the final public commit for this work.

### Task 1: Confirm the Live WordPress.org Baseline

**Files:**
- Modify: `.ai/wporg/progress.md`
- Modify: `.claude/napkin.md`

**Step 1: Verify the directory page and API**

Run:
```bash
curl -I -L https://wordpress.org/plugins/style-manager/
curl -s 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Bslug%5D=style-manager'
```

Expected: the page returns `200`, the API reports slug `style-manager`, version `2.2.13`, `requires_php` `8.1`, and a `download_link` under `downloads.wordpress.org`.

**Step 2: Record the baseline**

Update `.ai/wporg/progress.md` with the live version, active installs, last updated timestamp, support URL, and download URL. Update `.claude/napkin.md` so future sessions stop treating WUpdates as the live channel.

**Step 3: Commit**

No public commit is required for ignored private-overlay files. If this task also changes public docs, commit under the issue created for this handoff.

### Task 2: Decide the Handoff Version

**Files:**
- Modify: `.ai/wporg/progress.md`
- Modify: `style-manager.php`
- Modify: `readme.txt`

**Step 1: Choose the release branch**

If the handoff should only migrate update channels, branch from the already-approved `2.2.13` code and release `2.2.14`.

If the Site Editor beta work on `main` is intended to become stable now, finish QA and release `2.3.0`; do not use `2.3.0-beta1` as the WUpdates handoff.

**Step 2: Update release metadata**

Update the plugin header `Version`, `const VERSION`, `readme.txt` `Stable tag`, and changelog entry to the chosen stable version.

**Step 3: Commit**

```bash
git add style-manager.php readme.txt
git commit -m "Prepare Style Manager <VERSION> handoff release"
```

### Task 3: Build and Verify the WordPress.org-Clean Package

**Files:**
- Verify: `../style-manager-wporg-<VERSION>.zip`

**Step 1: Build the package**

Run:
```bash
npm run zip:wporg
```

Expected: `../style-manager-wporg-<VERSION>.zip` exists.

**Step 2: Inspect the package**

Run:
```bash
unzip -l ../style-manager-wporg-<VERSION>.zip | rg 'distribution/|wupdates|style-manager.php'
```

Expected: no `distribution/` files and no WUpdates files. Inspect `style-manager.php` from the zip and confirm there is no `Update URI: false` header.

**Step 3: Run Plugin Check on the artifact**

Install the built artifact into the clean Plugin Check site and run:
```bash
studio wp --path ~/Studio/sm-pcp plugin check style-manager --format=csv
```

Expected: `0` errors and `0` warnings, or documented non-blocking findings with explicit rationale before release.

### Task 4: Publish to WordPress.org SVN

**Files:**
- External: `https://plugins.svn.wordpress.org/style-manager/trunk/`
- External: `https://plugins.svn.wordpress.org/style-manager/tags/<VERSION>/`

**Step 1: Update SVN trunk from the clean package**

Checkout or update a local SVN working copy, replace `trunk/` with the clean package contents, and review:
```bash
svn status
svn diff
```

Expected: only release-intended code, asset, readme, and metadata changes.

**Step 2: Commit trunk and tag**

Run:
```bash
svn ci -m "Release <VERSION>"
svn cp trunk tags/<VERSION>
svn ci -m "Tag <VERSION>"
```

Expected: SVN commits succeed and the tag exists.

**Step 3: Verify public propagation**

Run:
```bash
curl -s 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Bslug%5D=style-manager'
curl -I 'https://downloads.wordpress.org/plugin/style-manager.<VERSION>.zip'
```

Expected: WordPress.org reports `<VERSION>` and the download URL responds successfully.

### Task 5: Publish the One-Time WUpdates Handoff

**Files:**
- External: WUpdates `Style Manager` product

**Step 1: Upload the WordPress.org-clean zip**

In WUpdates, create a new Plugin Version for `Style Manager` using `../style-manager-wporg-<VERSION>.zip`, not the commercial `npm run zip` artifact.

**Step 2: Set it as current**

Publish the version and update the parent product's `Current Version` to the new version post.

**Step 3: Verify WUpdates delivery**

Use the WUpdates verification commands in `AGENTS.md` to confirm the parent product, version metadata, and attached zip path. Then smoke-test an older WUpdates-installed copy and confirm it updates to `<VERSION>`.

Expected: after updating, the installed plugin no longer has the WUpdates updater or `Update URI: false`, so subsequent updates come from WordPress.org.

### Task 6: Retire WUpdates From Source

**Files:**
- Delete: `distribution/wupdates.php`
- Modify: `style-manager.php`
- Modify: `.zipignore-wporg`
- Modify: `tasks/build-folder.js`
- Modify: `tasks/build-zip.js`
- Modify: `gulpfile.js`
- Modify: `composer.json`
- Modify: `package.json`
- Modify: `AGENTS.md`
- Modify: `README.md`

**Step 1: Remove updater loading**

Delete `distribution/wupdates.php` and remove the guarded include from `style-manager.php`.

**Step 2: Remove the source header**

Remove `Update URI: false` from `style-manager.php`.

**Step 3: Simplify packaging**

Remove the WordPress.org-vs-commercial split if no other commercial-only files remain. Keep one release package command that builds the WordPress.org-safe artifact.

**Step 4: Verify**

Run:
```bash
npm run zip:wporg
rg -n 'WUpdates|wupdates|Update URI: false|zip:wporg|distribution/wupdates' style-manager.php tasks gulpfile.js composer.json package.json AGENTS.md README.md
```

Expected: no runtime WUpdates or `Update URI: false` references. Any remaining docs references should be historical and explicitly marked that way.

**Step 5: Commit**

```bash
git add -A
git commit -m "Retire WUpdates distribution path" -m "Fixes #158"
```

### Task 7: Polish the WordPress.org Listing

**Files:**
- Modify: `readme.txt`
- External: `https://plugins.svn.wordpress.org/style-manager/assets/`

**Step 1: Add listing assets**

Prepare banner, icon, and screenshots in the SVN `assets/` directory. Keep generated or source design files outside the plugin package.

**Step 2: Improve public readme copy**

Keep the copy warm and practical. Emphasize Style Manager as the free design-control engine for compatible themes, disclose Pixelgrade Cloud clearly, and avoid comparative claims against WordPress core.

**Step 3: Verify the listing**

After SVN propagation, check the public page, screenshots, install instructions, tags, and support forum URL.

Expected: the listing is complete, current, and ready to be linked from Pixelgrade, Anima LT, Nova Blocks, and onboarding flows.
