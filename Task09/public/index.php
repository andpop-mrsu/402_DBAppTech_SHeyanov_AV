<?php

if (PHP_SAPI == 'cli-server') {
    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
}

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(true, true, true);

$dbPath = __DIR__ . "/../db/minesweeper.db";

function getDb($path) {
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

function jsonResponse(Response $response, $data, $status = 200) {
    $response->getBody()->write(json_encode($data));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($status);
}

$app->get('/', function (Request $request, Response $response) {
    return $response
        ->withHeader('Location', '/index.html')
        ->withStatus(302);
});

$app->get('/games', function (Request $request, Response $response) use ($dbPath) {
    $pdo = getDb($dbPath);
    $stmt = $pdo->query("SELECT * FROM games ORDER BY date DESC");
    $games = $stmt->fetchAll();

    foreach ($games as &$game) {
        $game["victory"] = (bool) $game["victory"];
        $game["surrendered"] = (bool) $game["surrendered"];
    }
    return jsonResponse($response, $games);
});

$app->get('/games/{id}', function (Request $request, Response $response, $args) use ($dbPath) {
    $gameId = $args['id'];
    $pdo = getDb($dbPath);
    $stmt = $pdo->prepare("SELECT * FROM moves WHERE game_id = ? ORDER BY t ASC");
    $stmt->execute([$gameId]);
    $moves = $stmt->fetchAll();

    foreach ($moves as &$move) {
        if ($move["type"] === "flag") {
            $move["value"] = (bool) $move["value"];
        }
    }
    return jsonResponse($response, $moves);
});

$app->post('/games', function (Request $request, Response $response) use ($dbPath) {
    $input = $request->getParsedBody();
    
    if (!$input) {
         return jsonResponse($response, ["error" => "Invalid JSON"], 400);
    }

    $pdo = getDb($dbPath);
    $stmt = $pdo->prepare(
        "INSERT INTO games (width, height, mines, player, seed, date) VALUES (?, ?, ?, ?, ?, ?)"
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
    return jsonResponse($response, ["id" => $id]);
});

$app->post('/step/{id}', function (Request $request, Response $response, $args) use ($dbPath) {
    $gameId = $args['id'];
    $input = $request->getParsedBody();

    if (!$input) {
        return jsonResponse($response, ["error" => "Invalid JSON"], 400);
    }

    $pdo = getDb($dbPath);

    if (isset($input["type"])) {
        $stmt = $pdo->prepare(
            "INSERT INTO moves (game_id, type, x, y, value, t) VALUES (?, ?, ?, ?, ?, ?)"
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
            $sql = "UPDATE games SET " . implode(", ", $updateFields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
    }

    return jsonResponse($response, ["status" => "ok"]);
});

$app->delete('/games', function (Request $request, Response $response) use ($dbPath) {
    $pdo = getDb($dbPath);
    $pdo->exec("DELETE FROM moves");
    $pdo->exec("DELETE FROM games");
    return jsonResponse($response, ["status" => "ok"]);
});

$app->run();
