# План: правило «пробег авто не больше 400 000 км, иначе решение review»

ID задачи: `MILEAGE`. Основа: `docs/spec/spec_MILEAGE.md` (обновлена 2026-10-12: OQ-MILEAGE-01
закрыт ответом постановщика), `docs/setup/code_map.md` (сверен с кодом), ответ scout из
`docs/setup/agents.md`. План сверен с обновлённой спекой; шаги и тесты привязаны к REQ/AC.

Суть по спеке: заявка с пробегом не больше 400 000 км решается по LTV по действующим
правилам (REQ-MILEAGE-01); заявка с пробегом 400 001–500 000 км, решение которой по LTV —
`approve`, получает `review` вместо `approve` (REQ-MILEAGE-02); при том же пробеге решения
`review` и `reject` по LTV сохраняются без изменений — `reject` при LTV > 85 сохраняет
приоритет (REQ-MILEAGE-03; OQ-MILEAGE-01 закрыт 2026-10-12 ответом постановщика). Валидация
пробега 0–500 000 км не меняется (REQ-MILEAGE-04), отсутствующее поле и `null` — ошибка
валидации (REQ-MILEAGE-05), пустая строка сохраняет текущее поведение «приводится к 0 и
допускается» (REQ-MILEAGE-06), набор решений и структура ответа не меняются (REQ-MILEAGE-07).

## Файлы

- `backend/config/rules.php` — в секцию `vehicle` (строки 20–24) добавить ключ
  `'review_max_mileage_km' => 400000` рядом с `max_mileage_km` (строка 23): порог решения из
  требования (REQ-MILEAGE-01/02), не хардкод в коде. Имя ключа спека не фиксирует — выбор
  плана (предложение из code_map); переименование при ревью на REQ/AC не влияет.
- `backend/src/Domain/DecisionEngine.php` — принять порог в конструкторе (расширить форму
  массива `$thresholds`, строка 23), сменить сигнатуру `decide(float $ltv, int $mileage): string`
  (строка 30), переработать ранние `return` (строки 32–40): сначала решение по LTV, затем
  понижение `approve` → `REVIEW` при пробеге выше порога (REQ-MILEAGE-02); ветки `review` и
  `reject` не меняются (REQ-MILEAGE-03); обновить phpdoc класса и метода.
- `backend/src/Domain/AssessmentService.php` — в `assess()` строка 33: передать
  `$input['mileage']` вторым аргументом в `decide()`; phpdoc результата не меняется
  (`decision:string` — REQ-MILEAGE-07).
- `backend/src/AppFactory.php` — строка 37: вместо `$rules['ltv']` собрать для `DecisionEngine`
  массив с порогами LTV и пробегом (`$rules['ltv']` + `$rules['vehicle']['review_max_mileage_km']`).
- `tests/Unit/DecisionEngineTest.php` — обновить провайдер под новую сигнатуру (строка 23) и
  сборку движка в `setUp()` (строка 17); добавить граничные случаи AC-MILEAGE-01 – AC-MILEAGE-06
  (см. Тесты).
- `tests/Unit/AssessmentServiceTest.php` — обновить сборку `DecisionEngine` в `setUp()`
  (строка 27) новым порогом; добавить сквозные тесты AC-MILEAGE-03 – AC-MILEAGE-06 (пробег × LTV),
  AC-MILEAGE-12 (пустая строка → 0 → `approve`) и AC-MILEAGE-13 (неизменный состав результата);
  существующие тесты (пробег 96 000, строка 38) остаются REQ-MILEAGE-01-регрессией.
- `tests/Unit/ApplicationValidatorTest.php` — пробег сейчас покрыт только валидным значением
  (строка 34, 84000); добавить AC-MILEAGE-07 – AC-MILEAGE-12 (границы валидации, отсутствующее
  поле, `null`, пустая строка).
- `frontend/` — без изменений: поле `mileage` уже есть (`index.html:30–31`, `app.js:8,14`).
- `README.md` — без изменений: таблица/пороги решений в README отсутствуют (строка 7 только
  перечисляет `approve` / `review` / `reject`), набор решений не меняется (REQ-MILEAGE-07).
- `db/schema.sql` — без изменений: `mileage_km INT UNSIGNED NOT NULL` (строка 22) вмещает весь
  диапазон валидации, миграции не нужны.

## Шаги

