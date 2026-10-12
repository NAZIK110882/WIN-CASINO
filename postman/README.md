# Postman collection

This collection contains automated API tests for WIN-CASINO.

How to use:
1. Import postman/WIN-CASINO.postman_collection.json into Postman.
2. Set environment variable `base_url` to your local server address (e.g., http://127.0.0.1/WIN-CASINO).
3. Run requests individually or run the collection with the Collection Runner.

Notes:
- API endpoints in this project often return JSON with HTTP 200 even on application errors. Tests check for presence of `success` or `error` fields accordingly.
