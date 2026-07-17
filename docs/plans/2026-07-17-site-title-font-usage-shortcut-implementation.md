# Site Title Font Usage Shortcut Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use $executing-plans to implement this plan task-by-task.

**Goal:** Add Style Manager's global Site Title font picker to the core Site Title block's Typography inspector as a synchronized shortcut to the existing Font Usage setting, including a curated Wordmarks collection.

**Architecture:** Keep the theme-owned Site Title font option as the only source of truth. Style Manager will reuse its existing searchable font picker and drive the same hidden font control change pipeline used by Typography → Usage, so variant normalization, webfont loading, editor preview, native save, and Pixelgrade Plus filtering remain shared. The Site Editor engine will boot lazily into a hidden host only when the shortcut is used before the Style Manager sidebar has opened, then move the same root between that host and the sidebar without reinitializing it.

**Tech Stack:** WordPress block editor filters and inspector slots, React through WordPress globals, Style Manager's `wp.customize` shim and legacy font engine, JavaScript `node:test`, Sass, webpack 5, Node 22.

---

### Task 1: Specify the shared font-setting adapter

**Files:**
- Create: `src/_js/site-editor/font-setting-adapter.js`
- Test: `tests/js/site-editor-font-setting-adapter.test.js`

**Step 1: Write the failing tests**

Cover resolution of a bare `site_title_font` key to a wrapped theme setting such as `anima_options[site_title_font]`, locating that setting's hidden font control, and driving its family select through an injected change dispatcher.

**Step 2: Run the focused test and verify it fails**

Run: `node --test tests/js/site-editor-font-setting-adapter.test.js`

Expected: FAIL because the adapter module does not exist.

**Step 3: Write the minimal implementation**

Implement pure helpers that:

- prefer an exact setting id and otherwise match a bracket-wrapped id;
- locate the hidden value holder and owning control row without assuming an Anima option prefix;
- ensure the selected family exists in the lean hidden select and invoke the supplied change dispatcher;
- report `false` without changing state when the setting/control is unavailable.

**Step 4: Run the focused test and verify it passes**

Run: `node --test tests/js/site-editor-font-setting-adapter.test.js`

Expected: PASS.

### Task 2: Add the Wordmarks staff-picks collection

**Files:**
- Modify: `src/_js/site-editor/font-staff-picks.js`
- Create: `tests/js/site-editor-font-staff-picks.test.js`

**Step 1: Write the failing test**

Assert that the bundled collections expose `wordmarks` first, label it `Staff Picks · Wordmarks`, include representative Pixelgrade Cloud and Google logo/display families, and still allow a cloud payload to replace the bundled list.

**Step 2: Run the focused test and verify it fails**

Run: `node --test tests/js/site-editor-font-staff-picks.test.js`

Expected: FAIL because no Wordmarks collection exists.

**Step 3: Write the minimal implementation**

Add a deliberately compact curated wordmark list, include it in the collection order and labels, and preserve runtime filtering against each site's actual catalog.

**Step 4: Run the focused test and verify it passes**

Run: `node --test tests/js/site-editor-font-staff-picks.test.js`

Expected: PASS.

### Task 3: Reuse the picker from the Site Title Typography inspector

**Files:**
- Modify: `src/_js/site-editor/font-control.js`
- Create: `src/_js/site-editor/site-title-font-shortcut.js`
- Modify: `src/_js/site-editor/index.js`
- Modify: `src/_js/site-editor/style.scss`
- Modify: `webpack.config.js`
- Modify: `src/Screen/SiteEditor.php`

**Step 1: Expose the existing family picker**

Export `FontFamilyControl` and route the existing `NativeFont` family changes through the tested adapter so Usage and the new shortcut literally share the same selection action.

**Step 2: Add the Site Title-only inspector extension**

