<?php
$host = "127.0.0.1";
$port = 3306;
$username = "roomrsv";
$password = 'P@$$vv04d:roomrsv';
$database = "roomrsv";

$db = new PDO(
    "mysql:host=$host;port=$port",
    $username,
    $password,
    [
        PDO::ATTR_TIMEOUT => 5,
        PDO::ATTR_PERSISTENT => true,
    ]
);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("CREATE DATABASE IF NOT EXISTS `$database`");
$db->exec("use `$database`");
$db->exec("SET SESSION lock_wait_timeout = 5");
$schema_marker_file = __DIR__ . '/.db_mysql_schema_v1';

// Skip repetitive schema checks after initial migration.
if (file_exists($schema_marker_file)) {
    return;
}

function tableExists($dbh, $id)
{
    $stmt = $dbh->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name LIMIT 1");
    $stmt->bindValue(':table_name', $id);
    $stmt->execute();
    $results = $stmt->fetchColumn();
    if(!$results) {
        return false;
    }
    return true;
}

function columnExists($dbh, $table, $column)
{
    try {
        $stmt = $dbh->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name LIMIT 1");
        $stmt->bindValue(':table_name', $table);
        $stmt->bindValue(':column_name', $column);
        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

$exists = tableExists($db, "rooms");

if (!$exists) {
    //create the database
    $db->exec("CREATE TABLE IF NOT EXISTS rooms (
                        id INTEGER PRIMARY KEY AUTO_INCREMENT,
                        tenant_id INTEGER DEFAULT 1,
                        name TEXT,
                        capacity INTEGER,
                        status VARCHAR(30),
                        price DECIMAL(10,2) DEFAULT 0,
                        price_day DECIMAL(10,2) DEFAULT 300000,
                        price_hour DECIMAL(10,2) DEFAULT 80000)");

    $db->exec("CREATE TABLE IF NOT EXISTS reservations (
                        id INTEGER PRIMARY KEY AUTO_INCREMENT,
                        tenant_id INTEGER DEFAULT 1,
                        customer_id INTEGER NULL,
                        name TEXT,
                        start DATETIME,
                        `end` DATETIME,
                        rental_type VARCHAR(20) DEFAULT 'short_term',
                        room_id INTEGER,
                        status VARCHAR(30),
                        paid INTEGER,
                        room_price DECIMAL(10,2) DEFAULT 0,
                        discount_type VARCHAR(20) DEFAULT 'fixed',
                        discount_value DECIMAL(10,2) DEFAULT 0,
                        final_price DECIMAL(10,2) DEFAULT 0)");

    $db->exec("CREATE TABLE IF NOT EXISTS customers (
                        id INTEGER PRIMARY KEY AUTO_INCREMENT,
                        tenant_id INTEGER DEFAULT 1,
                        full_name VARCHAR(200),
                        phone_number VARCHAR(30),
                        id_type VARCHAR(30),
                        id_number VARCHAR(100),
                        birthday DATE NULL)");

    $db->exec("CREATE TABLE IF NOT EXISTS reservation_service_fees (
                        id INTEGER PRIMARY KEY AUTO_INCREMENT,
                        tenant_id INTEGER DEFAULT 1,
                        reservation_id INTEGER NOT NULL,
                        invoice_id INTEGER NULL,
                        fee_type VARCHAR(30),
                        charge_mode VARCHAR(20) DEFAULT 'one_time',
                        description VARCHAR(255),
                        meter_start DECIMAL(10,2) DEFAULT 0,
                        meter_end DECIMAL(10,2) DEFAULT 0,
                        period_start DATE NULL,
                        period_end DATE NULL,
                        amount DECIMAL(10,2) DEFAULT 0)");

    $db->exec("CREATE TABLE IF NOT EXISTS reservation_invoices (
                        id INTEGER PRIMARY KEY AUTO_INCREMENT,
                        tenant_id INTEGER DEFAULT 1,
                        reservation_id INTEGER NOT NULL,
                        cycle_index INTEGER DEFAULT 1,
                        period_start DATE NOT NULL,
                        period_end DATE NOT NULL,
                        occupied_days INTEGER DEFAULT 0,
                        month_days INTEGER DEFAULT 0,
                        room_amount DECIMAL(10,2) DEFAULT 0,
                        service_amount DECIMAL(10,2) DEFAULT 0,
                        total_amount DECIMAL(10,2) DEFAULT 0,
                        payment_status VARCHAR(20) DEFAULT 'unpaid',
                        payment_method VARCHAR(30) NULL,
                        paid_amount DECIMAL(10,2) DEFAULT 0,
                        paid_at DATETIME NULL,
                        payment_ref VARCHAR(100) NULL,
                        payment_note VARCHAR(255) NULL)");

    $db->exec("CREATE TABLE IF NOT EXISTS reservation_contract_terms (
                        id INTEGER PRIMARY KEY AUTO_INCREMENT,
                        tenant_id INTEGER DEFAULT 1,
                        reservation_id INTEGER NOT NULL,
                        electric_unit_price DECIMAL(10,2) DEFAULT 3000,
                        water_pricing_mode VARCHAR(30) DEFAULT 'quota',
                        water_quota_price DECIMAL(10,2) DEFAULT 500,
                        water_per_person_price DECIMAL(10,2) DEFAULT 100000,
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
    $db->exec("ALTER TABLE reservations ADD COLUMN rental_type VARCHAR(20) DEFAULT 'short_term'");
}

if (!columnExists($db, "rooms", "price")) {
    $db->exec("ALTER TABLE rooms ADD COLUMN price DECIMAL(10,2) DEFAULT 0");
}

if (!columnExists($db, "rooms", "price_day")) {
    $db->exec("ALTER TABLE rooms ADD COLUMN price_day DECIMAL(10,2) DEFAULT 300000");
}

if (!columnExists($db, "rooms", "price_hour")) {
    $db->exec("ALTER TABLE rooms ADD COLUMN price_hour DECIMAL(10,2) DEFAULT 80000");
}

$db->exec("UPDATE rooms SET price_day = 300000 WHERE price_day IS NULL OR price_day <= 0");
$db->exec("UPDATE rooms SET price_hour = 80000 WHERE price_hour IS NULL OR price_hour <= 0");

if (!columnExists($db, "reservations", "room_price")) {
    $db->exec("ALTER TABLE reservations ADD COLUMN room_price DECIMAL(10,2) DEFAULT 0");
}

if (!columnExists($db, "reservations", "discount_type")) {
    $db->exec("ALTER TABLE reservations ADD COLUMN discount_type VARCHAR(20) DEFAULT 'fixed'");
}

if (!columnExists($db, "reservations", "discount_value")) {
    $db->exec("ALTER TABLE reservations ADD COLUMN discount_value DECIMAL(10,2) DEFAULT 0");
}

if (!columnExists($db, "reservations", "final_price")) {
    $db->exec("ALTER TABLE reservations ADD COLUMN final_price DECIMAL(10,2) DEFAULT 0");
}

if (!tableExists($db, "customers")) {
    $db->exec("CREATE TABLE IF NOT EXISTS customers (
                        id INTEGER PRIMARY KEY AUTO_INCREMENT,
                        tenant_id INTEGER DEFAULT 1,
                        full_name VARCHAR(200),
                        phone_number VARCHAR(30),
                        id_type VARCHAR(30),
                        id_number VARCHAR(100),
                        birthday DATE NULL)");
}

if (!tableExists($db, "reservation_service_fees")) {
    $db->exec("CREATE TABLE IF NOT EXISTS reservation_service_fees (
                        id INTEGER PRIMARY KEY AUTO_INCREMENT,
                        tenant_id INTEGER DEFAULT 1,
                        reservation_id INTEGER NOT NULL,
                        invoice_id INTEGER NULL,
                        fee_type VARCHAR(30),
                        charge_mode VARCHAR(20) DEFAULT 'one_time',
                        description VARCHAR(255),
                        meter_start DECIMAL(10,2) DEFAULT 0,
                        meter_end DECIMAL(10,2) DEFAULT 0,
                        period_start DATE NULL,
                        period_end DATE NULL,
                        amount DECIMAL(10,2) DEFAULT 0)");
}

if (!columnExists($db, "reservation_service_fees", "description")) {
    $db->exec("ALTER TABLE reservation_service_fees ADD COLUMN description VARCHAR(255)");
}

if (!columnExists($db, "reservation_service_fees", "invoice_id")) {
    $db->exec("ALTER TABLE reservation_service_fees ADD COLUMN invoice_id INTEGER NULL");
}

if (!columnExists($db, "reservation_service_fees", "charge_mode")) {
    $db->exec("ALTER TABLE reservation_service_fees ADD COLUMN charge_mode VARCHAR(20) DEFAULT 'one_time'");
}

if (!tableExists($db, "reservation_invoices")) {
    $db->exec("CREATE TABLE IF NOT EXISTS reservation_invoices (
                        id INTEGER PRIMARY KEY AUTO_INCREMENT,
                        tenant_id INTEGER DEFAULT 1,
                        reservation_id INTEGER NOT NULL,
                        cycle_index INTEGER DEFAULT 1,
                        period_start DATE NOT NULL,
                        period_end DATE NOT NULL,
                        occupied_days INTEGER DEFAULT 0,
                        month_days INTEGER DEFAULT 0,
                        room_amount DECIMAL(10,2) DEFAULT 0,
                        service_amount DECIMAL(10,2) DEFAULT 0,
                        total_amount DECIMAL(10,2) DEFAULT 0,
                        payment_status VARCHAR(20) DEFAULT 'unpaid',
                        payment_method VARCHAR(30) NULL,
                        paid_amount DECIMAL(10,2) DEFAULT 0,
                        paid_at DATETIME NULL,
                        payment_ref VARCHAR(100) NULL,
                        payment_note VARCHAR(255) NULL)");
}

if (!tableExists($db, "reservation_contract_terms")) {
    $db->exec("CREATE TABLE IF NOT EXISTS reservation_contract_terms (
                        id INTEGER PRIMARY KEY AUTO_INCREMENT,
                        tenant_id INTEGER DEFAULT 1,
                        reservation_id INTEGER NOT NULL,
                        electric_unit_price DECIMAL(10,2) DEFAULT 3000,
                        water_pricing_mode VARCHAR(30) DEFAULT 'quota',
                        water_quota_price DECIMAL(10,2) DEFAULT 500,
                        water_per_person_price DECIMAL(10,2) DEFAULT 100000,
                        occupants_count INTEGER DEFAULT 1)");
}

if (!tableExists($db, "tenant_settings")) {
    $db->exec("CREATE TABLE IF NOT EXISTS tenant_settings (
                        id INTEGER PRIMARY KEY AUTO_INCREMENT,
                        tenant_id INTEGER NOT NULL,
                        rental_mode VARCHAR(20) DEFAULT 'both',
                        room_module_enabled TINYINT(1) DEFAULT 1,
                        short_term_day_threshold_hours INTEGER DEFAULT 4,
                        transport_module_enabled TINYINT(1) DEFAULT 1,
                        transport_dashboard_enabled TINYINT(1) DEFAULT 1,
                        updated_at DATETIME NULL)");
}

if (!columnExists($db, "tenant_settings", "room_module_enabled")) {
    $db->exec("ALTER TABLE tenant_settings ADD COLUMN room_module_enabled TINYINT(1) DEFAULT 1");
}

if (!columnExists($db, "tenant_settings", "short_term_day_threshold_hours")) {
    $db->exec("ALTER TABLE tenant_settings ADD COLUMN short_term_day_threshold_hours INTEGER DEFAULT 4");
}

if (!columnExists($db, "tenant_settings", "transport_module_enabled")) {
    $db->exec("ALTER TABLE tenant_settings ADD COLUMN transport_module_enabled TINYINT(1) DEFAULT 1");
}

if (!columnExists($db, "tenant_settings", "transport_dashboard_enabled")) {
    $db->exec("ALTER TABLE tenant_settings ADD COLUMN transport_dashboard_enabled TINYINT(1) DEFAULT 1");
}

if (!columnExists($db, "tenant_settings", "transport_default_discount_type")) {
    $db->exec("ALTER TABLE tenant_settings ADD COLUMN transport_default_discount_type VARCHAR(20) DEFAULT 'fixed'");
}

if (!columnExists($db, "tenant_settings", "transport_default_discount_value")) {
    $db->exec("ALTER TABLE tenant_settings ADD COLUMN transport_default_discount_value DECIMAL(10,2) DEFAULT 0");
}

if (!columnExists($db, "tenant_settings", "transport_default_cost_driver")) {
    $db->exec("ALTER TABLE tenant_settings ADD COLUMN transport_default_cost_driver DECIMAL(10,2) DEFAULT 0");
}

if (!columnExists($db, "tenant_settings", "transport_default_cost_toll")) {
    $db->exec("ALTER TABLE tenant_settings ADD COLUMN transport_default_cost_toll DECIMAL(10,2) DEFAULT 0");
}

if (!columnExists($db, "tenant_settings", "transport_default_cost_fuel")) {
    $db->exec("ALTER TABLE tenant_settings ADD COLUMN transport_default_cost_fuel DECIMAL(10,2) DEFAULT 0");
}

if (!columnExists($db, "tenant_settings", "transport_default_cost_parking")) {
    $db->exec("ALTER TABLE tenant_settings ADD COLUMN transport_default_cost_parking DECIMAL(10,2) DEFAULT 0");
}

if (!columnExists($db, "tenant_settings", "transport_default_cost_other")) {
    $db->exec("ALTER TABLE tenant_settings ADD COLUMN transport_default_cost_other DECIMAL(10,2) DEFAULT 0");
}

if (!columnExists($db, "tenant_settings", "transport_price_per_km")) {
    $db->exec("ALTER TABLE tenant_settings ADD COLUMN transport_price_per_km DECIMAL(10,2) DEFAULT 6000");
}

if (!columnExists($db, "tenant_settings", "transport_fuel_liters_per_100km")) {
    $db->exec("ALTER TABLE tenant_settings ADD COLUMN transport_fuel_liters_per_100km DECIMAL(10,2) DEFAULT 0");
}

if (!columnExists($db, "tenant_settings", "transport_fuel_price_per_liter")) {
    $db->exec("ALTER TABLE tenant_settings ADD COLUMN transport_fuel_price_per_liter DECIMAL(10,2) DEFAULT 22000");
}

if (!tableExists($db, "vehicles")) {
    $db->exec("CREATE TABLE IF NOT EXISTS vehicles (
                        id INTEGER PRIMARY KEY AUTO_INCREMENT,
                        tenant_id INTEGER DEFAULT 1,
                        name VARCHAR(200),
                        plate_no VARCHAR(60),
                        capacity INTEGER DEFAULT 4,
                        status VARCHAR(30) DEFAULT 'Ready',
                        fuel_consumption_per_100km DECIMAL(10,2) DEFAULT 8,
                        base_price DECIMAL(10,2) DEFAULT 6000)");
}

if (!columnExists($db, "vehicles", "fuel_consumption_per_100km")) {
    $db->exec("ALTER TABLE vehicles ADD COLUMN fuel_consumption_per_100km DECIMAL(10,2) DEFAULT 8");
}

if (!tableExists($db, "trip_reservations")) {
    $db->exec("CREATE TABLE IF NOT EXISTS trip_reservations (
                        id INTEGER PRIMARY KEY AUTO_INCREMENT,
                        tenant_id INTEGER DEFAULT 1,
                        customer_id INTEGER NULL,
                        vehicle_id INTEGER NOT NULL,
                        name VARCHAR(255),
                        driver_name VARCHAR(200),
                        distance_km DECIMAL(10,2) DEFAULT 0,
                        fuel_estimated_cost DECIMAL(10,2) DEFAULT 0,
                        start DATETIME,
                        `end` DATETIME,
                        status VARCHAR(30) DEFAULT 'New',
                        trip_price DECIMAL(10,2) DEFAULT 0,
                        discount_type VARCHAR(20) DEFAULT 'fixed',
                        discount_value DECIMAL(10,2) DEFAULT 0,
                        final_price DECIMAL(10,2) DEFAULT 0,
                        paid_amount DECIMAL(10,2) DEFAULT 0,
                        payment_status VARCHAR(20) DEFAULT 'unpaid',
                        payment_method VARCHAR(30) NULL,
                        payment_ref VARCHAR(120) NULL,
                        note VARCHAR(255) NULL)");
}

if (!columnExists($db, "trip_reservations", "customer_id")) {
    $db->exec("ALTER TABLE trip_reservations ADD COLUMN customer_id INTEGER NULL");
}

if (!columnExists($db, "trip_reservations", "distance_km")) {
    $db->exec("ALTER TABLE trip_reservations ADD COLUMN distance_km DECIMAL(10,2) DEFAULT 0");
}

if (!columnExists($db, "trip_reservations", "fuel_estimated_cost")) {
    $db->exec("ALTER TABLE trip_reservations ADD COLUMN fuel_estimated_cost DECIMAL(10,2) DEFAULT 0");
}

if (!columnExists($db, "trip_reservations", "payment_method")) {
    $db->exec("ALTER TABLE trip_reservations ADD COLUMN payment_method VARCHAR(30) NULL");
}

if (!columnExists($db, "trip_reservations", "payment_ref")) {
    $db->exec("ALTER TABLE trip_reservations ADD COLUMN payment_ref VARCHAR(120) NULL");
}

if (!tableExists($db, "trip_costs")) {
    $db->exec("CREATE TABLE IF NOT EXISTS trip_costs (
                        id INTEGER PRIMARY KEY AUTO_INCREMENT,
                        tenant_id INTEGER DEFAULT 1,
                        trip_reservation_id INTEGER NOT NULL,
                        cost_type VARCHAR(30) DEFAULT 'other',
                        description VARCHAR(255),
                        amount DECIMAL(10,2) DEFAULT 0,
                        incurred_at DATETIME NULL)");
}

if (!tableExists($db, "trip_invoices")) {
    $db->exec("CREATE TABLE IF NOT EXISTS trip_invoices (
                        id INTEGER PRIMARY KEY AUTO_INCREMENT,
                        tenant_id INTEGER DEFAULT 1,
                        trip_reservation_id INTEGER NOT NULL,
                        invoice_no VARCHAR(50) NULL,
                        total_amount DECIMAL(10,2) DEFAULT 0,
                        paid_amount DECIMAL(10,2) DEFAULT 0,
                        payment_status VARCHAR(20) DEFAULT 'unpaid',
                        payment_method VARCHAR(30) NULL,
                        payment_ref VARCHAR(120) NULL,
                        paid_at DATETIME NULL,
                        note VARCHAR(255) NULL)");
}

@file_put_contents($schema_marker_file, 'ok');
