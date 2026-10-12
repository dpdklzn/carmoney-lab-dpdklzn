# Карта кода для правила пробега

Основа: ответ Kilo в режиме ask, сверенный с файлами репозитория.
Код приложения в задании 1.2.2 не менялся.

## Файлы и порядок вызовов

- `backend/config/rules.php` содержит пороги VIN, возраста, пробега, суммы, срока и LTV.
- `backend/src/Domain/AssessmentService.php` → `assess($payload)` управляет оценкой заявки.
- Сначала `backend/src/Domain/ApplicationValidator.php` → `validate($payload)` нормализует поля и проверяет VIN, год и возраст, пробег, стоимость, сумму и срок. VIN проверяется через `VinValidator::isValid()`, возраст — через `VehicleAge::inYears()`. Ошибки приводят к `ValidationException`; расчёт решения не продолжается.
- Затем `backend/src/Domain/LtvCalculator.php` → `calculate($requestedAmount, $marketValue)` считает `round(requestedAmount / marketValue * 100, 2)`.
- Затем `backend/src/Domain/DecisionEngine.php` → `decide($ltv)` возвращает решение по LTV: `< 60` — `approve`, `60 <= LTV <= 85` — `review`, `> 85` — `reject`. Значения берутся из `rules['ltv']`. Ровно 60 сейчас даёт `review`, хотя комментарии в коде описывают границу иначе.
- В конце `AssessmentService::assess()` вызывает `VehicleAge::inYears()` для результата и возвращает возраст, LTV, решение, нормализованный ввод и `approved_limit`: запрошенную сумму при `approve`, иначе 0. Справочник `ltv_by_age` для лимита пока не используется.

## Точка вставки правила

Предлагаемое место — `DecisionEngine::decide()`, перед первой проверкой LTV (`DecisionEngine.php:32`). Сейчас метод получает только `float $ltv`. Для правила «пробег > 400 000 км → review» нужно передать пробег из `AssessmentService::assess()`: значение уже есть в `$input['mileage']`, но вызов на строке 33 передаёт только LTV.

Порог 400 000 в конфиге отсутствует. Предлагаемый новый ключ — `vehicle.review_max_mileage_km`; название ещё не закреплено. Число должно храниться в `backend/config/rules.php`, а не в методе. Понадобится также передать порог при сборке `DecisionEngine`: сейчас `backend/src/AppFactory.php:37` передаёт только `$rules['ltv']`.

Перед реализацией нужно определить приоритет правил: должна ли большая величина пробега давать `review` даже при LTV > 85, или `reject` сохраняет приоритет. Это разные варианты порядка проверок; готовой логики такого сочетания в коде нет.

Существующие строки вокруг предложенной точки вставки (`DecisionEngine.php:30`), сверенные с файлом:

```php
    public function decide(float $ltv): string
    {
        if ($ltv < $this->approveMax) {
            return self::APPROVE;
        }
```

## Что уже проверяется про пробег

В `ApplicationValidator::validate()` (`ApplicationValidator.php:43`) пробег приводится к `int`; при отсутствии поля используется -1. Проверка допускает диапазон от 0 до `rules['vehicle']['max_mileage_km']` включительно. Сейчас верхний предел — 500 000 км (`rules.php:23`). За пределами диапазона возникает ошибка валидации, а не решение `review`.

Поэтому при сохранении текущей валидации новое правило сможет обработать пробег от 400 001 до 500 000 км; ровно 400 000 его не активирует. Заявки с пробегом выше 500 000 не дойдут до `DecisionEngine`. Нужно отдельно уточнить, должна ли будущая фича менять это поведение.

Сейчас пробег не влияет на решение в `DecisionEngine` и не участвует в `LtvCalculator`, `VehicleAge` или `VinValidator`. Новых входных полей для пробега не требуется: не хватает порога и передачи существующего значения в движок решения.
