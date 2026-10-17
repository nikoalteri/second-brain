# 24-01 Summary — classified 30-day commitments

## Contract

`App\Services\Forecast\ForecastObligationCollector::collect(User, today)` returns `ForecastObligation` value objects (integer minor units), one per `type:sourceId:dueDate` identity.

- Window: `today` through `today + 30` inclusive, plus overdue items that are not provably settled.
- Owner scoping: every root (accounts, cards, loans, subscriptions) is filtered on `user_id` with the global user scope lifted, so a superadmin gets their personal view. A source account that is not the owner's is treated as unavailable.
- `cash_outflow` is what still has to leave the source account: a live posting transaction means the balance already moved (0); a soft-deleted-only posting does not suppress the deduction (counted, flagged `posting_soft_deleted`).
- Card-billed renewals are never cash debits; card payments count `total_amount` once.
- Subscriptions project every occurrence in the window via `calculateNextRenewalDate()` (de-duplicated); a renewal posted on either path (account or card) is matched by subscription ID + renewal date.
- Overdue unsettled items carry cash 0 and `overdue_unsettled`; they are a coverage gap, not part of the 30-day total.

## Evidence

`tests/Unit/Services/Forecast/ForecastObligationCollectorTest.php` (17 tests): unposted/posted/soft-deleted loan postings, inclusive day 30 vs 31, overdue listed vs already reflected, paid ignored, card payment + card renewal overlap, two renewals in a short month, same-date posted renewal, overdue renewal reported once, source switched to card path, unknown currency / missing / foreign source, other users and superadmin, inactive subscription. Existing `UpcomingPaymentsServiceTest` and the chatbot/dashboard tests are unchanged and green.

## Unresolved coverage conditions

- A pending item with no posting is assumed unpaid; manual payments not linked to the schedule cannot be seen.
- Card charges are not matched to a specific cycle payment, so projected card renewals are always disclosed (`card_charge_projected`).
- Every missed subscription occurrence is listed as overdue (bounded by a 1000-step projection cap).
- Rows marked `paid` with no live posting raise `paid_without_posting` (cash 0, coverage gap) for both loan installments and card payments when `actual_date` is within the last 90 days, or when `actual_date` is missing and the due date is within that window. Older paid history is ignored.
