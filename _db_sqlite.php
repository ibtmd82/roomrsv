<?php

$db_exists = file_exists("daypilot.sqlite");

$db = new PDO('sqlite:daypilot.sqlite');

function columnExists($dbh, $table, $column) {
    $stmt = $dbh->query("PRAGMA table_info($table)");
    $columns = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($columns as $c) {
        if (isset($c['name']) && $c['name'] === $column) {
            return true;
        }
    }
    return false;
}

if (!$db_exists) {
    //create the database
    $db->exec("CREATE TABLE IF NOT EXISTS rooms (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        tenant_id INTEGER DEFAULT 1,
                        name TEXT,
                        capacity INTEGER,
                        status VARCHAR(30),
                        price REAL DEFAULT 0)");

    $db->exec("CREATE TABLE IF NOT EXISTS reservations (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        tenant_id INTEGER DEFAULT 1,
                        customer_id INTEGER NULL,
                        name TEXT,
                        start DATETIME,
                        `end` DATETIME,
                        rental_type TEXT DEFAULT 'short_term',
                        room_id INTEGER,
                        status VARCHAR(30),
                        paid INTEGER,
                        room_price REAL DEFAULT 0,
                        discount_type VARCHAR(20) DEFAULT 'fixed',
                        discount_value REAL DEFAULT 0,
                        final_price REAL DEFAULT 0)");

    $db->exec("CREATE TABLE IF NOT EXISTS customers (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        tenant_id INTEGER DEFAULT 1,
                        full_name TEXT,
                        phone_number TEXT,
                        id_type TEXT,
                        id_number TEXT,
                        birthday DATE NULL)");

    $db->exec("CREATE TABLE IF NOT EXISTS reservation_service_fees (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        tenant_id INTEGER DEFAULT 1,
                        reservation_id INTEGER NOT NULL,
                        invoice_id INTEGER NULL,
                        fee_type TEXT,
                        charge_mode TEXT DEFAULT 'one_time',
                        description TEXT,
                        meter_start REAL DEFAULT 0,
                        meter_end REAL DEFAULT 0,
                        period_start DATE NULL,
                        period_end DATE NULL,
                        amount REAL DEFAULT 0)");

    $db->exec("CREATE TABLE IF NOT EXISTS reservation_invoices (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        tenant_id INTEGER DEFAULT 1,
                        reservation_id INTEGER NOT NULL,
                        cycle_index INTEGER DEFAULT 1,
                        period_start DATE NOT NULL,
                        period_end DATE NOT NULL,
                        occupied_days INTEGER DEFAULT 0,
                        month_days INTEGER DEFAULT 0,
                        room_amount REAL DEFAULT 0,
                        service_amount REAL DEFAULT 0,
                        total_amount REAL DEFAULT 0,
                        payment_status TEXT DEFAULT 'unpaid',
                        payment_method TEXT NULL,
                        paid_amount REAL DEFAULT 0,
                        paid_at DATETIME NULL,
                        payment_ref TEXT NULL,
                        payment_note TEXT NULL)");

    $db->exec("CREATE TABLE IF NOT EXISTS reservation_contract_terms (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        tenant_id INTEGER DEFAULT 1,
                        reservation_id INTEGER NOT NULL,
                        electric_unit_price REAL DEFAULT 3000,
                        water_pricing_mode TEXT DEFAULT 'quota',
                        water_quota_price REAL DEFAULT 500,
                        water_per_person_price REAL DEFAULT 100000,
                        occupants_count INTEGER DEFAULT 1)");

    $rooms = array(
                    array('name' => 'Room 1',
                        'id' => 1,
                        'tenant_id' => 1,
                        'capacity' => 2,
                        'status' => 'Dirty',
                        'price' => 600000),
                    array('name' => 'Room 2',
                        'id' => 2,
                        'tenant_id' => 1,
                        'capacity' => 2,
                        'status' => "Cleanup",
                        'price' => 650000),
                    array('name' => 'Room 3',
                        'id' => 3,
                        'tenant_id' => 1,
                        'capacity' => 2,
                        'status' => "Ready",
                        'price' => 700000),
                    array('name' => 'Room 4',
                        'id' => 4,
                        'tenant_id' => 1,
                        'capacity' => 4,
                        'status' => "Ready",
                        'price' => 1000000),
                    array('name' => 'Room 5',
                        'id' => 5,
                        'tenant_id' => 1,
                        'capacity' => 1,
                        'status' => "Ready",
                        'price' => 500000)
        );

    $insert = "INSERT INTO rooms (id, tenant_id, name, capacity, status, price) VALUES (:id, :tenant_id, :name, :capacity, :status, :price)";
    $stmt = $db->prepare($insert);

    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':tenant_id', $tenant_id);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':capacity', $capacity);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':price', $price);

    foreach ($rooms as $r) {
      $id = $r['id'];
      $tenant_id = $r['tenant_id'];
      $name = $r['name'];
      $capacity = $r['capacity'];
      $status = $r['status'];
      $price = $r['price'];
      $stmt->execute();
    }

}

