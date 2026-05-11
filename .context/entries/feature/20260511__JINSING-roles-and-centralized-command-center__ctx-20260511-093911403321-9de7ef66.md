---
entry_id: "ctx-20260511-093911403321-9de7ef66"
title: "JINSING roles and centralized command center"
category: "feature"
tags: ["wordpress", "plugin", "roles", "capabilities", "command-center", "construction"]
files: ["construction-mgmt.php", "includes/security/roles.php", "includes/admin/settings.php", "includes/admin/command-center.php", "includes/db/projects.php"]
commits: ["HEAD"]
status: "active"
importance: "high"
created_at: "2026-05-11T09:39:11Z"
updated_at: "2026-05-11T09:39:11Z"
summary: "Added construction-specific roles/capabilities and a centralized JINSING Command Center for monitoring many concurrent projects with aggregate KPIs."
retrieval_hints: "jinsing command center roles capabilities concurrent projects dashboard rfis"
---

## What
Introduced role registration for director/project-manager/site-engineer with custom caps, added a one-time role sync on init, created a JINSING Command Center admin screen, and added project aggregate queries for multi-project operations visibility.

## Why
The platform needs controlled user access and centralized oversight because many projects run at the same time.

## Impact
Admins can manage permissions by role, operational users can access a centralized command center, and the platform now has a scalable foundation for concurrent project monitoring.
