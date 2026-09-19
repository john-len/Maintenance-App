# Motorcycle Health Score - Simple Explanation

## What is the Health Score?

The Health Score is a number from **0 to 100** that tells how healthy a motorcycle is.

- **100** = perfect condition
- **80 - 99** = excellent
- **60 - 79** = good
- **40 - 59** = fair
- **below 40** = poor

It is not a hard-coded number. It is calculated live from real records in the database.

---

## Four Factors

The score is based on four weighted factors:

| Factor | Weight | What it checks |
|---|---|---|
| **Maintenance History** | 30% | How many services were done in the last 12 months |
| **Mileage / Service Interval** | 20% | Is the motorcycle due for service based on kilometers? |
| **Mechanic Inspection** | 35% | Condition of Engine, Brakes, Tires, Battery, Lights, Suspension, Fluids |
| **Overdue Services** | 15% | Is a required service late? |

---

## How Each Factor is Scored

### 1. Maintenance History (30%)

Counts records in `maintenance_history` for the last 12 months:

- 0 services = 0 points
- 1 service = 50 points
- 2 services = 75 points
- 3 or more services = 100 points

### 2. Mileage / Service Interval (20%)

Compares `current_mileage` to `last_service_mileage` and `maintenance_interval_km`:

- If the motorcycle has not exceeded its service km interval, it gets 100.
- The farther it goes past the interval, the lower the score.

### 3. Mechanic Inspection (35%)

Reads the latest `motorcycle_inspections` record for these 7 parts:

- Engine
- Brakes
- Tires
- Battery
- Lights
- Suspension
- Fluids

Each part is graded:

- **Good** = 100
- **Fair** = 75
- **Needs Attention** = 50
- **Critical** = 25

The inspection score is the average of all 7 parts.

### 4. Overdue Services (15%)

Checks `next_maintenance_date`:

- On time = 100
- Overdue = loses 5 points per overdue day

---

## Final Score Formula

```
Final Score = round(
  Maintenance_Score  x 0.30
  + Mileage_Score    x 0.20
  + Inspection_Score x 0.35
  + Overdue_Score    x 0.15
)
```

The result is always between 0 and 100.

---

## Why This is Not Hard-Coded

The score is not set manually. Every time a service, inspection, or mileage is updated, the system recalculates the score from the actual data.

For example, after one oil change:

- Maintenance goes from 0 to 50 (only 1 service)
- The other parts are still perfect (100)
- The final score becomes 85, not 100

The motorcycle only gets 100 when **all four factors are perfect**.

---

## Real Test Results

These results came from `test_health_score_v2.php`:

| Test | What happened | Score |
|---|---|---|
| Baseline (no services, no overdue) | 70 |
| Oil change overdue 21 days | 55 (went down) |
| Oil change completed | 85 (went up) |
| Poor inspection (Needs Attention) | 68 (went down) |
| Inspection fixed (all Good) | 85 (went up) |
| Confirmed score is not 100 after one service | 85 |

---

## Key Files

- `motorcycle_health_helper.php` - does all the calculations
- `maintenance_history` table - stores service records
- `motorcycle_inspections` table - stores mechanic inspections
- `customer_health_score.php` - customer view
- `admin_health_scores.php` - admin view
- `admin_inspections.php` - new page to add inspections
- `test_health_score_v2.php` - automatic test script