if (!columnExists($db, "rooms", "tenant_id")) {
    $db->exec("ALTER TABLE rooms ADD COLUMN tenant_id INTEGER DEFAULT 1");
}

if (!columnExists($db, "reservations", "tenant_id")) {
    $db->exec("ALTER TABLE reservations ADD COLUMN tenant_id INTEGER DEFAULT 1");
}

if (!columnExists($db, "reservations", "customer_id")) {
    $db->exec("ALTER TABLE reservations ADD COLUMN customer_id INTEGER NULL");
}

if (!columnExists($db, "reservations", "rental_type")) {
    $db->exec("ALTER TABLE reservations ADD COLUMN rental_type TEXT DEFAULT 'short_term'");
}

if (!columnExists($db, "rooms", "price")) {
    $db->exec("ALTER TABLE rooms ADD COLUMN price REAL DEFAULT 0");
}

if (!columnExists($db, "reservations", "room_price")) {
    $db->exec("ALTER TABLE reservations ADD COLUMN room_price REAL DEFAULT 0");
}

if (!columnExists($db, "reservations", "discount_type")) {
    $db->exec("ALTER TABLE reservations ADD COLUMN discount_type VARCHAR(20) DEFAULT 'fixed'");
}

if (!columnExists($db, "reservations", "discount_value")) {
    $db->exec("ALTER TABLE reservations ADD COLUMN discount_value REAL DEFAULT 0");
}

if (!columnExists($db, "reservations", "final_price")) {
    $db->exec("ALTER TABLE reservations ADD COLUMN final_price REAL DEFAULT 0");
}

$db->exec("CREATE TABLE IF NOT EXISTS customers (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tenant_id INTEGER DEFAULT 1,
                    full_name TEXT,
                    phone_number TEXT,
                    id_type TEXT,
                    id_number TEXT,
                    birthday DATE NULL)");

$db->exec("CREATE TABLE IF NOT EXISTS reservation_service_fees (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tenant_id INTEGER DEFAULT 1,
                    reservation_id INTEGER NOT NULL,
                    invoice_id INTEGER NULL,
                    fee_type TEXT,
                    charge_mode TEXT DEFAULT 'one_time',
                    description TEXT,
                    meter_start REAL DEFAULT 0,
                    meter_end REAL DEFAULT 0,
                    period_start DATE NULL,
                    period_end DATE NULL,
                    amount REAL DEFAULT 0)");

if (!columnExists($db, "reservation_service_fees", "description")) {
    $db->exec("ALTER TABLE reservation_service_fees ADD COLUMN description TEXT");
}

if (!columnExists($db, "reservation_service_fees", "invoice_id")) {
    $db->exec("ALTER TABLE reservation_service_fees ADD COLUMN invoice_id INTEGER NULL");
}

if (!columnExists($db, "reservation_service_fees", "charge_mode")) {
    $db->exec("ALTER TABLE reservation_service_fees ADD COLUMN charge_mode TEXT DEFAULT 'one_time'");
}

$db->exec("CREATE TABLE IF NOT EXISTS reservation_invoices (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tenant_id INTEGER DEFAULT 1,
                    reservation_id INTEGER NOT NULL,
                    cycle_index INTEGER DEFAULT 1,
                    period_start DATE NOT NULL,
                    period_end DATE NOT NULL,
                    occupied_days INTEGER DEFAULT 0,
                    month_days INTEGER DEFAULT 0,
                    room_amount REAL DEFAULT 0,
                    service_amount REAL DEFAULT 0,
                    total_amount REAL DEFAULT 0,
                    payment_status TEXT DEFAULT 'unpaid',
                    payment_method TEXT NULL,
                    paid_amount REAL DEFAULT 0,
                    paid_at DATETIME NULL,
                    payment_ref TEXT NULL,
                    payment_note TEXT NULL)");

$db->exec("CREATE TABLE IF NOT EXISTS reservation_contract_terms (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tenant_id INTEGER DEFAULT 1,
                    reservation_id INTEGER NOT NULL,
                    electric_unit_price REAL DEFAULT 3000,
                    water_pricing_mode TEXT DEFAULT 'quota',
                    water_quota_price REAL DEFAULT 500,
                    water_per_person_price REAL DEFAULT 100000,
                    occupants_count INTEGER DEFAULT 1)");

$db->exec("CREATE TABLE IF NOT EXISTS tenant_settings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tenant_id INTEGER NOT NULL,
                    rental_mode TEXT DEFAULT 'both',
                    updated_at DATETIME NULL)");
