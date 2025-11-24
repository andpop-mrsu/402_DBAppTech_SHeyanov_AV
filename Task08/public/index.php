<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);

$dbPath = __DIR__ . "/../db/minesweeper.db";

function getDb($path)
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $pdo = new PDO("sqlite:$path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("CREATE TABLE IF NOT EXISTS games (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        width INTEGER,
        height INTEGER,
        mines INTEGER,
        player TEXT,
        seed INTEGER,
        date TEXT,
        seconds INTEGER DEFAULT 0,
        victory INTEGER DEFAULT 0,
        surrendered INTEGER DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS moves (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        game_id INTEGER,
        type TEXT,
        x INTEGER,
        y INTEGER,
        value INTEGER,
        t INTEGER,
        FOREIGN KEY(game_id) REFERENCES games(id)
    )");

    return $pdo;
}

$method = $_SERVER["REQUEST_METHOD"];
$uri = $_SERVER["REQUEST_URI"];
$path = parse_url($uri, PHP_URL_PATH);

if ($path !== "/" && file_exists(__DIR__ . $path)) {
    return false;
}

if ($path === "/") {
    header("Location: /index.html");
    exit();
}

function jsonResponse($data, $status = 200)
{
    header("Content-Type: application/json");
    http_response_code($status);
    echo json_encode($data);
    exit();
}

function getJsonInput()
{
    return json_decode(file_get_contents("php://input"), true);
}

try {
    $pdo = getDb($dbPath);

    if ($method === "GET" && $path === "/games") {
        $stmt = $pdo->query("SELECT * FROM games ORDER BY date DESC");
        $games = $stmt->fetchAll();

        foreach ($games as &$game) {
            $game["victory"] = (bool) $game["victory"];
            $game["surrendered"] = (bool) $game["surrendered"];
        }
        jsonResponse($games);
    } elseif (
        $method === "GET" &&
        preg_match('#^/games/(\d+)$#', $path, $matches)
    ) {
        $gameId = $matches[1];
        $stmt = $pdo->prepare(
            "SELECT * FROM moves WHERE game_id = ? ORDER BY t ASC",
        );
        $stmt->execute([$gameId]);
        $moves = $stmt->fetchAll();

        foreach ($moves as &$move) {
            if ($move["type"] === "flag") {
                $move["value"] = (bool) $move["value"];
            }
        }
        jsonResponse($moves);
    } elseif ($method === "POST" && $path === "/games") {
        $input = getJsonInput();
        if (!$input) {
            jsonResponse(["error" => "Invalid JSON"], 400);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO games (width, height, mines, player, seed, date) VALUES (?, ?, ?, ?, ?, ?)",
        );
        $date = date("Y-m-d H:i:s");

        $stmt->execute([
            $input["width"],
            $input["height"],
            $input["mines"],
            $input["player"] ?? "Anonymous",
            $input["seed"] ?? null,
            $date,
        ]);

        $id = $pdo->lastInsertId();
        jsonResponse(["id" => $id]);
    } elseif (
        $method === "POST" &&
        preg_match('#^/step/(\d+)$#', $path, $matches)
    ) {
        $gameId = $matches[1];
        $input = getJsonInput();
        if (!$input) {
            jsonResponse(["error" => "Invalid JSON"], 400);
        }

        if (isset($input["type"])) {
            $stmt = $pdo->prepare(
                "INSERT INTO moves (game_id, type, x, y, value, t) VALUES (?, ?, ?, ?, ?, ?)",
            );
            $stmt->execute([
                $gameId,
                $input["type"],
                $input["x"] ?? null,
                $input["y"] ?? null,
                isset($input["value"]) ? (int) $input["value"] : null,
                $input["t"] ?? round(microtime(true) * 1000),
            ]);
        }

        if (
            isset($input["seconds"]) ||
            isset($input["victory"]) ||
            isset($input["surrendered"])
        ) {
            $updateFields = [];
            $params = [];
            if (isset($input["seconds"])) {
                $updateFields[] = "seconds = ?";
                $params[] = $input["seconds"];
            }
            if (isset($input["victory"])) {
                $updateFields[] = "victory = ?";
                $params[] = (int) $input["victory"];
            }
            if (isset($input["surrendered"])) {
                $updateFields[] = "surrendered = ?";
                $params[] = (int) $input["surrendered"];
            }

            if (!empty($updateFields)) {
                $params[] = $gameId;
                $sql =
                    "UPDATE games SET " .
                    implode(", ", $updateFields) .
                    " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }
        }

        jsonResponse(["status" => "ok"]);
    } elseif ($method === "DELETE" && $path === "/games") {
        $pdo->exec("DELETE FROM moves");
        $pdo->exec("DELETE FROM games");
        jsonResponse(["status" => "ok"]);
    } else {
        jsonResponse(["error" => "Not Found"], 404);
    }
} catch (Exception $e) {
    jsonResponse(["error" => $e->getMessage()], 500);
}
