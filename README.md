# Notification System

## Problem Context

You're reviewing a notification processing system that sends notifications to users via external APIs.
The team reports performance and reliability issues under load.

**The Code:** See [NotificationProcessor.php](NotificationProcessor.php)

---

## System Context

- **Current volume:** ~10,000 notifications/day
- **Target volume:** ~500,000 notifications/day (50× growth)
- **Latency target:** Notifications sent within 5 minutes of being created
- **Current runtime:** Script takes 45-60 minutes to process a 100-notification batch
- **Deployment:** Single server today, team wants to scale horizontally

### Known Issues Reported by Team
- Notifications are sometimes **sent multiple times** to the same user
- Running **two instances simultaneously** to improve throughput made duplicates worse
- When the script **crashes mid-run**, some notifications get stuck in pending and never processed
- **Memory usage** grows during long runs
- ~15% of notifications **fail silently** with no retry or alerting

---

## Database Schema

See [schema.sql](schema.sql) for the full schema.

Key tables:
- `notifications` — queue of pending/processed notifications
- `users` — user details (email, name)
- `notification_templates` — message templates with placeholders
- `user_preferences` — per-user settings (timezone, opt-in)

> You may suggest schema changes as part of your solution.

---

## External APIs

### Notification Service
- `POST https://api.notification-service.com/send`
- Rate limit: 100 requests/second
- Supports `X-Idempotency-Key` header for deduplication
- Timeout: currently set to 30 seconds in code

### Analytics Service
- `POST https://analytics.service.com/track`
- **Non-critical:** failures here should NOT block notification delivery
- Timeout: currently set to 15 seconds in code

---

## Your Task

Analyze the code and improve it. Specifically:

1. **Identify** the critical issues affecting performance and reliability
2. **Prioritize** what to fix first and explain why
3. **Implement or design** solutions for the most critical problems
4. **Document** your approach and trade-offs

**You may:**
- Write working code for critical fixes
- Use pseudocode/comments for less critical improvements
- Ask clarifying questions during the coding phase
- Look up syntax (we're not testing memorization)

**You don't need:**
- Complete implementation of all features
- Working tests (explain your test strategy instead)
- Production-ready error handling for every edge case

## Suggested Time Breakdown (30 minutes)

| Time | Activity |
|------|----------|
| 0–5 min | Read code, identify issues, ask clarifying questions |
| 5–10 min | Prioritize and explain your approach |
| 10–25 min | Implement your most critical fix in detail |
| 25–30 min | Document remaining issues in comments |

> **Tip:** One thing done well beats many things done poorly.

## Suggested Approach

This is **not mandatory** - choose your own path:

**Option A: Implementation Focus**
1. Identify and prioritize issues (5 min)
2. Implement ONE critical fix with full detail (20 min)
3. Outline remaining fixes in comments (5 min)

**Option B: Design Focus**
1. Identify issues and prioritize (5 min)
2. Write pseudocode/design for the full solution (15 min)
3. Implement ONE piece in detail to show code quality (10 min)

Both are equally valid — choose what showcases your strengths.


## What We're Looking For

| Area | Weight | What Good Looks Like |
|------|--------|----------------------|
| **Problem Identification** | 25% | Spots critical issues, prioritizes by impact |
| **Concurrency Solution** | 25% | Proposes a correct, safe claim mechanism |
| **Code Quality** | 20% | Clean, readable, well-commented |
| **Communication** | 15% | Explains reasoning and trade-offs clearly |
| **System Thinking** | 15% | Considers failure modes, scale, observability |

> We value **reasoning over completeness**. A well-explained partial solution beats rushed complete code.

---

## Deliverables

By the end of the interview:

1. **Modified code** with your improvements (working or pseudocode)
2. **Comments** explaining your approach and reasoning
3. **Brief write-up** (see section below)


## Setup & Running

### Prerequisites
- PHP 7.4+ with PDO and curl extensions
- MySQL 5.7+ or compatible database

### Run
```bash
php [NotificationProcessor.php](http://_vscodecontentref_/0)