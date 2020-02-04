# Basic classes for placebook's framework

## Install

```
composer require "placebook/framework-basic"
```


## Классы

### Placebook\Framework\Core\Api
    Предназначен для отправки запросок к API на GraphQL и обработке ответов
    Поддерживает отправку http заголовка Accept-Language, указананных в config.json - файле по пути acceptLanguage
    Поддерживает отправку http заголовков, указананных в config.json - файле по пути api.extraHeaders
    Токен для доступа к API и сслылку на API получает как аргументы. Если не указаны, смотрит на константы API_URL и API_TOKEN


### Placebook\Framework\Core\Http
    Предназначен для выполнения http запросов

### Placebook\Framework\Core\SystemConfig
    Предназначен для работы с настройками системы в json файле
