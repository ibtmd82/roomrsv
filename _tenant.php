<?php

function redisConnectSocket($host = '127.0.0.1', $port = 6379, $timeout = 0.5)
{
    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout);
    if (!$socket) {
        return null;
    }
    stream_set_timeout($socket, 1);
    return $socket;
}

function redisEncodeCommand(array $parts)
{
    $cmd = '*' . count($parts) . "\r\n";
    foreach ($parts as $part) {
        $text = (string)$part;
        $cmd .= '$' . strlen($text) . "\r\n" . $text . "\r\n";
    }
    return $cmd;
}

function redisReadLine($socket)
{
    $line = fgets($socket);
    if ($line === false) {
        return null;
    }
    return rtrim($line, "\r\n");
}

function redisReadReply($socket)
{
    $prefix = fgetc($socket);
    if ($prefix === false) {
        return null;
    }

    if ($prefix === '+') {
        return redisReadLine($socket);
    }
    if ($prefix === '-') {
        $error = redisReadLine($socket);
        throw new RuntimeException($error ?: 'Redis error');
    }
    if ($prefix === ':') {
        return intval(redisReadLine($socket));
    }
    if ($prefix === '$') {
        $length = intval(redisReadLine($socket));
        if ($length < 0) {
            return null;
        }
        $data = '';
        $remaining = $length;
        while ($remaining > 0) {
            $chunk = fread($socket, $remaining);
            if ($chunk === false || $chunk === '') {
                return null;
            }
            $data .= $chunk;
            $remaining -= strlen($chunk);
        }
        fread($socket, 2); // trailing \r\n
        return $data;
    }
    if ($prefix === '*') {
        $count = intval(redisReadLine($socket));
        if ($count < 0) {
            return null;
        }
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = redisReadReply($socket);
        }
        return $items;
    }

    return null;
}

function redisCommand(array $parts)
{
    $socket = redisConnectSocket();
    if (!$socket) {
        return null;
    }

    $payload = redisEncodeCommand($parts);
    $writeOk = fwrite($socket, $payload);
    if ($writeOk === false) {
        fclose($socket);
        return null;
    }

    try {
        $reply = redisReadReply($socket);
    } catch (Throwable $e) {
        fclose($socket);
        return null;
    }

    fclose($socket);
    return $reply;
}

function redisCommandOnSocket($socket, array $parts)
{
    if (!$socket) {
        return null;
    }

    $payload = redisEncodeCommand($parts);
    $writeOk = fwrite($socket, $payload);
    if ($writeOk === false) {
        return null;
    }

    try {
        return redisReadReply($socket);
    } catch (Throwable $e) {
        return null;
    }
}

function getClientIdHeader()
{
    $keys = ['HTTP_CLIENTID', 'REDIRECT_HTTP_CLIENTID'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            return trim((string)$_SERVER[$key]);
        }
    }
    return '';
}

function resolveTenantContext()
{
    static $cachedContext = null;
    if ($cachedContext !== null) {
        return $cachedContext;
    }

    $context = [
        'clientid' => '',
        'tenant_id' => 1,
        'tenant_name' => 'Default Tenant',
        'tenant_description' => '',
    ];

    $clientid = getClientIdHeader();
    if ($clientid === '') {
        $cachedContext = $context;
        return $cachedContext;
    }

    $context['clientid'] = $clientid;

    $socket = redisConnectSocket();
    if (!$socket) {
        $cachedContext = $context;
        return $cachedContext;
    }

    $tenantId = redisCommandOnSocket($socket, ['HGET', "account:{$clientid}", 'tenant_id']);

    if ($tenantId === null || $tenantId === '') {
        fclose($socket);
        $cachedContext = $context;
        return $cachedContext;
    }

    $tenantId = intval($tenantId);
    if ($tenantId <= 0) {
        fclose($socket);
        $cachedContext = $context;
        return $cachedContext;
    }

    $context['tenant_id'] = $tenantId;

    $tenantData = redisCommandOnSocket($socket, ['HGETALL', "tenant:{$tenantId}"]);
    fclose($socket);
    if (is_array($tenantData) && count($tenantData) >= 2) {
        for ($i = 0; $i < count($tenantData); $i += 2) {
            $key = isset($tenantData[$i]) ? (string)$tenantData[$i] : '';
            $value = isset($tenantData[$i + 1]) ? (string)$tenantData[$i + 1] : '';
            if ($key === 'name') {
                $context['tenant_name'] = $value;
            } elseif ($key === 'description') {
                $context['tenant_description'] = $value;
            }
        }
    }

    $cachedContext = $context;
    return $cachedContext;
}
