<?php

try {

    $pdo = new PDO(
        "mysql:host=localhost;dbname=u249540203_antal24;charset=utf8mb4",
        "u249540203_adminantal",
        "Antal2026!!"
    );

    echo "MYSQL OK";

} catch (PDOException $e) {

    die($e->getMessage());
}