<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$routeFile = $root.'/routes/tenant.php';
$reportFile = $root.'/docs/security/tenant-rbac-audit.md';

function read_file(string $path): string
{
    $contents = file_get_contents($path);

    return is_string($contents) ? $contents : '';
}

function class_to_path(string $root, string $class): string
{
    $relative = str_replace('\\', '/', $class);
    if (str_starts_with($relative, 'App/')) {
        $relative = 'app/'.substr($relative, 4);
    }

    return $root.'/'.$relative.'.php';
}

function controller_class(string $usesLine): ?string
{
    if (preg_match('/^use\s+(App\\\\Http\\\\Controllers\\\\[^;]+);/m', $usesLine, $match) === 1) {
        return $match[1];
    }

    return null;
}

function imported_controllers(string $source): array
{
    $imports = [];
    preg_match_all('/^use\s+(App\\\\Http\\\\Controllers\\\\[^;]+);/m', $source, $matches);

    foreach ($matches[1] as $class) {
        $short = substr($class, strrpos($class, '\\') + 1);
        $imports[$short] = $class;
    }

    return $imports;
}

function method_body(string $source, string $method): string
{
    $pattern = '/public\s+function\s+'.preg_quote($method, '/').'\s*\([^)]*\)[^{]*\{/m';
    if (preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
        return '';
    }

    $start = $match[0][1] + strlen($match[0][0]);
    $depth = 1;
    $length = strlen($source);

    for ($i = $start; $i < $length; $i++) {
        if ($source[$i] === '{') {
            $depth++;
        } elseif ($source[$i] === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($source, $start, $i - $start);
            }
        }
    }

    return '';
}

function method_signature(string $source, string $method): string
{
    $pattern = '/public\s+function\s+'.preg_quote($method, '/').'\s*\(([^)]*)\)/m';
    if (preg_match($pattern, $source, $match) !== 1) {
        return '';
    }

    return $match[1];
}

function form_request_class(string $signature): ?string
{
    if (preg_match('/\b([A-Z][A-Za-z0-9_]*Request)\s+\$[A-Za-z0-9_]+/', $signature, $match) !== 1) {
        return null;
    }

    return $match[1];
}

function imports(string $source): array
{
    $imports = [];
    preg_match_all('/^use\s+([^;]+);/m', $source, $matches);

    foreach ($matches[1] as $class) {
        $short = substr($class, strrpos($class, '\\') + 1);
        $imports[$short] = $class;
    }

    return $imports;
}

function route_is_exempt(string $path, string $controller): bool
{
    $authRoutes = [
        '/auth/login',
        '/auth/exchange-ticket',
        '/auth/password/forgot',
        '/auth/password/reset',
        '/auth/logout',
        '/auth/logout-all',
        '/auth/refresh',
    ];

    return in_array($path, $authRoutes, true)
        || str_starts_with($controller, 'TenantPasswordResetController@');
}

function request_authorization_signal(string $root, array $controllerImports, ?string $requestShort): ?string
{
    if ($requestShort === null) {
        return null;
    }

    $class = $controllerImports[$requestShort] ?? 'App\\Http\\Requests\\Tenant\\'.$requestShort;
    $path = class_to_path($root, $class);
    $source = read_file($path);

    if ($source === '') {
        return null;
    }

    $authorize = method_body($source, 'authorize');

    if (
        str_contains($authorize, '->can(')
        || str_contains($authorize, 'Gate::authorize')
        || str_contains($authorize, 'Gate::allows')
        || str_contains($authorize, 'Gate::denies')
        || str_contains($authorize, 'hasPermissionTo')
        || str_contains($authorize, 'permission')
    ) {
        return 'FormRequest::authorize(permission)';
    }

    if (
        str_contains($authorize, '->isAdmin(')
        || str_contains($authorize, '->hasAnyRole(')
        || str_contains($authorize, '->hasRole(')
    ) {
        return 'FormRequest::authorize(role)';
    }

    if (
        str_contains($authorize, '$this->user() !== null')
        || str_contains($authorize, '$this->user() === null')
        || str_contains($authorize, '$this->user()?')
        || str_contains($authorize, '$this->user()')
    ) {
        return 'FormRequest::authorize(authenticated)';
    }

    return null;
}

function controller_authorization_signal(string $body): ?string
{
    if (
        str_contains($body, '$this->authorize(')
        || str_contains($body, 'Gate::authorize(')
        || str_contains($body, 'Gate::allows(')
        || str_contains($body, 'Gate::denies(')
    ) {
        return 'controller authorize';
    }

    return null;
}

