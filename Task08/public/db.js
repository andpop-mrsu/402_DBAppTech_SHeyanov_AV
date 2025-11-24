export async function createGame(gameData) {
  const response = await fetch("/games", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(gameData),
  });
  if (!response.ok) throw new Error("Failed to create game");
  return await response.json();
}

export async function saveResult(result) {
  const gameId = result.gameId;
  if (!gameId) return;

  const response = await fetch(`/step/${gameId}`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      seconds: result.seconds,
      victory: result.victory ? 1 : 0,
      surrendered: result.surrendered ? 1 : 0,
    }),
  });

  if (!response.ok) throw new Error("Failed to save result");
  return await response.json();
}

export async function getAllResults() {
  const response = await fetch("/games");
  if (!response.ok) throw new Error("Failed to fetch games");
  return await response.json();
}

export async function clearResults() {
  const response = await fetch("/games", {
    method: "DELETE",
  });
  if (!response.ok) throw new Error("Failed to clear results");
  return await response.json();
}

export async function addMove(gameId, move) {
  const response = await fetch(`/step/${gameId}`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(move),
  });
  if (!response.ok) throw new Error("Failed to add move");
  return await response.json();
}

export async function getMoves(gameId) {
  const response = await fetch(`/games/${gameId}`);
  if (!response.ok) throw new Error("Failed to fetch moves");
  return await response.json();
}
