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
        ['tenant_id' => 3, 'name' => 'RoomRSV Branch B', 'description' => 'Branch B tenant'],
    ];

    foreach ($tenants as $tenant) {
        $id = (int)$tenant['tenant_id'];
        // Redis on CentOS 7 is often old; avoid multi-field HSET.
        redisExec(['HSET', "tenant:{$id}", 'tenant_id', (string)$id]);
        redisExec(['HSET', "tenant:{$id}", 'name', $tenant['name']]);
        redisExec(['HSET', "tenant:{$id}", 'description', $tenant['description']]);
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
        [
            'account_id' => 'test02',
            'tenant_id' => 2,
            'password' => '12345',
        ],
        [
            'account_id' => 'test03',
            'tenant_id' => 3,
            'password' => '12345',
        ],
    ];

    foreach ($accounts as $account) {
        $accountId = (string)$account['account_id'];
        $tenantId = (int)$account['tenant_id'];
        $password = (string)$account['password'];

        // Redis on CentOS 7 is often old; avoid multi-field HSET.
        redisExec(['HSET', "account:{$accountId}", 'account_id', $accountId]);
        redisExec(['HSET', "account:{$accountId}", 'tenant_id', (string)$tenantId]);
        redisExec(['HSET', "account:{$accountId}", 'password', $password]);
        redisExec(['SADD', 'accounts:all', $accountId]);
    }
}

try {
    // Quick ping check.
    redisExec(['PING']);

    seedTenants();
    seedAccounts();

    echo "Redis seed completed.\n";
    echo "Accounts: E46594ED => tenant 1, A9F22B1C => tenant 2, test01 => tenant 1, test02 => tenant 2, test03 => tenant 3\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Redis seed failed: " . $e->getMessage() . "\n";
}