Порядок выполнения ДЗ.1: сначала подготовить тесты из шагов 5–7 (включая новый контракт движка), запустить `make test` и убедиться, что падения связаны с отсутствующим правилом пробега. Сохранить тесты отдельным коммитом до кода. Затем выполнить шаги 1–4 и добиться зелёного запуска шага 8, не меняя зафиксированные тесты. Нумерация ниже описывает состав изменений, а не порядок коммитов.

1. `rules.php`: добавить `'review_max_mileage_km' => 400000` в секцию `vehicle` с комментарием,
   отличающим его от `max_mileage_km` (порог валидации ввода 0–500 000, REQ-MILEAGE-04, vs
   порог решения, REQ-MILEAGE-01/02).
2. `DecisionEngine`: принять порог в конструкторе, сменить сигнатуру на
   `decide(float $ltv, int $mileage): string`, переработать ранние `return` (добавление
   проверки после нынешних LTV-веток её не выполнит): сначала решение по LTV, затем замена
   `approve` на `self::REVIEW` при `mileage > reviewMaxMileageKm` (REQ-MILEAGE-02); ветки
   `review` и `reject` не трогаются — `reject` сохраняет приоритет (REQ-MILEAGE-03, вариант
   «`reject` сохраняет приоритет» из закрытого OQ-MILEAGE-01, отдельная ветка не нужна).
   Обновить phpdoc класса и метода.
3. `AssessmentService::assess()`: передать `$input['mileage']` в `decide()` (строка 33);
   структура результата не меняется (REQ-MILEAGE-07).
4. `AppFactory::create()`: собрать массив порогов для `DecisionEngine` из `rules['ltv']` и
   `rules['vehicle']['review_max_mileage_km']` (строка 37).
5. `DecisionEngineTest`: обновить существующий провайдер под новую сигнатуру (пробег ниже
   порога — поведение по LTV, REQ-MILEAGE-01); добавить случаи AC-MILEAGE-01, AC-MILEAGE-02,
   AC-MILEAGE-03, AC-MILEAGE-04, AC-MILEAGE-05, AC-MILEAGE-06 (LTV 70 % → `review`
   сохраняется, LTV 90 % → `reject` сохраняется).
6. `ApplicationValidatorTest`: добавить AC-MILEAGE-07 – AC-MILEAGE-12: 0 км и `""` допускаются
   (`""` нормализуется в 0 — REQ-MILEAGE-06), −1 км, 500 001 км, отсутствующее поле и `null` —
   `ValidationException` с ошибкой по полю `mileage` (REQ-MILEAGE-04/05).
7. `AssessmentServiceTest`: обновить `setUp()` (строка 27); добавить сквозные AC-MILEAGE-03 –
   AC-MILEAGE-06 (например, суммы/стоимость из текущего хелпера: 450 000/900 000 → LTV 50 %,
   630 000/900 000 → 70 %, 810 000/900 000 → 90 %), AC-MILEAGE-12 (`'mileage' => ''` → 0 →
   `approve`) и AC-MILEAGE-13 (ключи результата и `decision` из `approve`/`review`/`reject`).
8. Прогнать `make test` и `make lint` (php -l); все существующие тесты зелёные до и после.
   `make verify` в проекте нет.

## Тесты

Граничные значения (отдельными строками):

- Пробег 399 999 км, LTV 50 % → `approve`, порог не срабатывает (AC-MILEAGE-01, REQ-MILEAGE-01) — `DecisionEngineTest`.
- Пробег ровно 400 000 км, LTV 50 % → `approve`, «не больше» включительно (AC-MILEAGE-02, REQ-MILEAGE-01) — `DecisionEngineTest`.
- Пробег 400 001 км, LTV 50 % → `review`, первая отметка срабатывания (AC-MILEAGE-03, REQ-MILEAGE-02) — `DecisionEngineTest` + `AssessmentServiceTest`.
- Пробег 500 000 км, LTV 50 % → `review`, верхняя граница действия правила (AC-MILEAGE-05, REQ-MILEAGE-02) — `DecisionEngineTest` + `AssessmentServiceTest`.
- Пробег 0 км, LTV 50 % → заявка валидна, `approve`, нижняя граница валидации (AC-MILEAGE-07, REQ-MILEAGE-04) — `ApplicationValidatorTest` (валидность) + `DecisionEngineTest` (решение).
- Пробег −1 км → `ValidationException` с ошибкой по полю `mileage`, решение не считается (AC-MILEAGE-08, REQ-MILEAGE-04) — `ApplicationValidatorTest`.
- Пробег 500 001 км → `ValidationException` с ошибкой по полю `mileage`, решение не считается (AC-MILEAGE-09, REQ-MILEAGE-04) — `ApplicationValidatorTest`.

