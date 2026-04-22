# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {ACCESS_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Obtain a token via POST /api/v1/auth/login. Access tokens expire in 30 minutes.

For the SPA, Bearer auth is the important part: several finance endpoints do not work correctly with session cookies alone.
