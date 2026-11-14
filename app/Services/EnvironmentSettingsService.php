<?php

namespace App\Services;

use Dotenv\Dotenv;
use RuntimeException;

class EnvironmentSettingsService
{
    public function __construct(private readonly ?string $filePath = null) {}

    public function groups(): array
    {
        return [
            [
                'title' => 'Приложение',
                'description' => 'Основной адрес, режим, локаль и доступ владельца.',
                'fields' => [
                    $this->field('APP_NAME', 'Название приложения', default: config('app.name', 'Laravel')),
                    $this->field('APP_ENV', 'Среда', 'select', ['local', 'testing', 'staging', 'production'], default: config('app.env', 'production')),
                    $this->field('APP_DEBUG', 'Режим отладки', 'boolean', default: false),
                    $this->field('APP_URL', 'Базовый URL', default: config('app.url', 'http://localhost')),
                    $this->field('ADMIN_EMAIL', 'Email владельца', 'email'),
                    $this->field('APP_LOCALE', 'Основная локаль', default: config('app.locale', 'en')),
                    $this->field('APP_FALLBACK_LOCALE', 'Резервная локаль', default: config('app.fallback_locale', 'en')),
                    $this->field('APP_FAKER_LOCALE', 'Локаль генератора тестовых данных', default: config('app.faker_locale', 'en_US')),
                    $this->field('APP_MAINTENANCE_DRIVER', 'Драйвер режима обслуживания', 'select', ['file', 'cache', 'database']),
                    $this->field('APP_MAINTENANCE_STORE', 'Хранилище режима обслуживания'),
                    $this->field('APP_KEY', 'Ключ шифрования', secret: true, editable: false, help: 'Неизменяемый ключ: его замена сделает недоступными существующие зашифрованные данные.'),
                    $this->field('APP_PREVIOUS_KEYS', 'Предыдущие ключи шифрования', secret: true),
                    $this->field('BCRYPT_ROUNDS', 'Количество раундов Bcrypt', 'number', min: 4, max: 31, default: '12'),
                    $this->field('PHP_CLI_SERVER_WORKERS', 'Воркеры встроенного PHP-сервера', 'number', min: 1, max: 64, editable: false, help: 'Применяется только при запуске встроенного сервера; измените команду запуска и перезапустите процесс.'),
                ],
            ],
            [
                'title' => 'База данных',
                'description' => 'Параметры подключения Laravel к основной БД. После смены подключения проверьте доступность базы до перезапуска worker.',
                'fields' => [
                    $this->field('DB_CONNECTION', 'Драйвер БД', 'select', ['sqlite', 'mysql', 'mariadb', 'pgsql', 'sqlsrv'], default: config('database.default', 'sqlite')),
                    $this->field('DB_URL', 'URL подключения', secret: true),
                    $this->field('DB_HOST', 'Хост'),
                    $this->field('DB_PORT', 'Порт', 'number', min: 1, max: 65535),
                    $this->field('DB_DATABASE', 'Имя базы / путь SQLite'),
                    $this->field('DB_USERNAME', 'Пользователь'),
                    $this->field('DB_PASSWORD', 'Пароль', secret: true),
                    $this->field('DB_SOCKET', 'Unix socket'),
                    $this->field('DB_FOREIGN_KEYS', 'Проверять внешние ключи', 'boolean', default: true),
                    $this->field('DB_CHARSET', 'Кодировка'),
                    $this->field('DB_COLLATION', 'Сопоставление'),
                    $this->field('DOCKER_DB_PORT', 'Порт MySQL на хосте Docker', 'number', min: 1, max: 65535, editable: false, help: 'Параметр Docker Compose, не применяется работающим Laravel-приложением.'),
                ],
            ],
            [
                'title' => 'Redis, кеш и Memcached',
                'description' => 'Redis используется в том числе политиками надежности шлюза.',
                'fields' => [
                    $this->field('REDIS_CLIENT', 'Клиент Redis', 'select', ['phpredis', 'predis']),
                    $this->field('REDIS_URL', 'URL подключения Redis', secret: true),
                    $this->field('REDIS_HOST', 'Хост Redis', default: config('database.redis.default.host', '127.0.0.1')),
                    $this->field('REDIS_USERNAME', 'Пользователь Redis'),
                    $this->field('REDIS_PASSWORD', 'Пароль Redis', secret: true),
                    $this->field('REDIS_PORT', 'Порт Redis', 'number', min: 1, max: 65535, default: config('database.redis.default.port', '6379')),
                    $this->field('REDIS_DB', 'Номер основной БД Redis', 'number', min: 0, max: 15, default: '0'),
                    $this->field('REDIS_CACHE_DB', 'Номер БД Redis для кеша', 'number', min: 0, max: 15, default: '1'),
                    $this->field('REDIS_PREFIX', 'Префикс ключей Redis'),
                    $this->field('REDIS_MAX_RETRIES', 'Повторные подключения Redis', 'number', min: 0, max: 100, default: '3'),
                    $this->field('REDIS_BACKOFF_ALGORITHM', 'Алгоритм задержки Redis'),
                    $this->field('REDIS_BACKOFF_BASE', 'Начальная задержка Redis, мс', 'number', min: 0, max: 60000, default: '100'),
                    $this->field('REDIS_BACKOFF_CAP', 'Максимальная задержка Redis, мс', 'number', min: 0, max: 60000, default: '1000'),
                    $this->field('CACHE_STORE', 'Драйвер кеша', 'select', ['array', 'database', 'file', 'memcached', 'redis', 'dynamodb'], default: config('cache.default', 'database')),
                    $this->field('CACHE_DRIVER', 'Старое имя драйвера кеша (Docker)', 'select', ['array', 'database', 'file', 'memcached', 'redis']),
                    $this->field('CACHE_PREFIX', 'Префикс ключей кеша'),
                    $this->field('MEMCACHED_HOST', 'Хост Memcached'),
                    $this->field('MEMCACHED_PORT', 'Порт Memcached', 'number', min: 1, max: 65535, default: '11211'),
                    $this->field('MEMCACHED_USERNAME', 'Пользователь Memcached'),
                    $this->field('MEMCACHED_PASSWORD', 'Пароль Memcached', secret: true),
                ],
            ],
            [
                'title' => 'Очереди, сессии и трансляции',
                'description' => 'После изменения драйвера очереди или сессий перезапустите долгоживущие процессы.',
                'fields' => [
                    $this->field('QUEUE_CONNECTION', 'Драйвер очереди', 'select', ['sync', 'database', 'redis', 'sqs', 'beanstalkd'], default: config('queue.default', 'database')),
                    $this->field('DB_QUEUE_CONNECTION', 'Подключение БД для очереди'),
                    $this->field('DB_QUEUE_TABLE', 'Таблица очереди БД', default: 'jobs'),
                    $this->field('DB_QUEUE', 'Имя очереди БД', default: 'default'),
                    $this->field('DB_QUEUE_RETRY_AFTER', 'Время до повторной попытки БД, сек', 'number', min: 1, max: 86400, default: '90'),
                    $this->field('BEANSTALKD_QUEUE_HOST', 'Хост Beanstalkd'),
                    $this->field('BEANSTALKD_QUEUE', 'Имя очереди Beanstalkd', default: 'default'),
                    $this->field('BEANSTALKD_QUEUE_RETRY_AFTER', 'Время до повторной попытки Beanstalkd, сек', 'number', min: 1, max: 86400, default: '90'),
                    $this->field('REDIS_QUEUE_CONNECTION', 'Подключение Redis для очереди', default: 'default'),
                    $this->field('REDIS_QUEUE', 'Имя очереди Redis', default: 'default'),
                    $this->field('REDIS_QUEUE_RETRY_AFTER', 'Время до повторной попытки Redis, сек', 'number', min: 1, max: 86400, default: '90'),
                    $this->field('SQS_PREFIX', 'URL-префикс Amazon SQS'),
                    $this->field('SQS_QUEUE', 'Имя очереди Amazon SQS', default: 'default'),
                    $this->field('SQS_SUFFIX', 'Суффикс очереди Amazon SQS'),
                    $this->field('QUEUE_FAILED_DRIVER', 'Хранилище неудачных заданий', 'select', ['database-uuids', 'database', 'file', 'null']),
                    $this->field('SESSION_DRIVER', 'Драйвер сессий', 'select', ['cookie', 'database', 'file', 'redis', 'memcached', 'array'], default: config('session.driver', 'database')),
                    $this->field('SESSION_LIFETIME', 'Срок сессии, минут', 'number', min: 1, max: 525600, default: '120'),
                    $this->field('SESSION_ENCRYPT', 'Шифровать сессии', 'boolean', default: false),
                    $this->field('SESSION_CONNECTION', 'Подключение для сессий'),
                    $this->field('SESSION_TABLE', 'Таблица сессий', default: 'sessions'),
                    $this->field('SESSION_STORE', 'Хранилище сессий'),
                    $this->field('SESSION_PATH', 'Путь cookie', default: '/'),
                    $this->field('SESSION_DOMAIN', 'Домен cookie'),
                    $this->field('SESSION_SECURE_COOKIE', 'Только HTTPS для cookie', 'boolean'),
                    $this->field('SESSION_HTTP_ONLY', 'HttpOnly для cookie', 'boolean', default: true),
                    $this->field('SESSION_SAME_SITE', 'SameSite для cookie', 'select', ['lax', 'strict', 'none']),
                    $this->field('BROADCAST_CONNECTION', 'Драйвер трансляции событий', 'select', ['log', 'null', 'redis', 'pusher', 'ably']),
                    $this->field('PUSHER_APP_ID', 'Pusher App ID'),
                    $this->field('PUSHER_APP_KEY', 'Pusher App Key'),
                    $this->field('PUSHER_APP_SECRET', 'Pusher App Secret', secret: true),
                    $this->field('PUSHER_APP_CLUSTER', 'Pusher Cluster'),
                    $this->field('PUSHER_HOST', 'Pusher host'),
                    $this->field('PUSHER_PORT', 'Pusher port', 'number', min: 1, max: 65535),
                    $this->field('PUSHER_SCHEME', 'Pusher scheme', 'select', ['http', 'https']),
                    $this->field('ABLY_KEY', 'Ably API Key', secret: true),
                ],
            ],
            [
                'title' => 'Почта и уведомления',
                'description' => 'Пароли и токены не отображаются. Пустое поле секрета оставляет сохраненное значение без изменений.',
                'fields' => [
                    $this->field('MAIL_MAILER', 'Почтовый драйвер', 'select', ['smtp', 'sendmail', 'log', 'array', 'ses', 'postmark', 'resend', 'failover', 'roundrobin'], default: config('mail.default', 'log')),
                    $this->field('MAIL_SCHEME', 'Схема SMTP', 'select', ['', 'smtp', 'smtps', 'tls', 'ssl']),
                    $this->field('MAIL_URL', 'URL почтового подключения', secret: true),
                    $this->field('MAIL_HOST', 'SMTP хост'),
                    $this->field('MAIL_PORT', 'SMTP порт', 'number', min: 1, max: 65535),
                    $this->field('MAIL_USERNAME', 'SMTP пользователь'),
                    $this->field('MAIL_PASSWORD', 'SMTP пароль', secret: true),
                    $this->field('MAIL_ENCRYPTION', 'Шифрование SMTP', 'select', ['', 'tls', 'ssl']),
                    $this->field('MAIL_EHLO_DOMAIN', 'SMTP EHLO домен'),
                    $this->field('MAIL_SENDMAIL_PATH', 'Команда Sendmail'),
                    $this->field('MAIL_FROM_ADDRESS', 'Адрес отправителя'),
                    $this->field('MAIL_FROM_NAME', 'Имя отправителя'),
                    $this->field('POSTMARK_TOKEN', 'Postmark API Token', secret: true),
                    $this->field('RESEND_KEY', 'Resend API Key', secret: true),
                    $this->field('SLACK_BOT_USER_OAUTH_TOKEN', 'Slack Bot Token', secret: true),
                    $this->field('SLACK_BOT_USER_DEFAULT_CHANNEL', 'Канал Slack'),
                ],
            ],
            [
                'title' => 'Файловое хранилище и AWS',
                'description' => 'Для S3-подключения после изменения проверьте диск и перезапустите workers.',
                'fields' => [
                    $this->field('FILESYSTEM_DISK', 'Диск по умолчанию', 'select', ['local', 'public', 's3'], default: config('filesystems.default', 'local')),
                    $this->field('AWS_ACCESS_KEY_ID', 'AWS Access Key ID', secret: true),
                    $this->field('AWS_SECRET_ACCESS_KEY', 'AWS Secret Access Key', secret: true),
                    $this->field('AWS_SESSION_TOKEN', 'AWS Session Token', secret: true),
                    $this->field('AWS_DEFAULT_REGION', 'AWS регион'),
                    $this->field('AWS_BUCKET', 'S3 Bucket'),
                    $this->field('AWS_URL', 'AWS URL'),
                    $this->field('AWS_ENDPOINT', 'S3 совместимый endpoint'),
                    $this->field('AWS_USE_PATH_STYLE_ENDPOINT', 'Path-style для S3', 'boolean', default: false),
                    $this->field('DYNAMODB_CACHE_TABLE', 'Таблица DynamoDB кеша'),
                    $this->field('DYNAMODB_ENDPOINT', 'DynamoDB endpoint'),
                ],
            ],
            [
                'title' => 'Вебхуки и безопасность',
                'description' => 'APP_KEY и ключи шифрования остаются неизменяемыми из панели.',
                'fields' => [
                    $this->field('WEBHOOK_TIMEOUT', 'Таймаут исходящего webhook, сек', 'number', min: 1, max: 600, default: '30'),
                    $this->field('WEBHOOK_MAX_RETRIES', 'Максимум повторов webhook', 'number', min: 1, max: 100, default: '3'),
                    $this->field('WEBHOOK_RETRY_DELAY', 'Задержка повтора webhook, сек', 'number', min: 1, max: 86400, default: '5'),
                    $this->field('JWT_SECRET', 'JWT Secret', secret: true),
                    $this->field('API_RATE_LIMIT', 'Лимит API запросов', 'number', min: 1, max: 1000000, default: '60'),
                    $this->field('AUTH_GUARD', 'Auth Guard'),
                    $this->field('AUTH_PASSWORD_BROKER', 'Password Broker'),
                    $this->field('AUTH_PASSWORD_TIMEOUT', 'Таймаут подтверждения пароля, сек', 'number', min: 1, max: 86400, default: '10800'),
                ],
            ],
            [
                'title' => 'Логи и диагностика',
                'description' => 'Не включайте debug на публичном production-сайте.',
                'fields' => [
                    $this->field('LOG_CHANNEL', 'Канал логирования', 'select', ['stack', 'single', 'daily', 'slack', 'papertrail', 'stderr', 'syslog', 'errorlog', 'null'], default: config('logging.default', 'stack')),
                    $this->field('LOG_STACK', 'Каналы stack через запятую'),
                    $this->field('LOG_DEPRECATIONS_CHANNEL', 'Канал deprecation-логов', 'select', ['', 'null', 'single', 'daily', 'stderr']),
                    $this->field('LOG_DEPRECATIONS_TRACE', 'Добавлять stack trace deprecation', 'boolean', default: false),
                    $this->field('LOG_LEVEL', 'Уровень логирования', 'select', ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency']),
                    $this->field('LOG_DAILY_DAYS', 'Хранить daily-логи, дней', 'number', min: 1, max: 3650, default: '14'),
                    $this->field('LOG_SLACK_WEBHOOK_URL', 'Slack log webhook URL', secret: true),
                    $this->field('LOG_SLACK_USERNAME', 'Имя Slack-бота'),
                    $this->field('LOG_SLACK_EMOJI', 'Emoji Slack-бота'),
                    $this->field('PAPERTRAIL_URL', 'Papertrail host'),
                    $this->field('PAPERTRAIL_PORT', 'Papertrail port', 'number', min: 1, max: 65535),
                    $this->field('TELESCOPE_ENABLED', 'Включить Telescope', 'boolean', default: true),
                    $this->field('TELESCOPE_DOMAIN', 'Домен Telescope'),
                    $this->field('TELESCOPE_PATH', 'Путь Telescope', default: 'telescope'),
                    $this->field('TELESCOPE_DRIVER', 'Хранилище Telescope', 'select', ['database', 'redis']),
                ],
            ],
            [
                'title' => 'Параметры только для сборки или Docker',
                'description' => 'Эти значения показаны для справки, но не изменяются отсюда: они обрабатываются Vite/Docker до запуска Laravel.',
                'fields' => [
                    $this->field('VITE_APP_NAME', 'Имя приложения Vite', editable: false, help: 'Для применения пересоберите frontend assets.'),
                    $this->field('DOCKER_APP_PORT', 'Порт приложения Docker', 'number', editable: false),
                    $this->field('DOCKER_REDIS_PORT', 'Порт Redis Docker на хосте', 'number', editable: false),
                    $this->field('DB_ROOT_PASSWORD', 'Пароль root MySQL Docker', secret: true, editable: false),
                ],
            ],
        ];
    }

    public function formGroups(): array
    {
        $values = $this->readValues();

        return array_map(function (array $group) use ($values): array {
            $group['fields'] = array_map(function (array $field) use ($values): array {
                $key = $field['key'];
                $value = $values[$key] ?? null;
                $configured = array_key_exists($key, $values) && $value !== null && $value !== '';

                if (! array_key_exists($key, $values)) {
                    $value = $field['default'];
                    $configured = $value !== null && $value !== '';
                }

                $field['configured'] = $configured;
                $field['value'] = $field['secret']
                    ? ''
                    : ($value === null ? 'null' : (string) $value);

                return $field;
            }, $group['fields']);

            return $group;
        }, $this->groups());
    }

    public function editableFields(): array
    {
        $fields = [];
        foreach ($this->groups() as $group) {
            foreach ($group['fields'] as $field) {
                if ($field['editable']) {
                    $fields[$field['key']] = $field;
                }
            }
        }

        return $fields;
    }

    public function activeFileName(): string
    {
        return basename($this->path());
    }

    public function isWritable(): bool
    {
        return is_file($this->path()) && is_writable($this->path());
    }

    public function save(array $settings, array $clearSecrets = []): array
    {
        $fields = $this->editableFields();
        $updates = [];

        foreach ($settings as $key => $value) {
            if (! isset($fields[$key])) {
                throw new RuntimeException('Получен неизвестный параметр окружения.');
            }

            $field = $fields[$key];
            if ($field['secret'] && ($value === null || $value === '') && ! in_array($key, $clearSecrets, true)) {
                continue;
            }

            $updates[$key] = $field['type'] === 'boolean'
                ? (filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false')
                : (string) ($value ?? '');
        }

        foreach ($clearSecrets as $key) {
            if (! isset($fields[$key]) || ! $fields[$key]['secret']) {
                throw new RuntimeException('Очищать можно только секретные параметры.');
            }
            $updates[$key] = '';
        }

        if ($updates === []) {
            return [];
        }

        $path = $this->path();
        $handle = fopen($path, 'r+');
        if ($handle === false) {
            throw new RuntimeException('Не удалось открыть активный файл окружения для записи.');
        }

        if (! flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new RuntimeException('Не удалось заблокировать файл окружения для записи.');
        }

        try {
            rewind($handle);
            $contents = stream_get_contents($handle);
            if ($contents === false) {
                throw new RuntimeException('Не удалось прочитать активный файл окружения.');
            }

            $updatedContents = $this->replaceValues($contents, $updates);
            try {
                Dotenv::parse($updatedContents);
            } catch (\Dotenv\Exception\InvalidFileException $exception) {
                throw new RuntimeException('Настройки не сохранены: итоговый файл окружения содержит синтаксическую ошибку.', previous: $exception);
            }

            rewind($handle);
            if (! ftruncate($handle, 0)) {
                throw new RuntimeException('Не удалось сохранить настройки в активный файл окружения.');
            }
            $written = 0;
            $length = strlen($updatedContents);
            while ($written < $length) {
                $bytes = fwrite($handle, substr($updatedContents, $written));
                if ($bytes === false || $bytes === 0) {
                    throw new RuntimeException('Не удалось сохранить настройки в активный файл окружения.');
                }
                $written += $bytes;
            }
            if (! fflush($handle)) {
                throw new RuntimeException('Не удалось сохранить настройки в активный файл окружения.');
            }

            return array_keys($updates);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function readValues(): array
    {
        $path = $this->path();
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Не удалось прочитать активный файл окружения.');
        }

        try {
            $values = Dotenv::parse($contents);
        } catch (\Dotenv\Exception\InvalidFileException $exception) {
            throw new RuntimeException('Активный файл окружения содержит синтаксическую ошибку.', previous: $exception);
        }

        foreach ($this->editableFields() + $this->readOnlyFields() as $key => $field) {
            if (! array_key_exists($key, $values)) {
                $externalValue = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
                if (is_string($externalValue)) {
                    $values[$key] = $externalValue;
                }
            }
        }

        return $values;
    }

    private function readOnlyFields(): array
    {
        $fields = [];
        foreach ($this->groups() as $group) {
            foreach ($group['fields'] as $field) {
                if (! $field['editable']) {
                    $fields[$field['key']] = $field;
                }
            }
        }

        return $fields;
    }

    private function replaceValues(string $contents, array $updates): string
    {
        $newline = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $lines = preg_split('/\r\n|\n|\r/', $contents);
        $replaced = [];

        foreach ($lines as $index => $line) {
            foreach ($updates as $key => $value) {
                if (! preg_match('/^\s*'.preg_quote($key, '/').'\s*=/', $line)) {
                    continue;
                }

                if (isset($replaced[$key])) {
                    $lines[$index] = null;
                    break;
                }

                $lines[$index] = $key.'='.$this->encodeValue($value);
                $replaced[$key] = true;
                break;
            }
        }

        $lines = array_values(array_filter($lines, static fn ($line): bool => $line !== null));
        foreach ($updates as $key => $value) {
            if (! isset($replaced[$key])) {
                $lines[] = $key.'='.$this->encodeValue($value);
            }
        }

        $updated = implode($newline, $lines);

        return str_ends_with($contents, "\n") || str_ends_with($contents, "\r")
            ? $updated.$newline
            : $updated;
    }

    private function encodeValue(string $value): string
    {
        if ($value === '' || in_array($value, ['null', 'true', 'false'], true)) {
            return $value;
        }

        $escaped = strtr($value, [
            '\\' => '\\\\',
            '"' => '\\"',
            '$' => '\\$',
            "\r" => '\\r',
            "\n" => '\\n',
        ]);

        return '"'.$escaped.'"';
    }

    private function path(): string
    {
        if ($this->filePath !== null) {
            return $this->filePath;
        }

        $path = app()->environmentFilePath();
        $basePath = realpath(base_path());
        $realPath = realpath($path);
        if ($basePath === false || $realPath === false || ! str_starts_with($realPath, $basePath.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Активный файл окружения отсутствует или расположен вне проекта.');
        }

        return $realPath;
    }

    private function field(
        string $key,
        string $label,
        string $type = 'text',
        array $options = [],
        bool $secret = false,
        bool $editable = true,
        ?string $help = null,
        int|string|null $min = null,
        int|string|null $max = null,
        string|bool|null $default = null
    ): array {
        return compact('key', 'label', 'type', 'options', 'secret', 'editable', 'help', 'min', 'max', 'default');
    }
}
