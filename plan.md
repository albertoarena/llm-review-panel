  # Plan: rate-limit the `/login` endpoint

  We see brute-force attempts on `/login`. Add per-IP rate limiting at the
  controller level: 5 attempts per IP per minute, return 429 after that.

  Steps:

  1. Install `symfony/rate-limiter` (already in vendor).
  2. Configure a rate limiter named `login` in `framework.yaml` with the
     policy `fixed_window`, limit 5, interval `1 minute`.
  3. In `LoginController::login`, fetch the limiter for the request IP,
     consume one token, return 429 if blocked.
  4. Add a feature test that hits `/login` six times and asserts the 6th
     is 429.

  No frontend changes. No new database tables.