$source = read_file($routeFile);
$controllers = imported_controllers($source);

preg_match_all(
    '/Route::(post|put|patch|delete)\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*\[([A-Za-z0-9_]+)::class,\s*[\'"]([A-Za-z0-9_]+)[\'"]\]\s*\)(.*?);/s',
    $source,
    $matches,
    PREG_SET_ORDER
);

$rows = [];

foreach ($matches as $match) {
    [$statement, $verb, $path, $controllerShort, $method, $chain] = $match;
    $controllerClass = $controllers[$controllerShort] ?? null;
    $controllerPath = $controllerClass ? class_to_path($root, $controllerClass) : '';
    $controllerSource = $controllerPath ? read_file($controllerPath) : '';
    $body = $controllerSource ? method_body($controllerSource, $method) : '';
    $signature = $controllerSource ? method_signature($controllerSource, $method) : '';
    $controllerImports = $controllerSource ? imports($controllerSource) : [];
    $requestShort = form_request_class($signature);
    $controller = $controllerShort.'@'.$method;

    if (route_is_exempt($path, $controller)) {
        continue;
    }

    $checks = [
        'permission.gate' => str_contains($chain, 'permission.gate'),
        'tenant.admin' => str_contains($chain, 'tenant.admin'),
    ];

    $requestSignal = request_authorization_signal($root, $controllerImports, $requestShort);
    if ($requestSignal !== null) {
        $checks[$requestSignal] = true;
    }

    $controllerSignal = controller_authorization_signal($body);
    if ($controllerSignal !== null) {
        $checks[$controllerSignal] = true;
    }

    $covered = in_array(true, $checks, true);
    $rows[] = [
        'verb' => strtoupper($verb),
        'path' => $path,
        'controller' => $controller,
        'request' => $requestShort ?? '-',
        'checks' => array_keys(array_filter($checks)),
        'covered' => $covered,
    ];
}

$uncovered = array_values(array_filter($rows, fn (array $row): bool => ! $row['covered']));

$lines = [
    '# Auditoria RBAC das rotas tenant',
    '',
    'Gerado por `php scripts/security/audit_tenant_rbac.php`.',
    '',
    'Critério: rotas tenant mutáveis (`POST`, `PUT`, `PATCH` e `DELETE`) em `routes/tenant.php` devem ter ao menos um destes sinais objetivos: `permission.gate`, `tenant.admin`, `FormRequest::authorize()` com checagem de permissão, papel ou self-service autenticado, ou autorização explícita no controller. Rotas públicas/de ciclo de autenticação são excluídas do escopo de RBAC.',
    '',
    '## Resumo',
    '',
    '- Rotas mutáveis analisadas: '.count($rows),
    '- Rotas com cobertura objetiva: '.(count($rows) - count($uncovered)),
    '- Rotas para revisão manual: '.count($uncovered),
    '',
    '## Rotas Para Revisão Manual',
    '',
];

if ($uncovered === []) {
    $lines[] = 'Nenhuma rota mutável sem cobertura objetiva foi encontrada.';
} else {
    $lines[] = '| Método | Rota | Controller | Request |';
    $lines[] = '| --- | --- | --- | --- |';
    foreach ($uncovered as $row) {
        $lines[] = sprintf(
            '| `%s` | `%s` | `%s` | `%s` |',
            $row['verb'],
            $row['path'],
            $row['controller'],
            $row['request']
        );
    }
}

$lines[] = '';
$lines[] = '## Rotas Cobertas';
$lines[] = '';
$lines[] = '| Método | Rota | Controller | Sinais |';
$lines[] = '| --- | --- | --- | --- |';

foreach ($rows as $row) {
    if (! $row['covered']) {
        continue;
    }

    $lines[] = sprintf(
        '| `%s` | `%s` | `%s` | %s |',
        $row['verb'],
        $row['path'],
        $row['controller'],
        implode(', ', array_map(fn (string $check): string => '`'.$check.'`', $row['checks']))
    );
}

if (! is_dir(dirname($reportFile))) {
    mkdir(dirname($reportFile), 0775, true);
}

file_put_contents($reportFile, implode("\n", $lines)."\n");

echo "RBAC audit written to {$reportFile}\n";
echo "Mutable routes: ".count($rows)."\n";
echo "Needs review: ".count($uncovered)."\n";
