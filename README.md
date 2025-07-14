# Notification System

## About the Notification Service

The notification service automates user notifications, supporting multiple channels, personalization, priority handling, user preferences, analytics, and template management. It operates as an hourly scheduled job.

## Problem Description and Current Issues

The notification system is experiencing severe performance degradation, and multiple critical issues:

- **Extremely slow execution times** (taking hours to complete)
- **Database deadlocks and table locking** (preventing concurrent operations)
- **High CPU utilization** (server resources being exhausted)
- **API timeout issues** (external service calls failing)
- **Memory leaks** (process consuming excessive memory)
- **Significant delays** in notification delivery for users

## What we expect

- Clearly describe your approach and the reasoning behind
- Refactor the code with significant performance improvements
- Issues identified & Solutions implemented
- Why these solutions address the root causes
- Scalability considerations
- Architecture recommendations for handling high-load scenarios

Good luck!
