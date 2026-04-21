<?php
$host = "127.0.0.1";
$port = 3306;
$username = "roomrsv";
$password = 'P@$$vv04d:roomrsv';
$database = "roomrsv";

$db = new PDO("mysql:host=$host;port=$port",
               $username,
               $password);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("CREATE DATABASE IF NOT EXISTS `$database`");
$db->exec("use `$database`");

function tableExists($dbh, $id)
{
    $results = $dbh->query("SHOW TABLES LIKE '$id'");
    if(!$results) {
        return false;
    }
    if($results->rowCount() > 0) {
        return true;
    }
    return false;
}

function columnExists($dbh, $table, $column)
{
    $stmt = $dbh->prepare("SHOW COLUMNS FROM `$table` LIKE :column");
    $stmt->bindValue(':column', $column);
    $stmt->execute();
    return $stmt->rowCount() > 0;
}

$exists = tableExists($db, "rooms");

if (!$exists) {
    //create the database
    $db->exec("CREATE TABLE IF NOT EXISTS rooms (
                        id INTEGER PRIMARY KEY AUTO_INCREMENT,
                        name TEXT,
                        capacity INTEGER,
                        status VARCHAR(30),
                        price DECIMAL(10,2) DEFAULT 0)");

    $db->exec("CREATE TABLE IF NOT EXISTS reservations (
                        id INTEGER PRIMARY KEY AUTO_INCREMENT,
                        name TEXT,
                        start DATETIME,
                        `end` DATETIME,
                        room_id INTEGER,
                        status VARCHAR(30),
                        paid INTEGER,
                        room_price DECIMAL(10,2) DEFAULT 0,
                        discount_type VARCHAR(20) DEFAULT 'fixed',
                        discount_value DECIMAL(10,2) DEFAULT 0,
                        final_price DECIMAL(10,2) DEFAULT 0)");

    $rooms = array(
                    array('name' => 'Room 1',
                        'id' => 1,
                        'capacity' => 2,
                        'status' => 'Dirty',
                        'price' => 600000),
                    array('name' => 'Room 2',
                        'id' => 2,
                        'capacity' => 2,
                        'status' => "Cleanup",
                        'price' => 650000),
                    array('name' => 'Room 3',
                        'id' => 3,
                        'capacity' => 2,
                        'status' => "Ready",
                        'price' => 700000),
                    array('name' => 'Room 4',
                        'id' => 4,
                        'capacity' => 4,
                        'status' => "Ready",
                        'price' => 1000000),
                    array('name' => 'Room 5',
                        'id' => 5,
                        'capacity' => 1,
                        'status' => "Ready",
                        'price' => 500000)
        );

    $insert = "INSERT INTO rooms (id, name, capacity, status, price) VALUES (:id, :name, :capacity, :status, :price)";
    $stmt = $db->prepare($insert);

    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':capacity', $capacity);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':price', $price);

    foreach ($rooms as $r) {
      $id = $r['id'];
      $name = $r['name'];
      $capacity = $r['capacity'];
      $status = $r['status'];
      $price = $r['price'];
      $stmt->execute();
    }

}

if (!columnExists($db, "rooms", "price")) {
    $db->exec("ALTER TABLE rooms ADD COLUMN price DECIMAL(10,2) DEFAULT 0");
}

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
