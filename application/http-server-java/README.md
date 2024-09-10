# Java App

```bash
curl -X POST http://localhost:8080/sign-up \
  -H "Content-Type: application/json" \
  -d '{
        "username": "myUsername",
        "password": "myPassword"
      }'
```

```bash
curl -X POST http://localhost:8080/sign-in \
  -H "Content-Type: application/json" \
  -d '{
        "username": "myUsername",
        "password": "myPassword"
      }'
```