Register an `editor.BlockEdit` filter that adds a Typography-group `ToolsPanelItem` only while `core/site-title` is selected. Resolve the real theme setting id at runtime, bind to its `wp.customize` value, and render the shared picker. Do not write block attributes or create a second font value.

**Step 3: Preserve the full engine contract when the sidebar is closed**

Add a lazy hidden engine host. When the inspector shortcut mounts before the Style Manager sidebar has ever opened, attach and boot the existing engine in that host. When the sidebar opens, move the same engine root into it; when it closes, move the root back. This keeps hidden controls, live canvas CSS, webfont loading, native save, and Plus filtering active without duplicate initialization.

**Step 4: Surface Plus trial semantics**

When the resolved Site Title font setting is gated for a locked site, keep the picker interactive and show compact Plus trial guidance. Let the existing dirty-value filter and Save · Plus affordance remain the persistence authority.

**Step 5: Register explicit WordPress dependencies and style the control**

Declare `wp-block-editor`, `wp-compose`, and `wp-hooks` script dependencies and add narrowly scoped inspector styles. Reuse existing picker classes and design-system/editor component tokens; do not introduce hardcoded theme styling.

### Task 4: Automated verification and production build

**Files:**
- Modify only if failures expose a defect.

**Step 1: Run the focused tests**

Run: `node --test tests/js/site-editor-font-setting-adapter.test.js tests/js/site-editor-font-staff-picks.test.js`

Expected: PASS.

**Step 2: Run the full JavaScript suite**

Run: `node --test tests/js/*.test.js tests/js/*.test.cjs`

Expected: all tests pass; the pre-existing module-type warnings may remain.

**Step 3: Build with the required runtime**

Run: `npm run compile:production` under Node 22.

Expected: webpack and Sass complete successfully.

**Step 4: Inspect the diff**

Confirm there is no Site Title font block attribute, no duplicated font catalog, no hardcoded logo CSS, and no unrelated generated/private files.

### Task 5: Browser verification and Fit Text compatibility

**Files:**
- Modify Nova Blocks only if the live test proves Fit Text does not remeasure after the webfont finishes loading.

**Step 1: Deploy the production assets to the active local site**

Use the repository's local mirror workflow so `style-manager.local` runs the worktree build without replacing unrelated user changes.

**Step 2: Verify the editor workflow visually**

In the Site Editor:

- select a Site Title and confirm the font shortcut appears inside Typography;
- change the font without first opening Style Manager;
- confirm the canvas updates and the chosen face finishes loading;
- open Typography → Usage and confirm Site Title shows the same family;
- change it from Usage and confirm the inspector updates;
- confirm other Site Title instances update globally;
- save/reload on an entitled site and confirm persistence;
- verify the locked Plus presentation/save behavior when that state is available;
- inspect screenshots at desktop and mobile editor widths.

**Step 3: Verify Fit Text**

With Fit Text enabled, switch between materially different wordmark fonts and confirm the title is remeasured after each font loads, remains centered, and follows the existing responsive fit curve. If it does not, add the smallest Nova-side non-persistent measurement invalidation and cover it with a focused test before rebuilding Nova.

**Step 4: Verify the frontend**

Save, load the frontend, and confirm the global Site Title font token is applied consistently without block-specific inline font state.

### Task 6: Review and delivery

**Files:**
- Update: `.claude/napkin.md` only with durable, recurring lessons.

**Step 1: Request code review**

Review the implementation against this plan, the design document, issue #188, the shared-setting contract, Plus gating, and the no-duplicate-state requirement.

**Step 2: Re-run verification after review fixes**

Repeat the focused tests, full JavaScript suite, production build, and relevant browser smoke checks.

**Step 3: Commit and integrate**

Commit with `Fixes #188`, merge the isolated branch into Style Manager `main`, and push `main` after verifying the final checkout.

**Step 4: Close the issue**

Comment on issue #188 with the root cause, implementation, and verification evidence, then close it according to the repository workflow.
