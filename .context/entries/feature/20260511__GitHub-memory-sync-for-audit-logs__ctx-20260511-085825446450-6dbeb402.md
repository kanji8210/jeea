---
entry_id: "ctx-20260511-085825446450-6dbeb402"
title: "GitHub memory sync for audit logs"
category: "feature"
tags: ["wordpress", "plugin", "github", "memory", "audit-log", "graphql"]
files: ["construction-mgmt.php", "includes/security/settings.php", "includes/security/logging.php", "includes/security/github-memory.php", "includes/graphql/auth.php"]
commits: ["HEAD"]
status: "active"
importance: "high"
created_at: "2026-05-11T08:58:25Z"
updated_at: "2026-05-11T08:58:25Z"
summary: "Scaffolded a Construction Management plugin and added GitHub-backed memory sync for audit events, including admin settings and secure API integration hooks."
retrieval_hints: "github memory sync audit logs wordpress settings token repository issues"
---

## What
Added GitHub memory module that can create issue-based memory entries, exposed settings for enable/repo/token, hooked audit logging to publish entries, and fixed plugin include path plus GraphQL auth parse blocker.

## Why
User requested a GitHub memory skill so important construction audit events can be preserved externally and reviewed over time.

## Impact
Plugin now supports configurable GitHub memory persistence for audit history with runtime-safe guards and validated PHP syntax across all modules.
