1) Учебный сервис предварительной оценки заявки на заём под ПТС: принимает заявку (VIN, год, пробег, оценочная стоимость, сумма, срок), считает LTV и возвращает `approve`/`review`/`reject`; данные синтетические.
2) `Makefile`: `help`, `up`, `down`, `ps`, `logs`, `install`, `test`, `lint`, `seed`; `docker-compose.yml` поднимает `backend` (php -S 0.0.0.0:8080) и `db` (mysql:8.0) с healthcheck, порты `8080` и `3307`.
3) Папка `backend/src/Domain/` — там `DecisionEngine.php`, `LtvCalculator.php`, `ApplicationValidator.php`, `VinValidator.php`, `VehicleAge.php`.

модель: MiniMax-M3