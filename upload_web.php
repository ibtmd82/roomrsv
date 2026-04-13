<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$targetDir = __DIR__ . "/uploads/";

if (!file_exists($targetDir)) {
    mkdir($targetDir, 0777, true);
}

$response = [];

if (isset($_FILES["uploaded_file"])) {
    $file = $_FILES["uploaded_file"];

    if ($file["error"] === UPLOAD_ERR_OK) {
        $filename = basename($file["name"]);
        $targetPath = $targetDir . $filename;

        if (move_uploaded_file($file["tmp_name"], $targetPath)) {
            $response = [
                "success" => true,
                "message" => "Upload thành công.",
                "filename" => $filename,
                "url" => $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . "/uploads/" . $filename
            ];
        } else {
            $response = [
                "success" => false,
                "message" => "Không thể lưu file."
            ];
        }
    } else {
        $response = [
            "success" => false,
            "message" => "Lỗi upload: " . $file["error"]
        ];
    }
} else {
    $response = [
        "success" => false,
        "message" => "Không có file nào được gửi."
    ];
}

echo json_encode($response);
