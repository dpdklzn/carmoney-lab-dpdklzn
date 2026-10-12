# Planner и scout

## Роли и права

- `planner` (`.kilo/agents/planner.md`) — основной агент (`mode: primary`). Читает код и карту проекта, пишет планы с разделами «Файлы», «Шаги», «Тесты», «Риски»; граничные значения перечисляет отдельно. Редактирование разрешено только в `docs/plan/**`, в остальных местах запрещено; bash запрещён.
- `scout` (`.kilo/agents/scout.md`) — субагент (`mode: subagent`). Читает и ищет код, возвращает файл, строку и короткое описание. Редактирование и bash запрещены; исправлений не предлагает.

## Ответ scout

Запрос из сессии planner: `@scout найди все места, где читается пробег (mileage)`.
Ответ, предоставленный пользователем:

```text
backend/config/rules.php:23 — порог валидации max_mileage_km=500000
backend/src/Domain/ApplicationValidator.php:22 — phpdoc: mileage:int в $normalized
backend/src/Domain/ApplicationValidator.php:43 — чтение из payload: $mileage = (int) ($payload['mileage'] ?? -1)
backend/src/Domain/ApplicationValidator.php:44 — валидация диапазона против rules.vehicle.max_mileage_km
backend/src/Domain/ApplicationValidator.php:45 — формирование $errors['mileage']
backend/src/Domain/ApplicationValidator.php:78 — проброс в нормализованный выход: 'mileage' => $mileage
backend/src/Repository/ApplicationRepository.php:19 — phpdoc $input: mileage:int
backend/src/Repository/ApplicationRepository.php:38 — INSERT vehicles: колонка mileage_km
backend/src/Repository/ApplicationRepository.php:39 — INSERT vehicles: bind :mileage
backend/src/Repository/ApplicationRepository.php:45 — bind: ':mileage' => $input['mileage']
backend/src/Repository/ApplicationRepository.php:68 — SELECT заявки: v.mileage_km (чтение из БД)
frontend/index.html:30 — <label for="mileage">Пробег, км</label>
frontend/index.html:31 — <input id="mileage" name="mileage" type="number" value="84000" required>
frontend/app.js:8 — NUMERIC_FIELDS включает 'mileage'
frontend/app.js:14 — payload['mileage'] = Number(value) при POST
tests/Unit/ApplicationValidatorTest.php:34 — тест: 'mileage' => 84000
tests/Unit/AssessmentServiceTest.php:38 — тест: 'mileage' => 96000
db/schema.sql:22 — колонка mileage_km INT UNSIGNED NOT NULL
db/seed.sql:31 — INSERT ... mileage_km
```

Примечание scout: в `AssessmentService`, `DecisionEngine`, `LtvCalculator` пробег не читается — только валидация и запись в БД.

## Сверка с кодом

Пути и номера строк подтверждены файлами репозитория. Уточнения: `ApplicationValidator.php:22` содержит `@return` с типом `mileage:int`, переменной `$normalized` там нет. В `frontend/app.js:14` используется общий `payload[key]`, преобразующий числовые поля, включая `mileage`, через `Number(value)`. Список включает не только чтение, но и описание типов, конфигурацию, запись и тестовые данные; из БД пробег читается в `ApplicationRepository.php:68`.

Проверка автоматического вызова scout без `@scout` не проводилась. Для planner подтверждено отсутствие изменений `backend/public/index.php` после запроса добавить комментарий; сообщение блокировки инструмента в этой проверке пока не предоставлено.
