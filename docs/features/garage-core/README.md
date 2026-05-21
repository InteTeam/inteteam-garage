# Feature: Garage Core

## Overview

The core repair job lifecycle — from vehicle intake through diagnostics, estimates, customer approval, handover, and collection. All actions are timestamped, attributed, and immutable.

## User Stories

**Mechanic:**
- As a mechanic I can create a job for a vehicle so I have a structured record of the repair
- As a mechanic I can upload photos/videos to a job stage so customers have visual evidence of the work
- As a mechanic I can create an estimate with line items so customers can approve or decline individual items
- As a mechanic I can see when a customer has a question so I can respond promptly
- As a mechanic I can mark a job complete and trigger the collection flow

**Garage Admin:**
- As a garage admin I can manage the mechanic team and assign jobs
- As a garage admin I can set the garage's notification channel default
- As a garage admin I can enable online payment for my garage
- As a garage admin I can see all jobs across the team on the dashboard

**Customer (via portal, no login):**
- As a customer I can view photos of my car at each repair stage
- As a customer I can approve or decline individual estimate line items
- As a customer I can ask a question pinned to a specific photo or line item
- As a customer I can complete the handover inspection checklist before collecting my car
- As a customer I can pay online if the garage has enabled it

## Acceptance Criteria

- [ ] Job state machine enforces all valid transitions; invalid transitions throw
- [ ] Media cannot be uploaded to a locked stage
- [ ] Estimate cannot be re-sent after customer has responded; requires new revision
- [ ] All approvals, declines, questions, and preference changes write to `ApprovalEvent`
- [ ] `collected` state requires: HandoverInspection submitted + (payment confirmed OR payment disabled)
- [ ] Cross-garage data access returns 404
- [ ] Customer portal token is scoped to one job only
- [ ] 24h timeout flags jobs stuck in `awaiting_approval`, `customer_query`, `awaiting_collection`
- [ ] Translation preview shown before sending estimate when locale pair requires it

## Design Checklist

- [x] Poka Yoke: blank estimate cannot be sent
- [x] Poka Yoke: all line items must be resolved before `completed`
- [x] Poka Yoke: not-inspected handover item requires a note
- [x] Poka Yoke: payment amount derived from approved items only, no manual override
- [x] Poka Yoke: audit log written by system only, no direct mechanic entries
- [x] Poka Yoke: broken English to customer not possible — translation enforced by locale pair