Пустой и неизвестный пробег:

- Поле `mileage` отсутствует → `ValidationException` с ошибкой по полю `mileage` (AC-MILEAGE-10, REQ-MILEAGE-05) — `ApplicationValidatorTest`.
- `"mileage": null` → `ValidationException` с ошибкой по полю `mileage` (AC-MILEAGE-11, REQ-MILEAGE-05) — `ApplicationValidatorTest`.
- `"mileage": ""`, LTV 50 % → приведение к 0, заявка валидна, `approve` — текущее поведение сохранено по спеке (AC-MILEAGE-12, REQ-MILEAGE-06) — `ApplicationValidatorTest` (нормализация в 0) + `AssessmentServiceTest` (решение). Превращение в ошибку валидации — OQ-MILEAGE-02, вне scope.

Сочетания пробег × LTV (пробег выше порога):

- Пробег 400 001 км + LTV 70 % (по LTV `review`) → `review` — решение по LTV сохраняется без изменений (AC-MILEAGE-04, REQ-MILEAGE-03) — `DecisionEngineTest` + `AssessmentServiceTest`.
- Пробег 400 001 км + LTV 90 % (по LTV `reject`) → `reject` — `reject` сохраняет приоритет над `review` по пробегу (AC-MILEAGE-06, REQ-MILEAGE-03, OQ-MILEAGE-01 закрыт 2026-10-12) — `DecisionEngineTest` + `AssessmentServiceTest`.

Структура ответа:

- `decision` принимает значение `approve` / `review` / `reject`; состав ключей результата
  `assess()` совпадает с текущим (`vehicle_age`, `ltv`, `decision`, `approved_limit`, `input`);
  `ApplicationController` задачей не правится — новых полей, включая причину `review`, нет
  (AC-MILEAGE-13, REQ-MILEAGE-07) — `AssessmentServiceTest`.

Регресс:

- Существующие тесты с пробегом ниже порога (96 000 км) остаются зелёными: заявки с пробегом
  ≤ 400 000 км решаются по LTV как раньше (REQ-MILEAGE-01); существующий тест высокого LTV —
  `reject` не меняется при низком пробеге.

## Риски

- **OQ-MILEAGE-01 закрыт** ответом постановщика (2026-10-12): `reject` сохраняет приоритет, в
  `review` переводится только `approve` (REQ-MILEAGE-03). Согласований, блокирующих шаги,
  больше нет; если постановщик сменит решение — меняются ветка движка (шаг 2) и тест
  AC-MILEAGE-06, остальное от варианта не зависит. OQ-MILEAGE-02 (пустая строка) остаётся
  отдельной задачей, вне scope.
- Правило действует только в диапазоне 400 001–500 000 км: валидация не меняется
  (REQ-MILEAGE-04), пробег > 500 000 даёт ошибку валидации, а не `review` (AC-MILEAGE-09).
  Если заказчик ждёт `review` и выше 500 000 — это изменение валидации, вне спеки MILEAGE.
- Смена сигнатуры `DecisionEngine::decide()` ломает все текущие вызовы и тесты — правки в
  `AssessmentService`, `DecisionEngineTest`, `AssessmentServiceTest` обязательны;
  внешних/композерских вызовов нет (проект монолитный).
- AC-MILEAGE-13 проверяется на unit-уровне (форма результата `assess()`); прямого HTTP-теста в
  репозитории нет — набор `tests/Feature` объявлен в `phpunit.xml`, но пуст и без договорённостей
  о запуске (нужны база/окружение). Добавление фиче-теста — отдельное решение, в MILEAGE не
  входит; риск низкий, так как `ApplicationController` задачей не правится.
- Пустая строка пробега: поведение «приводится к 0 и допускается» сохраняется (REQ-MILEAGE-06,
  AC-MILEAGE-12); существующее приведение `(int) ($payload['mileage'] ?? -1)`
  (`ApplicationValidator.php:43`) не трогается. Изменение поведения — OQ-MILEAGE-02, отдельная задача.
- Форма и БД не меняются: `mileage_km INT UNSIGNED NOT NULL` (`db/schema.sql:22`) вмещает все
  значения валидации; миграции не нужны.
- Не входит: расчёт `approved_limit` по `ltv_by_age` (задача LOAN-12), тексты ошибок валидации,
  причина `review` в ответе (REQ-MILEAGE-07), фронтенд, seed-данные.
- `make test` должен остаться зелёным до и после; `make lint` — php -l; `make verify` в проекте нет.
