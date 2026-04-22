<?php
require_once '_tenant.php';

function redisExec(array $parts)
{
    $result = redisCommand($parts);
    if ($result === null) {
        throw new RuntimeException('Redis command failed: ' . implode(' ', $parts));
    }
    return $result;
}

function seedTenants()
{
    $tenants = [
        ['tenant_id' => 1, 'name' => 'RoomRSV Demo', 'description' => 'Default tenant for demo environment'],
        ['tenant_id' => 2, 'name' => 'RoomRSV Branch A', 'description' => 'Branch A tenant'],
    ];

    foreach ($tenants as $tenant) {
        $id = (int)$tenant['tenant_id'];
        redisExec([
            'HSET', "tenant:{$id}",
            'tenant_id', (string)$id,
            'name', $tenant['name'],
            'description', $tenant['description'],
        ]);
        redisExec(['SADD', 'tenants:all', (string)$id]);
    }
}

function seedAccounts()
{
    $accounts = [
        [
            'account_id' => 'E46594ED',
            'tenant_id' => 1,
            'password' => '123456',
        ],
        [
            'account_id' => 'A9F22B1C',
            'tenant_id' => 2,
            'password' => '123456',
        ],
        [
            'account_id' => 'test01',
            'tenant_id' => 1,
            'password' => '12345',
        ],
    ];

    foreach ($accounts as $account) {
        $accountId = (string)$account['account_id'];
        $tenantId = (int)$account['tenant_id'];
        $password = (string)$account['password'];

        redisExec([
            'HSET', "account:{$accountId}",
            'account_id', $accountId,
            'tenant_id', (string)$tenantId,
            'password', $password,
        ]);
        redisExec(['SADD', 'accounts:all', $accountId]);
    }
}

try {
    // Quick ping check.
    redisExec(['PING']);

    seedTenants();
    seedAccounts();

    echo "Redis seed completed.\n";
    echo "Accounts: E46594ED => tenant 1, A9F22B1C => tenant 2\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Redis seed failed: " . $e->getMessage() . "\n";
}
